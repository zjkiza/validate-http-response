<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Monad;

use ZJKiza\HttpResponseValidator\Contract\ResultInterface;

/**
 * @template T
 */
abstract class Result implements ResultInterface
{
    /**
     * @param T $value
     */
    public function __construct(protected mixed $value)
    {
    }

    /**
     * @template R
     *
     * @param callable(T): Result<R> $fn
     *
     * @return Result<R>
     */
    abstract public function bind(callable $fn): Result;

    /**
     * @return T
     */
    abstract public function getOrThrow(): mixed;

    /**
     * @template V
     *
     * @param V $value
     *
     * @return Result<V>
     */
    public static function success(mixed $value): self
    {
        return new Success($value);
    }

    /**
     * @template E
     *
     * @param E $error
     *
     * @return Result<E>
     */
    public static function failure(mixed $error, ?\Throwable $exception = null): self
    {
        return new Failure($error, $exception);
    }

    /**
     * @return T
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
