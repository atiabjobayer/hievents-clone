<?php

namespace HiEvents\Http\Actions\Orders\Payment\Bkash;

use HiEvents\DomainObjects\BkashPaymentDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\BkashPaymentRepositoryInterface;
use HiEvents\Services\Domain\Payment\Bkash\BkashPaymentService;
use HiEvents\Services\Application\Handlers\Order\Payment\Bkash\DTO\BkashPaymentPublicDTO;
use Illuminate\Http\JsonResponse;

class GetBkashPaymentStatusActionPublic extends BaseAction
{
    public function __construct(
        private readonly BkashPaymentRepositoryInterface $bkashPaymentRepository,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        $bkashPayment = $this->bkashPaymentRepository->findFirstWhere([
            BkashPaymentDomainObject::PAYER_REFERENCE => $orderShortId,
        ], 'created_at', 'desc');

        if (!$bkashPayment) {
            return $this->jsonResponse([
                'status' => 'not_found',
            ]);
        }

        return $this->jsonResponse([
            'payment_id' => $bkashPayment->getPaymentId(),
            'transaction_status' => $bkashPayment->getTransactionStatus(),
            'trx_id' => $bkashPayment->getTrxId(),
            'amount' => $bkashPayment->getAmount(),
        ]);
    }
}
