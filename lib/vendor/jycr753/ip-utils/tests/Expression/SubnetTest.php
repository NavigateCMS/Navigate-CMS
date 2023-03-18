<?php
/*
 * Copyright (c) 2013, Christoph Mewes, http://www.xrstf.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace Tests\Expression;

use IpUtils\Address\IPv4;
use IpUtils\Address\IPv6;
use IpUtils\Expression\Subnet;
use PHPUnit\Framework\TestCase;

class SubnetTest extends TestCase
{
    /**
     * @dataProvider  addressProvider
     */
    public function testMatches($subnet, $address, $expected)
    {
        $subnet = new Subnet($subnet);

        $this->assertSame($expected, $address->matches($subnet));
        $this->assertSame($expected, $subnet->matches($address));
    }

    public function addressProvider()
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
     * @dataProvider       invalidProvider
     *  @expectedException  \IpUtils\Exception\InvalidExpressionException
     */
    public function testInvalidFormats($subnet)
    {
        $subnet = new Subnet($subnet);
    }

    public function invalidProvider()
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
     * @expectedException  \LogicException
     */
    public function testMixedVersions($subnet, $address)
    {
        $subnet = new Subnet($subnet);
        $subnet->matches($address);
    }

    public function mixedProvider()
    {
        return [
            ['::/128', new IPv4('127.0.0.1')],
            ['1.0.0.0/8', new IPv6('::1')],
        ];
    }
}
