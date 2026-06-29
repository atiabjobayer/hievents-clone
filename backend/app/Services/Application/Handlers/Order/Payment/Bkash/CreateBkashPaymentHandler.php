<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Bkash;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\Bkash\BkashPaymentException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Payment\Bkash\BkashPaymentService;
use HiEvents\Services\Domain\Payment\Bkash\DTOs\CreateBkashPaymentResponseDTO;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Throwable;

readonly class CreateBkashPaymentHandler
{
    public function __construct(
        private OrderRepositoryInterface            $orderRepository,
        private BkashPaymentService                 $bkashPaymentService,
        private CheckoutSessionManagementService    $sessionIdentifierService,
        private EventSettingsRepositoryInterface    $eventSettingsRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     * @throws UnauthorizedException
     * @throws BkashPaymentException
     * @throws Throwable
     */
    public function handle(int $eventId, string $orderShortId): CreateBkashPaymentResponseDTO
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->loadRelation(new Relationship(EventDomainObject::class, name: 'event'))
            ->findByShortId($orderShortId);

        if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
            throw new UnauthorizedException(
                __('Sorry, we could not verify your session. Please create a new order.')
            );
        }

        if ($order->getStatus() !== OrderStatus::RESERVED->name || $order->isReservedOrderExpired()) {
            throw new ResourceConflictException(
                __('This order is expired or not in a valid state for payment.')
            );
        }

        if ($order->getTotalGross() <= 0) {
            throw new ResourceConflictException(
                __('This order does not require payment.')
            );
        }

        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObject::EVENT_ID => $eventId,
        ]);

        if (!$eventSettings || !in_array(PaymentProviders::BKASH->value, $eventSettings->getPaymentProviders() ?? [], true)) {
            throw new UnauthorizedException(
                __('bKash payments are not enabled for this event.')
            );
        }

        // Always create a fresh bKash payment — never reuse a previous one.
        // bKash invalidates the hash after any execute attempt (even failed ones),
        // and reusing a stale hash causes "Invalid page access request" in the popup.

        $amount = number_format($order->getTotalGross(), 2, '.', '');
        $currency = $order->getCurrency();

        return $this->bkashPaymentService->createPayment(
            order: $order,
            amount: $amount,
            currency: $currency,
        );
    }
}
