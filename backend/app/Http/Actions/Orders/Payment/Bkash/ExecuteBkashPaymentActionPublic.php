<?php

namespace HiEvents\Http\Actions\Orders\Payment\Bkash;

use HiEvents\Exceptions\Bkash\BkashPaymentException;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Payment\Bkash\BkashPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExecuteBkashPaymentActionPublic extends BaseAction
{
    public function __construct(
        private readonly BkashPaymentService $bkashPaymentService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $paymentID = $request->input('paymentID');

        if (empty($paymentID)) {
            return $this->errorResponse(
                __('Payment ID is required.'),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $result = $this->bkashPaymentService->executePayment($paymentID);
        } catch (CannotAcceptPaymentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        } catch (BkashPaymentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse([
            'paymentID' => $result->paymentID,
            'trxID' => $result->trxID,
            'transactionStatus' => $result->transactionStatus,
            'amount' => $result->amount,
            'currency' => $result->currency,
        ]);
    }
}
