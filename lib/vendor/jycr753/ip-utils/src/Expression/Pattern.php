<?php
/*
 * Copyright (c) 2013, Christoph Mewes, http://www.xrstf.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace IpUtils\Expression;

use IpUtils\Address\AddressInterface;
use UnexpectedValueException;

class Pattern implements ExpressionInterface
{
    protected $expression;

    public function __construct($expression)
    {
        $expression = strtolower(trim($expression));
        $expression = preg_replace('/\*+/', '*', $expression);

        $this->expression = $expression;
    }

    /**
     * check whether the expression matches an address
     *
     * @param  AddressInterface  $address
     *
     * @return boolean
     */
    public function matches(AddressInterface $address)
    {
        $addrChunks = $address->getChunks();
        $exprChunks = preg_split('/[.:]/', $this->expression);

        if (count($exprChunks) !== count($addrChunks)) {
            throw new UnexpectedValueException('Address and expression do not contain the same amount of chunks. Did you mix IPv4 and IPv6?');
        }

        foreach ($exprChunks as $idx => $exprChunk) {
            $addrChunk = $addrChunks[$idx];

            if (strpos($exprChunk, '*') === false) {
                // It's okay if the expression contains '.0.' and the IP contains '.000.',
                // we just care for the numerical value (and it's also okay to interprete
                // IPv4 chunks as hex values, as long as we interprete both as hex).
                if (hexdec($addrChunk) !== hexdec($exprChunk)) {
                    return false;
                }
            } else {
                $exprChunk = str_replace('*', '[0-9a-f]+?', $exprChunk);

                if (! preg_match('/^'.$exprChunk.'$/', $addrChunk)) {
                    return false;
                }
            }
        }

        return true;
    }
}
