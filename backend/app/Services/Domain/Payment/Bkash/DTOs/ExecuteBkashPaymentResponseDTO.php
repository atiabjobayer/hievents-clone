<?php

namespace HiEvents\Services\Domain\Payment\Bkash\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;

class ExecuteBkashPaymentResponseDTO extends BaseDTO
{
    public function __construct(
        public readonly string $paymentID,
        public readonly ?string $trxID,
        public readonly string $transactionStatus,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $customerMsisdn,
        public readonly ?string $payerReference,
        public readonly ?string $merchantInvoiceNumber,
    )
    {
    }
}
