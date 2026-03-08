<?php

declare(strict_types=1);

namespace IpUtils\Address;

use IpUtils\Expression\ExpressionInterface;
use IpUtils\Expression\Subnet;
use UnexpectedValueException;

class IPv6 implements AddressInterface
{
    protected string $address;

    public function __construct($address)
    {
        if (! self::isValid($address)) {
            throw new UnexpectedValueException('"' . $address . '" is no valid IPv6 address.');
        }

        $this->address = implode(
            ':',
            array_map(
                static function ($b) {
                    return sprintf('%04x', $b);
                },
                unpack('n*', inet_pton($address))
            )
        );
    }

    public static function isValid(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    public static function isValidNetmask(int $netmask): bool
    {
        return $netmask >= 1 && $netmask <= 128;
    }

    public static function getLoopback(): self
    {
        return new self('::1');
    }

    public function __toString()
    {
        return $this->getCompact();
    }

    public function getCompact(): string
    {
        return inet_ntop(inet_pton($this->address));
    }

    public function getChunks(): array
    {
        return array_map(
            static function ($c) {
                return ltrim($c, '0') ?: '0';
            },
            explode(':', $this->getExpanded())
        );
    }

    public function getExpanded(): string
    {
        return $this->address;
    }

    public function isLoopback(): bool
    {
        return $this->matches(new Subnet('::1/128'));
    }

    public function matches(ExpressionInterface $expression): bool
    {
        return $expression->matches($this);
    }

    public function isPrivate(): bool
    {
        return $this->matches(new Subnet('fc00::/7'));
    }
}
