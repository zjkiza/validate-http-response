<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Helper;

final class TypeChecker
{
    public static function isValid(string $expectedType, mixed $value): bool
    {
        return match ($expectedType) {
            'string' => \is_string($value),
            'int' => \is_int($value),
            'float' => \is_float($value),
            'bool' => \is_bool($value),
            'array' => \is_array($value),
            'object' => \is_object($value),
            'null' => \is_null($value),
            'mixed' => true,
            default => true, // unknown type = don't enforce
        };
    }
}
