<?php

namespace HiEvents\Services\Domain\Payment\Bkash\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateBkashPaymentResponseDTO extends BaseDTO
{
    public function __construct(
        public readonly string $paymentID,
        public readonly string $merchantInvoiceNumber,
        public readonly string $transactionStatus,
        public readonly string $hash = '',
    )
    {
    }
}
