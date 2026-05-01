<?php

namespace App\Repository\Concerns;

trait HandlesMalformedUuid
{
    /**
     * Doctrine throws \InvalidArgumentException when a parameter typed as 'uuid'
     * receives a non-UUID string (e.g. an attacker-supplied id from a URL).
     * Treating that as "not found" lets controllers return 404 cleanly.
     *
     * @template T
     * @param callable():?T $query
     * @return T|null
     */
    private function nullOnInvalidUuid(callable $query): mixed
    {
        try {
            return $query();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
