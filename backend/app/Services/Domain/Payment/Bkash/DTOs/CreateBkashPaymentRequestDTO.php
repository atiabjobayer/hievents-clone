<?php

namespace HiEvents\Services\Domain\Payment\Bkash\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateBkashPaymentRequestDTO extends BaseDTO
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currencyCode,
        public readonly string $merchantInvoiceNumber,
        public readonly string $payerReference,
        public readonly string $intent = 'sale',
    )
    {
    }
}
