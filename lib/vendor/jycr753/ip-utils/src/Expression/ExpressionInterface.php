<?php

declare(strict_types=1);

namespace IpUtils\Expression;

use IpUtils\Address\AddressInterface;

interface ExpressionInterface
{
    /**
     * check whether the expression matches an address
     */
    public function matches(AddressInterface $address): bool;
}
