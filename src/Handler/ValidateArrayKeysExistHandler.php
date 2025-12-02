<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Handler;

use ZJKiza\HttpResponseValidator\Contract\HandlerInterface;
use ZJKiza\HttpResponseValidator\Exception\InvalidArgumentException;
use ZJKiza\HttpResponseValidator\Exception\InvalidPropertyValueException;
use ZJKiza\HttpResponseValidator\Handler\Factory\TagIndexMethod;
use ZJKiza\HttpResponseValidator\Monad\Result;

use function ZJKiza\HttpResponseValidator\addIdInMessage;

/**
 * @implements HandlerInterface<array<string, mixed>, array<string, mixed>>
 */
final class ValidateArrayKeysExistHandler extends AbstractHandler implements HandlerInterface
{
    use TagIndexMethod;

    /** @var string[] */
    private array $keys = [];

    /** @var string[] */
    private array $keysMissing = [];

    /**
     * @param array<string, mixed> $value
     *
     * @return Result<array<string, mixed>>
     */
    public function __invoke(mixed $value): Result
    {
        if (false === (bool) $this->keys) {
            throw new InvalidPropertyValueException('Property keys is not set in ValidateArrayKeysExistHandler.');
        }

        foreach ($this->keys as $key) {
            if (\array_key_exists($key, $value)) {
                continue;
            }

            $this->keysMissing[] = $key;
        }

        if (false === (bool) $this->keysMissing) {
            return Result::success($value);
        }

        $message = \sprintf(
            '%s [ValidateArrayKeysExistHandler] There is no required fields "%s" in the array (%s).',
            addIdInMessage(),
            \implode(', ', $this->keysMissing),
            \implode(', ', \array_keys($value))
        );

        /** @var Result<array<string, mixed>> */
        return $this->fail($message, InvalidArgumentException::class);
    }

    /**
     * @param string[] $keys
     */
    public function setKeys(array $keys): self
    {
        $this->keys = $keys;

        return $this;
    }
}
