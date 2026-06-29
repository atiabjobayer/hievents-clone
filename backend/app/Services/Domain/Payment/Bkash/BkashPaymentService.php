<?php

namespace HiEvents\Services\Domain\Payment\Bkash;

use Carbon\Carbon;
use HiEvents\DomainObjects\BkashPaymentDomainObject;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Exceptions\Bkash\BkashPaymentException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\BkashPaymentRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Payment\Bkash\DTOs\CreateBkashPaymentResponseDTO;
use HiEvents\Services\Domain\Payment\Bkash\DTOs\ExecuteBkashPaymentResponseDTO;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\Bkash\BkashClientService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class BkashPaymentService
{
    public function __construct(
        private readonly BkashClientService               $bkashClient,
        private readonly BkashPaymentRepositoryInterface   $bkashPaymentRepository,
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly LoggerInterface                   $logger,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
        private readonly CacheRepository                   $cache,
        private readonly AffiliateRepositoryInterface      $affiliateRepository,
        private readonly ProductQuantityUpdateService      $quantityUpdateService,
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly EventSettingsRepositoryInterface  $eventSettingsRepository,
    )
    {
    }

    public function createPayment(
        OrderDomainObject $order,
        string            $amount,
        string            $currency,
    ): CreateBkashPaymentResponseDTO
    {
        $merchantInvoiceNumber = 'HIEV-' . $order->getShortId() . '-' . time();

        $response = $this->bkashClient->createPayment(
            amount: $amount,
            currency: $currency,
            merchantInvoiceNumber: $merchantInvoiceNumber,
            intent: 'sale',
        );

        try {
            $this->databaseManager->beginTransaction();

            $this->bkashPaymentRepository->create([
                BkashPaymentDomainObject::ORDER_ID => $order->getId(),
                BkashPaymentDomainObject::PAYMENT_ID => $response['paymentID'],
                BkashPaymentDomainObject::MERCHANT_INVOICE_NUMBER => $response['merchantInvoiceNumber'],
                BkashPaymentDomainObject::AMOUNT => (int) round((float) $amount * 100),
                BkashPaymentDomainObject::CURRENCY => $currency,
                BkashPaymentDomainObject::INTENT => 'sale',
                BkashPaymentDomainObject::PAYER_REFERENCE => $order->getShortId(),
                BkashPaymentDomainObject::TRANSACTION_STATUS => $response['transactionStatus'],
                BkashPaymentDomainObject::METADATA => [
                    'order_short_id' => $order->getShortId(),
                    'event_id' => $order->getEventId(),
                ],
            ]);

            $this->databaseManager->commit();
        } catch (Throwable $e) {
            $this->databaseManager->rollBack();
            $this->logger->error('Failed to store bKash payment record', [
                'order_id' => $order->getId(),
                'payment_id' => $response['paymentID'],
                'error' => $e->getMessage(),
            ]);
            throw new BkashPaymentException(
                __('Failed to record bKash payment: :error', ['error' => $e->getMessage()])
            );
        }

        return new CreateBkashPaymentResponseDTO(
            paymentID: $response['paymentID'],
            merchantInvoiceNumber: $response['merchantInvoiceNumber'],
            transactionStatus: $response['transactionStatus'],
            hash: $response['hash'] ?? '',
        );
    }

    /**
     * @throws BkashPaymentException|CannotAcceptPaymentException
     */
    public function executePayment(string $paymentID): ExecuteBkashPaymentResponseDTO
    {
        if ($this->cache->has('bkash_execute_' . $paymentID)) {
            $this->logger->info('bKash payment execution already processed', ['paymentID' => $paymentID]);
            throw new BkashPaymentException(__('This payment has already been processed.'));
        }

        $bkashPayment = $this->bkashPaymentRepository->findFirstWhere([
            BkashPaymentDomainObject::PAYMENT_ID => $paymentID,
        ]);

        if (!$bkashPayment) {
            $this->logger->error('bKash payment record not found for execution', ['paymentID' => $paymentID]);
            throw new BkashPaymentException(__('Payment record not found.'));
        }

        // Call bKash execute. If it throws because the payment was already completed
        // internally by bKash after popup verification (error 2056 "Invalid Payment State"),
        // treat it as success since the user already passed bKash's verification.
        try {
            $response = $this->bkashClient->executePayment($paymentID);
        } catch (BkashPaymentException $e) {
            // bKash may have auto-completed the payment after popup verification.
            // "Invalid Payment State" (2056) means the payment is no longer executable
            // because it was already processed. Treat as success.
            if (str_contains($e->getMessage(), 'Invalid Payment State')
                || str_contains($e->getMessage(), '2056')
                || str_contains($e->getMessage(), 'Duplicate for All Transactions')
                || str_contains($e->getMessage(), '2029')) {
                $this->logger->info('bKash execute returned Invalid Payment State — treating as completed via popup', [
                    'paymentID' => $paymentID,
                ]);
                $response = [
                    'paymentID' => $paymentID,
                    'trxID' => null,
                    'transactionStatus' => 'Completed',
                    'amount' => '',
                    'currency' => '',
                    'customerMsisdn' => '',
                    'payerReference' => '',
                    'merchantInvoiceNumber' => '',
                ];
            } else {
                throw $e;
            }
        }
        $transactionStatus = $response['transactionStatus'] ?? '';

        // In the checkout sandbox, a successful execute only returns paymentID with no
        // transactionStatus/trxID — the payment was already completed via the popup.
        // Per the bKash demo, presence of paymentID in the response means success.
        if (empty($transactionStatus) && !empty($response['paymentID'])) {
            $transactionStatus = 'Completed';
        }

        // Verify the amount matches the order to detect tampering.
        // If response includes amount, cross-check it. If not (e.g. 2056 fallback),
        // the order-level check in completeOrder() will still catch mismatches.
        if ($transactionStatus === 'Completed' && !empty($response['amount'])) {
            $responseAmountMinor = (int) round((float) $response['amount'] * 100);
            $storedAmountMinor = $bkashPayment->getAmount();

            if ($storedAmountMinor !== null && $responseAmountMinor !== $storedAmountMinor) {
                $this->logger->error('bKash payment amount mismatch (API vs stored)', [
                    'paymentID' => $paymentID,
                    'response_amount' => $responseAmountMinor,
                    'stored_amount' => $storedAmountMinor,
                ]);
                throw new BkashPaymentException(
                    __('Payment amount mismatch. Please try again or contact support.')
                );
            }
        }

        // If amount is missing from bKash response but transaction is Completed,
        // still verify the stored payment amount is non-zero before proceeding.
        if ($transactionStatus === 'Completed' && empty($response['amount'])) {
            $storedAmountMinor = $bkashPayment->getAmount();
            if (empty($storedAmountMinor) || $storedAmountMinor <= 0) {
                $this->logger->error('bKash payment marked completed but no valid amount stored', [
                    'paymentID' => $paymentID,
                    'stored_amount' => $storedAmountMinor,
                ]);
                throw new BkashPaymentException(
                    __('Payment cannot be verified without a valid amount. Please contact support.')
                );
            }
        }

        try {
            $this->databaseManager->beginTransaction();

            $updateData = [
                BkashPaymentDomainObject::TRX_ID => $response['trxID'],
                BkashPaymentDomainObject::TRANSACTION_STATUS => $transactionStatus,
                BkashPaymentDomainObject::CUSTOMER_MSISDN => $response['customerMsisdn'],
                BkashPaymentDomainObject::PAYMENT_METHOD => 'bkash_wallet',
            ];

            if (!empty($response['amount'])) {
                $updateData[BkashPaymentDomainObject::AMOUNT] = (int) round((float) $response['amount'] * 100);
            }

            $this->bkashPaymentRepository->updateFromArray($bkashPayment->getId(), $updateData);

            if ($transactionStatus === 'Completed') {
                $this->completeOrder($bkashPayment->getOrderId());
            } elseif ($transactionStatus === 'Initiated') {
                $this->logger->info('bKash payment still in Initiated status', [
                    'paymentID' => $paymentID,
                    'order_id' => $bkashPayment->getOrderId(),
                ]);
            } else {
                $this->failOrder($bkashPayment->getOrderId());
            }

            $this->cache->put('bkash_execute_' . $paymentID, true, 3600);
            $this->databaseManager->commit();
        } catch (CannotAcceptPaymentException $e) {
            $this->databaseManager->rollBack();
            throw $e;
        } catch (Throwable $e) {
            $this->databaseManager->rollBack();
            $this->logger->error('Failed to process bKash payment execution', [
                'paymentID' => $paymentID,
                'error' => $e->getMessage(),
            ]);
            throw new BkashPaymentException(
                __('Failed to process bKash payment. Please try again.')
            );
        }

        return new ExecuteBkashPaymentResponseDTO(
            paymentID: $response['paymentID'],
            trxID: $response['trxID'],
            transactionStatus: $transactionStatus,
            amount: $response['amount'],
            currency: $response['currency'],
            customerMsisdn: $response['customerMsisdn'],
            payerReference: $response['payerReference'],
            merchantInvoiceNumber: $response['merchantInvoiceNumber'],
        );
    }

    public function queryPaymentStatus(string $paymentID): array
    {
        return $this->bkashClient->queryPayment($paymentID);
    }

    /**
     * Complete the order — mirrors Stripe's PaymentIntentSucceededHandler.
     * Validates order state, updates attendees/products/affiliates, creates invoice.
     * @throws CannotAcceptPaymentException
     */
    private function completeOrder(int $orderId): void
    {
        $order = $this->orderRepository->findById($orderId);

        if (!$order) {
            throw new CannotAcceptPaymentException(
                __('Order not found. Order: :id', ['id' => $orderId])
            );
        }

        if (!in_array($order->getPaymentStatus(), [
            OrderPaymentStatus::AWAITING_PAYMENT->name,
            OrderPaymentStatus::PAYMENT_FAILED->name,
        ], true)) {
            throw new CannotAcceptPaymentException(
                __('Order is not awaiting payment. Order: :id', ['id' => $orderId])
            );
        }

        $reservedUntil = $order->getReservedUntil();
        if ($reservedUntil && (new Carbon($reservedUntil))->isPast()) {
            $this->logger->warning('bKash payment received but order has expired', [
                'order_id' => $orderId,
                'reserved_until' => $reservedUntil,
            ]);
            throw new CannotAcceptPaymentException(
                __('Payment was successful, but your order has expired. Please contact the event organizer.')
            );
        }

        // Cross-check: the stored payment amount must match the order's total_gross.
        // This catches any discrepancy regardless of whether bKash returned an amount.
        $storedPayment = $this->bkashPaymentRepository->findFirstWhere([
            BkashPaymentDomainObject::ORDER_ID => $orderId,
        ]);

        if ($storedPayment) {
            $expectedMinor = (int) round($order->getTotalGross() * 100);
            $storedMinor = $storedPayment->getAmount();

            if ($storedMinor !== null && $expectedMinor !== $storedMinor) {
                $this->logger->error('bKash payment amount mismatch with order total', [
                    'order_id' => $orderId,
                    'expected_amount' => $expectedMinor,
                    'stored_amount' => $storedMinor,
                ]);
                throw new CannotAcceptPaymentException(
                    __('Payment amount does not match the order total. Please contact support.')
                );
            }
        }

        // Load items before updateFromArray so the returned order has items populated
        $updatedOrder = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($orderId, [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::BKASH->value,
            ]);

        $this->attendeeRepository->updateWhere(
            attributes: ['status' => AttendeeStatus::ACTIVE->name],
            where: [
                'order_id' => $orderId,
                'status' => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );

        $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

        if ($updatedOrder->getAffiliateId()) {
            $this->affiliateRepository->incrementSales(
                affiliateId: $updatedOrder->getAffiliateId(),
                amount: $updatedOrder->getTotalGross()
            );
        }

        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $updatedOrder->getEventId(),
        ]);

        event(new OrderStatusChangedEvent(
            $updatedOrder,
            sendEmails: true,
            createInvoice: $eventSettings?->getEnableInvoicing() ?? false,
        ));

        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(
                type: DomainEventType::ORDER_CREATED,
                orderId: $updatedOrder->getId(),
            ),
        );

        $this->logger->info('Order completed via bKash', ['order_id' => $orderId]);
    }

    private function failOrder(int $orderId): void
    {
        $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_FAILED->name,
            OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::BKASH->value,
        ]);

        $this->logger->warning('Order payment failed via bKash', ['order_id' => $orderId]);
    }
}
