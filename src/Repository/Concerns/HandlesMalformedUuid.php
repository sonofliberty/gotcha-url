<?php

namespace App\Repository\Concerns;

use Doctrine\DBAL\Types\ConversionException;

trait HandlesMalformedUuid
{
    /**
     * Doctrine throws when a parameter typed as 'uuid' receives a non-UUID
     * string (e.g. an attacker-supplied id from a URL). Older releases raised
     * \InvalidArgumentException; DBAL 3+ raises ConversionException. Treating
     * either as "not found" lets controllers return 404 cleanly.
     *
     * @template T
     * @param callable():?T $query
     * @return T|null
     */
    private function nullOnInvalidUuid(callable $query): mixed
    {
        try {
            return $query();
        } catch (\InvalidArgumentException | ConversionException) {
            return null;
        }
    }
}
