<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Handler;

use ZJKiza\HttpResponseValidator\Contract\StructureValidationHandlerInterface;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

final class NestedStructureHandler implements StructureValidationHandlerInterface
{
    public function support(int|string $key, mixed $expected, array $data, string $currentPath, ValidationContext $context): bool
    {
        return \is_array($expected);
    }

    public function handle(int|string $key, mixed $expected, array $data, string $currentPath, ErrorCollector $errorCollector, ValidationContext $context): bool
    {
        $fullKey = \sprintf('%s.%s', $currentPath, $key);

        // here $key is the field name that should contain an array/object
        if (!\array_key_exists($key, $data)) {
            $errorCollector->add(\sprintf('Missing required key: "%s"', $fullKey));

            return false;
        }

        $childValue = $data[$key];

        if (null === $childValue && !$context->ignoreNulls) {
            $errorCollector->add(\sprintf('Key "%s" cannot be null', $fullKey));

            return false;
        }

        if (!\is_array($childValue)) {
            $errorCollector->add(\sprintf('Key "%s" must be array, %s given', $fullKey, \gettype($childValue)));

            return false;
        }

        /**
         * @var array<array-key, mixed> $expected
         * @var array<array-key, mixed> $childValue
         */
        $context->validate($expected, $childValue, $fullKey);

        return false;

    }
}
