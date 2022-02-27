<?php

declare(strict_types=1);

namespace Tests\Expression;

use IpUtils\Address\IPv4;
use IpUtils\Address\IPv6;
use IpUtils\Exception\InvalidExpressionException;
use IpUtils\Expression\Subnet;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SubnetTest extends TestCase
{
    /**
     * @dataProvider  addressProvider
     */
    public function testMatches($subnet, $address, $expected): void
    {
        $subnet = new Subnet($subnet);

        $this->assertSame($expected, $address->matches($subnet));
        $this->assertSame($expected, $subnet->matches($address));
    }

    public function addressProvider(): array
    {
        return [
            ['1.0.0.0/1', new IPv4('1.0.0.0'), true],
            ['1.0.0.0/8', new IPv4('1.0.0.0'), true],
            ['1.0.0.0/8', new IPv4('1.1.0.0'), true],
            ['1.0.0.0/8', new IPv4('1.255.255.255'), true],
            ['1.0.0.0/8', new IPv4('2.0.0.0'), false],
            ['2.0.0.0/7', new IPv4('2.0.0.0'), true],
            ['2.0.0.0/7', new IPv4('2.0.255.0'), true],
            ['2.0.0.0/7', new IPv4('3.0.0.0'), true],
            ['1.0.0.0/32', new IPv4('1.0.0.0'), true],
            ['1.0.0.0/32', new IPv4('1.0.0.1'), false],
            ['1.0.0.0/32', new IPv4('2.0.0.0'), false],

            ['2a01:198:603:0::/65', new IPv6('2a01:198:603:0:396e:4789:8e99:890f'), true],
            ['2a01:198:603:0::/65', new IPv6('2a00:198:603:0:396e:4789:8e99:890f'), false],
            ['2001::/16', new IPv6('2000::1'), false],
        ];
    }

    /**
     * @dataProvider  invalidProvider
     */
    public function testInvalidFormats($subnet): void
    {
        $this->expectException(InvalidExpressionException::class);
        new Subnet($subnet);
    }

    public function invalidProvider(): array
    {
        return [
            ['1.0.0.0/'],
            ['1.0.0.0/null'],
            ['1.0.0.0/0'],
            ['1.0.0.0/-2'],
            ['1.0.0.0/33'],
            ['1.0.0.0/1.2.3.4'],
            ['1.0.0.0/1.'],
            ['/1'],
            ['foo/1'],
            ['1.2.500.1/1'],
            ['2001:dH8::1:2/1'],
            ['::1/-1'],
            ['::1/0'],
            ['::1/200'],
            ['1.2.*.3/1'],
        ];
    }

    /**
     * @dataProvider       mixedProvider
     */
    public function testMixedVersions($subnet, $address): void
    {
        $this->expectException(LogicException::class);
        $subnet = new Subnet($subnet);
        $subnet->matches($address);
    }

    public function mixedProvider(): array
    {
        return [
            ['::/128', new IPv4('127.0.0.1')],
            ['1.0.0.0/8', new IPv6('::1')],
        ];
    }
}
