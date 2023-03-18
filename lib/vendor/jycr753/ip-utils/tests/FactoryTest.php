<?php
/*
 * Copyright (c) 2013, Christoph Mewes, http://www.xrstf.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace Tests;

use Doctrine\Instantiator\Exception\UnexpectedValueException;
use IpUtils\Factory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    /**
     * @dataProvider  validAddressProvider
     */
    public function testGetAddress($address, $expected)
    {
        $address = Factory::getAddress($address);
        $this->assertInstanceOf($expected, $address);
    }

    public function validAddressProvider()
    {
        $v4 = 'IpUtils\Address\IPv4';
        $v6 = 'IpUtils\Address\IPv6';

        return [
            ['0.0.0.0', $v4],
            ['127.0.0.1', $v4],
            ['::1', $v6],
            ['fe80::', $v6],
        ];
    }

    /**
     * @dataProvider invalidAddressProvider
     * @expectedException UnexpectedValueException
     */
    public function testGetInvalidAddress($address)
    {
        Factory::getAddress($address);
    }

    public function invalidAddressProvider()
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
    public function testGetExpression($expr, $expected)
    {
        $expr = Factory::getExpression($expr);
        $this->assertInstanceOf($expected, $expr);
    }

    public function expressionProvider()
    {
        $literal = 'IpUtils\Expression\Literal';
        $pattern = 'IpUtils\Expression\Pattern';
        $subnet = 'IpUtils\Expression\Subnet';

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
