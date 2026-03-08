<?php

declare(strict_types=1);

namespace Tests;

use IpUtils\Factory;
use PHPUnit\Framework\TestCase;
use IpUtils\Address\IPv4;
use IpUtils\Address\IPv6;
use IpUtils\Expression\Literal;
use IpUtils\Expression\Pattern;
use IpUtils\Expression\Subnet;
use UnexpectedValueException;

final class FactoryTest extends TestCase
{
    /**
     * @dataProvider  validAddressProvider
     */
    public function testGetAddress($address, $expected): void
    {
        $address = Factory::getAddress($address);
        $this->assertInstanceOf($expected, $address);
    }

    public function validAddressProvider(): array
    {
        $v4 = IPv4::class;
        $v6 = IPv6::class;

        return [
            ['0.0.0.0', $v4],
            ['127.0.0.1', $v4],
            ['::1', $v6],
            ['fe80::', $v6],
        ];
    }

    /**
     * @dataProvider invalidAddressProvider
     */
    public function testGetInvalidAddress(string $address): void
    {
        $this->expectException(UnexpectedValueException::class);
        Factory::getAddress($address);
    }

    public function invalidAddressProvider(): array
    {
        return [
            ['0.0.0.300'],
            ['abc'],
            [':hallo:welt::'],
        ];
    }

    /**
     * @dataProvider  expressionProvider
     */
    public function testGetExpression($expr, $expected): void
    {
        $expr = Factory::getExpression($expr);
        $this->assertInstanceOf($expected, $expr);
    }

    public function expressionProvider(): array
    {
        $literal = Literal::class;
        $pattern = Pattern::class;
        $subnet = Subnet::class;

        return [
            ['0.0.0.0', $literal],
            ['::1', $literal],
            ['fe80::1', $literal],

            ['0.0.0.0/8', $subnet],
            ['::1/8', $subnet],
            ['fe80::/128', $subnet],

            ['fe*::', $pattern],
            ['::1:*', $pattern],
            ['127.*.*.0', $pattern],
        ];
    }
}
