<?php

declare(strict_types=1);

namespace IpUtils\Expression;

use IpUtils\Address\AddressInterface;
use UnexpectedValueException;

class Pattern implements ExpressionInterface
{
    protected string $expression;

    public function __construct(string $expression)
    {
        $expression = strtolower(trim($expression));
        $expression = preg_replace('/\*+/', '*', $expression);

        $this->expression = $expression;
    }

    /**
     * check whether the expression matches an address
     */
    public function matches(AddressInterface $address): bool
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
                // we just care for the numerical value (and it's also okay to interpret
                // IPv4 chunks as hex values, as long as we interpret both as hex).
                if (hexdec($addrChunk) !== hexdec($exprChunk)) {
                    return false;
                }
            } else {
                $exprChunk = str_replace('*', '[0-9a-f]+?', $exprChunk);

                if (! preg_match('/^' . $exprChunk . '$/', $addrChunk)) {
                    return false;
                }
            }
        }

        return true;
    }
}
