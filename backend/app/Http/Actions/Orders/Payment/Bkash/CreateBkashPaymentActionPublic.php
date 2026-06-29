<?php

namespace HiEvents\Http\Actions\Orders\Payment\Bkash;

use HiEvents\Exceptions\Bkash\BkashPaymentException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Bkash\CreateBkashPaymentHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateBkashPaymentActionPublic extends BaseAction
{
    public function __construct(
        private readonly CreateBkashPaymentHandler $createBkashPaymentHandler,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $result = $this->createBkashPaymentHandler->handle($eventId, $orderShortId);
        } catch (BkashPaymentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse([
            'payment_id' => $result->paymentID,
            'merchant_invoice_number' => $result->merchantInvoiceNumber,
            'transaction_status' => $result->transactionStatus,
            'hash' => $result->hash,
        ]);
    }
}
