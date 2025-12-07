<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Handler;

use ZJKiza\HttpResponseValidator\Contract\StructureValidationHandlerInterface;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

final class WildcardHandler implements StructureValidationHandlerInterface
{
    public function support(int|string $key, mixed $expected, array $data, string $currentPath, ValidationContext $context): bool
    {
        return '*' === $key;
    }

    public function handle(int|string $key, mixed $expected, array $data, string $currentPath, ErrorCollector $errorCollector, ValidationContext $context): bool
    {
        foreach ($data as $item) {
            // recursion is in the realm of orchestration/delegatora
            /**
             * @var array<array-key, mixed> $expected
             * @var array<array-key, mixed> $item
             */
            $context->validate($expected, $item, $currentPath.'.*');
        }

        return false; // wildcard ends this branch
    }
}
