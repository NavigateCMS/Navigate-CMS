<?php

declare(strict_types=1);

namespace IpUtils\Address;

use IpUtils\Expression\ExpressionInterface;

interface AddressInterface
{
    /**
     * get fully expanded address
     */
    public function getExpanded(): string;

    /**
     * get compact address representation
     */
    public function getCompact(): string;

    /**
     * get IP-specific chunks ([127,000,000,001] for IPv4 or [0000,0000,00ff,00ea,0001,...] for IPv6)
     */
    public function getChunks(): array;

    /**
     * check whether the address matches a given pattern/range
     */
    public function matches(ExpressionInterface $expression): bool;
}
