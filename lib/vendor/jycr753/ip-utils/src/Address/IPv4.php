<?php

declare(strict_types=1);

namespace IpUtils\Address;

use IpUtils\Expression\ExpressionInterface;
use IpUtils\Expression\Subnet;
use UnexpectedValueException;

class IPv4 implements AddressInterface
{
    protected string $address;

    public function __construct(string $address)
    {
        if (! self::isValid($address)) {
            throw new UnexpectedValueException('"' . $address . '" is no valid IPv4 address.');
        }

        $this->address = $address;
    }

    public static function isValid(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function isValidNetmask(int $netmask): bool
    {
        return $netmask >= 1 && $netmask <= 32;
    }

    public static function getLoopback(): self
    {
        return new self('127.0.0.1');
    }

    public function __toString()
    {
        return $this->getCompact();
    }

    public function getCompact(): string
    {
        return $this->getExpanded();
    }

    public function getExpanded(): string
    {
        return $this->address;
    }

    public function getChunks(): array
    {
        return explode('.', $this->getExpanded());
    }

    public function isLoopback(): bool
    {
        return $this->matches(new Subnet('127.0.0.0/8'));
    }

    public function matches(ExpressionInterface $expression): bool
    {
        return $expression->matches($this);
    }

    public function isPrivate(): bool
    {
        return
            $this->matches(new Subnet('10.0.0.0/8')) ||
            $this->matches(new Subnet('172.16.0.0/12')) ||
            $this->matches(new Subnet('192.168.0.0/16'));
    }

    public function isMulticast(): bool
    {
        return $this->matches(new Subnet('224.0.0.0/4'));
    }

    public function isLinkLocal(): bool
    {
        return $this->matches(new Subnet('169.254.1.0/24'));
    }
}
