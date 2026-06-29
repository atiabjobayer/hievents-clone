<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Bkash\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class BkashPaymentPublicDTO extends BaseDTO
{
    public function __construct(
        public string $paymentID,
        public string $transactionStatus,
        public ?string $trxID,
        public string $amount,
    )
    {
    }
}
