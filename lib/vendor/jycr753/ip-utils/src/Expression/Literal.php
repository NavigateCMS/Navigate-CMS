<?php

declare(strict_types=1);

namespace IpUtils\Expression;

use IpUtils\Address\AddressInterface;
use IpUtils\Address\IPv4;
use IpUtils\Address\IPv6;
use IpUtils\Exception\InvalidExpressionException;

class Literal implements ExpressionInterface
{
    protected string $expression;

    /**
     * @throws \IpUtils\Exception\InvalidExpressionException
     */
    public function __construct($expression)
    {
        $expression = strtolower(trim($expression));

        if (IPv4::isValid($expression)) {
            $ip = new IPv4($expression);
        } elseif (IPv6::isValid($expression)) {
            $ip = new IPv6($expression);
        } else {
            throw new InvalidExpressionException('Expression must be either a valid IPv4 or IPv6 address.');
        }

        $this->expression = $ip->getCompact();
    }

    /**
     * check whether the expression matches an address
     */
    public function matches(AddressInterface $address): bool
    {
        return $address->getCompact() === $this->expression;
    }
}
