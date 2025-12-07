<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Validator\Helper;

final class ErrorCollector
{
    /**
     * @var string[]
     */
    private array $errors = [];

    public function add(string $message): void
    {
        if (\in_array($message, $this->errors, true)) {
            return;
        }

        $this->errors[] = $message;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->errors;
    }
}
