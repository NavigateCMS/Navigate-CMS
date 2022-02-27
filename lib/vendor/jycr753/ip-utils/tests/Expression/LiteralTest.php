<?php

declare(strict_types=1);

namespace Tests\Expression;

use IpUtils\Address\IPv4;
use IpUtils\Address\IPv6;
use IpUtils\Expression\Literal;
use PHPUnit\Framework\TestCase;

final class LiteralTest extends TestCase
{
    /**
     * @dataProvider  addressProvider
     */
    public function testMatches($literal, $address, $expected)
    {
        $literal = new Literal($literal);

        $this->assertEquals($expected, $address->matches($literal));
        $this->assertEquals($expected, $literal->matches($address));
    }

    public function addressProvider()
    {
        return [
            ['0.0.0.0', new IPv4('0.0.0.0'), true],
            ['12.0.0.0', new IPv4('12.0.0.0'), true],
            ['12.0.0.255', new IPv4('12.0.0.255'), true],
            ['255.254.255.255', new IPv4('254.255.255.255'), false],
            ['12.0.0.0', new IPv4('1.0.0.0'), false],
            ['12.0.0.0', new IPv4('1.2.0.0'), false],
            ['::1', new IPv6('::1'), true],
            ['::1', new IPv6('0:0:0:0:0:0:0:1'), true],
            ['0:0:0:0:0:0:0:1', new IPv6('::1'), true],
            ['1:0:0:0:0:0:0:1', new IPv6('::1'), false],
        ];
    }
}
