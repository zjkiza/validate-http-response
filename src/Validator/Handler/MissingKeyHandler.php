<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Handler;

use ZJKiza\HttpResponseValidator\Contract\StructureValidationHandlerInterface;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

final class MissingKeyHandler implements StructureValidationHandlerInterface
{
    public function support(int|string $key, mixed $expected, array $data, string $currentPath, ValidationContext $context): bool
    {
        return !\array_key_exists($key, $data);
    }

    public function handle(int|string $key, mixed $expected, array $data, string $currentPath, ErrorCollector $errorCollector, ValidationContext $context): bool
    {
        $errorCollector->add(\sprintf('Missing required key "%s.%s"', $currentPath, $key));

        return true;
    }
}
