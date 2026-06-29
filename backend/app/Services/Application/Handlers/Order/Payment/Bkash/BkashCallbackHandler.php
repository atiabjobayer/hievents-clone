<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Bkash;

use HiEvents\Exceptions\Bkash\BkashPaymentException;
use HiEvents\Services\Domain\Payment\Bkash\BkashPaymentService;
use HiEvents\Services\Domain\Payment\Bkash\DTOs\ExecuteBkashPaymentResponseDTO;
use Psr\Log\LoggerInterface;

readonly class BkashCallbackHandler
{
    public function __construct(
        private BkashPaymentService $bkashPaymentService,
        private LoggerInterface     $logger,
    )
    {
    }

    /**
     * Handle the callback/redirect from bKash after payment.
     * bKash redirects the user's browser to our callback URL with query params:
     *   ?status=success&paymentID=xxx  (or status=failure / status=cancel)
     *
     * @throws BkashPaymentException
     */
    public function handle(string $status, string $paymentID): ExecuteBkashPaymentResponseDTO
    {
        $this->logger->info('bKash callback received', [
            'status' => $status,
            'paymentID' => $paymentID,
        ]);

        if ($status === 'success' || $status === 'Success') {
            return $this->bkashPaymentService->executePayment($paymentID);
        }

        if ($status === 'failure' || $status === 'Failure') {
            // Payment failed at bKash side - mark as failed
            return $this->bkashPaymentService->executePayment($paymentID);
        }

        if ($status === 'cancel' || $status === 'Cancel') {
            throw new BkashPaymentException(
                __('Payment was cancelled by the user.')
            );
        }

        throw new BkashPaymentException(
            __('Unknown payment status received from bKash: :status', ['status' => $status])
        );
    }
}
