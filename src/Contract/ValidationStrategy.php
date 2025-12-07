<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Contract;

interface ValidationStrategy
{
    /**
     * @param array<array-key, mixed> $structure
     * @param array<array-key, mixed> $data
     */
    public function validate(array $structure, array $data, string $currentPath): void;
}
