<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Contract;

use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

interface StructureValidationHandlerInterface
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function support(int|string $key, mixed $expected, array $data, string $currentPath, ValidationContext $context): bool;

    /**
     * Return true if handled (so chain should continue), false to break (e.g. in case of fatal error).
     *
     * @param array<array-key, mixed> $data
     */
    public function handle(int|string $key, mixed $expected, array $data, string $currentPath, ErrorCollector $errorCollector, ValidationContext $context): bool;
}
