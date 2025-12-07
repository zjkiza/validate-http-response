<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator;

use ZJKiza\HttpResponseValidator\Contract\StructureValidationHandlerInterface;
use ZJKiza\HttpResponseValidator\Contract\ValidationStrategy;
use ZJKiza\HttpResponseValidator\Exception\InvalidArgumentException;
use ZJKiza\HttpResponseValidator\Validator\Handler\NestedStructureHandler;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;
use ZJKiza\HttpResponseValidator\Validator\Helper\TypeChecker;
use ZJKiza\HttpResponseValidator\Validator\Handler\WildcardHandler;
use ZJKiza\HttpResponseValidator\Validator\Handler\MissingKeyHandler;
use ZJKiza\HttpResponseValidator\Validator\Handler\NullCheckHandler;
use ZJKiza\HttpResponseValidator\Validator\Handler\TypeCheckHandler;
use ZJKiza\HttpResponseValidator\Validator\Helper\ValidationContext;

final class ArrayStructureInternalValidation implements ValidationStrategy
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
            new NestedStructureHandler(),
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
        foreach ($structure as $key => $expected) {

            $context = new ValidationContext(
                ignoreNulls: $this->ignoreNulls,
                checkTypes:  $this->checkTypes,
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

                if ($handler instanceof NestedStructureHandler) {
                    [$key, $expected] = $this->transformLeaf($key, $expected);
                }
            }
            // Recursive descent for nested structures
            if (\array_key_exists($key, $data) && \is_array($expected)) {

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

    /**
     * @return array{string, string|null}
     */
    private function transformLeaf(int|string $key, mixed $expected): array
    {
        // Format A: ['a', 'b']
        if (\is_int($key)) {
            $stringValue = \is_scalar($expected) || $expected instanceof \Stringable
                ? (string) $expected
                : throw new InvalidArgumentException('Expected a scalar or Stringable value.');

            return [$stringValue, null];
        }

        // Format B: ['name' => 'string']
        return [$key, \is_string($expected) ? $expected : null];
    }
}
