<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Handler;

use ZJKiza\HttpResponseValidator\Contract\StructureValidationHandlerInterface;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

final class NullCheckHandler implements StructureValidationHandlerInterface
{
    public function support(int|string $key, mixed $expected, array $data, string $currentPath, ValidationContext $context): bool
    {
        return \array_key_exists($key, $data) && null === $data[$key] && !$context->ignoreNulls;
    }

    public function handle(int|string $key, mixed $expected, array $data, string $currentPath, ErrorCollector $errorCollector, ValidationContext $context): bool
    {
        $errorCollector->add(\sprintf('Key "%s.%s" cannot be null', $currentPath, $key));

        return true;
    }
}
