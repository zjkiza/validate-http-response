<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Helper;

final readonly class ValidationContext
{
    public function __construct(
        public bool $ignoreNulls,
        public bool $checkTypes,
        public TypeChecker $typeChecker,
        private \Closure $validateCallback,
    ) {
    }

    /**
     * @param array<array-key, mixed> $structure
     * @param array<array-key, mixed> $data
     */
    public function validate(array $structure, array $data, string $path): void
    {
        ($this->validateCallback)($structure, $data, $path);
    }
}
