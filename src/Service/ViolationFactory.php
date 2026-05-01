<?php

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ViolationFactory
{
    public static function single(string $path, string $message, ?string $invalidValue = null): ConstraintViolationList
    {
        return new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, $path, $invalidValue),
        ]);
    }
}
