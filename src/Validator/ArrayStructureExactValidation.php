<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator;

use ZJKiza\HttpResponseValidator\Contract\StructureValidationHandlerInterface;
use ZJKiza\HttpResponseValidator\Contract\ValidationStrategy;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\TypeChecker;
use ZJKiza\HttpResponseValidator\Validator\Handler\WildcardHandler;
use ZJKiza\HttpResponseValidator\Validator\Handler\MissingKeyHandler;
use ZJKiza\HttpResponseValidator\Validator\Handler\NullCheckHandler;
use ZJKiza\HttpResponseValidator\Validator\Handler\TypeCheckHandler;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

final class ArrayStructureExactValidation implements ValidationStrategy
{
    /**
     * @var StructureValidationHandlerInterface[]
     */
    private array $handlers;

    public function __construct(
        private ErrorCollector $errorCollector,
        private readonly bool $ignoreNulls = false,
        private readonly bool $checkTypes = false,
    ) {
        $this->handlers = [
            new WildcardHandler(),
            new MissingKeyHandler(),
            new NullCheckHandler(),
            new TypeCheckHandler(),
        ];
    }

    /**
     * @param array<array-key, mixed> $structure
     * @param array<array-key, mixed> $data
     */
    public function validate(array $structure, array $data, string $currentPath = 'root'): void
    {
        // Normalizacija
        $normalized = [];
        foreach ($structure as $key => $value) {
            if (\is_int($key)) {
                /** @var string $value */
                $normalized[$value] = true;
                continue;
            }
            $normalized[$key] = $value;
        }
        $structure = $normalized;

        $hasWildcard = \array_key_exists('*', $structure);
        if (!$hasWildcard) {
            // Strict/exact check for extra/missing keys
            $expectedKeys = \array_keys($structure);
            $actualKeys = \array_keys($data);
            \sort($expectedKeys);
            \sort($actualKeys);
            if ($expectedKeys !== $actualKeys) {
                $this->errorCollector->add(
                    \sprintf(
                        'Exact key mismatch at "%s". Expected: %s, got: %s',
                        $currentPath,
                        $this->safeJson($expectedKeys),
                        $this->safeJson($actualKeys)
                    )
                );
            }
        }

        // rekurzivno per key
        foreach ($structure as $key => $expected) {

            $context = new ValidationContext(
                ignoreNulls: $this->ignoreNulls,
                checkTypes: $this->checkTypes,
                typeChecker: new TypeChecker(),
                validateCallback: fn (array $structure, array $data, string $path) => $this->validate($structure, $data, $path),
            );

            foreach ($this->handlers as $handler) {
                if ($handler->support($key, $expected, $data, $currentPath, $context)) {
                    $handled = $handler->handle($key, $expected, $data, $currentPath, $this->errorCollector, $context);
                    if (false === $handled) {
                        break;
                    }
                }
            }
            // wildcard: svi children se obrađuju isključivo u handleru i dalje se ne spušta!
            if (!$hasWildcard && \array_key_exists($key, $data) && \is_array($expected)) {

                /** @var array<array-key, mixed> $dataArray */
                $dataArray = $data[$key];

                /** @var array<array-key, mixed> $expected */
                $this->validate($expected, $dataArray, $currentPath.'.'.$key);
            }
        }
    }

    public function getErrorCollector(): ErrorCollector
    {
        return $this->errorCollector;
    }

    private function safeJson(mixed $value): string
    {
        $json = \json_encode($value);

        return false === $json ? 'null' : $json;
    }
}
