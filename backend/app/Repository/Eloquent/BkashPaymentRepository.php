<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BkashPaymentDomainObject;
use HiEvents\Models\BkashPayment;
use HiEvents\Repository\Interfaces\BkashPaymentRepositoryInterface;

/**
 * @extends BaseRepository<BkashPaymentDomainObject>
 */
class BkashPaymentRepository extends BaseRepository implements BkashPaymentRepositoryInterface
{
    protected function getModel(): string
    {
        return BkashPayment::class;
    }

    public function getDomainObject(): string
    {
        return BkashPaymentDomainObject::class;
    }
}
