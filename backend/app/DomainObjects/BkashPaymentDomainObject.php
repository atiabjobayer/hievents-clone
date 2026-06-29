<?php

namespace HiEvents\DomainObjects;

class BkashPaymentDomainObject extends AbstractDomainObject
{
    final public const SINGULAR_NAME = 'bkash_payment';
    final public const PLURAL_NAME = 'bkash_payments';
    final public const ID = 'id';
    final public const ORDER_ID = 'order_id';
    final public const PAYMENT_ID = 'payment_id';
    final public const TRX_ID = 'trx_id';
    final public const TRANSACTION_STATUS = 'transaction_status';
    final public const MERCHANT_INVOICE_NUMBER = 'merchant_invoice_number';
    final public const AMOUNT = 'amount';
    final public const CURRENCY = 'currency';
    final public const INTENT = 'intent';
    final public const PAYER_REFERENCE = 'payer_reference';
    final public const METADATA = 'metadata';
    final public const LAST_ERROR = 'last_error';
    final public const PAYMENT_METHOD = 'payment_method';
    final public const CUSTOMER_MSISDN = 'customer_msisdn';
    final public const CREATED_AT = 'created_at';
    final public const UPDATED_AT = 'updated_at';
    final public const DELETED_AT = 'deleted_at';

    private ?OrderDomainObject $order = null;

    public function getOrder(): ?OrderDomainObject
    {
        return $this->order;
    }

    public function setOrder(?OrderDomainObject $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getId(): int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }
    public function getOrderId(): int { return $this->order_id; }
    public function setOrderId(int $order_id): self { $this->order_id = $order_id; return $this; }
    public function getPaymentId(): string { return $this->payment_id; }
    public function setPaymentId(string $payment_id): self { $this->payment_id = $payment_id; return $this; }
    public function getTrxId(): ?string { return $this->trx_id; }
    public function setTrxId(?string $trx_id): self { $this->trx_id = $trx_id; return $this; }
    public function getTransactionStatus(): ?string { return $this->transaction_status; }
    public function setTransactionStatus(?string $status): self { $this->transaction_status = $status; return $this; }
    public function getMerchantInvoiceNumber(): ?string { return $this->merchant_invoice_number; }
    public function setMerchantInvoiceNumber(?string $number): self { $this->merchant_invoice_number = $number; return $this; }
    public function getAmount(): ?int { return $this->amount; }
    public function setAmount(?int $amount): self { $this->amount = $amount; return $this; }
    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): self { $this->currency = $currency; return $this; }
    public function getIntent(): ?string { return $this->intent; }
    public function setIntent(?string $intent): self { $this->intent = $intent; return $this; }
    public function getPayerReference(): ?string { return $this->payer_reference; }
    public function setPayerReference(?string $ref): self { $this->payer_reference = $ref; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): self { $this->metadata = $metadata; return $this; }
    public function getLastError(): ?string { return $this->last_error; }
    public function setLastError(?string $error): self { $this->last_error = $error; return $this; }
    public function getPaymentMethod(): ?string { return $this->payment_method; }
    public function setPaymentMethod(?string $method): self { $this->payment_method = $method; return $this; }
    public function getCustomerMsisdn(): ?string { return $this->customer_msisdn; }
    public function setCustomerMsisdn(?string $msisdn): self { $this->customer_msisdn = $msisdn; return $this; }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'order_id' => $this->order_id ?? null,
            'payment_id' => $this->payment_id ?? null,
            'trx_id' => $this->trx_id ?? null,
            'transaction_status' => $this->transaction_status ?? null,
            'merchant_invoice_number' => $this->merchant_invoice_number ?? null,
            'amount' => $this->amount ?? null,
            'currency' => $this->currency ?? null,
            'intent' => $this->intent ?? null,
            'payer_reference' => $this->payer_reference ?? null,
            'metadata' => $this->metadata ?? null,
            'last_error' => $this->last_error ?? null,
            'payment_method' => $this->payment_method ?? null,
            'customer_msisdn' => $this->customer_msisdn ?? null,
        ];
    }
}
