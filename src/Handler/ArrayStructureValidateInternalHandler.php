<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Handler;

use ZJKiza\HttpResponseValidator\Contract\HandlerInterface;
use ZJKiza\HttpResponseValidator\Exception\InvalidArgumentException;
use ZJKiza\HttpResponseValidator\Exception\InvalidPropertyValueException;
use ZJKiza\HttpResponseValidator\Handler\Factory\TagIndexMethod;
use ZJKiza\HttpResponseValidator\Monad\Result;
use ZJKiza\HttpResponseValidator\Validator\ArrayStructureInternalValidation;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;

use function ZJKiza\HttpResponseValidator\addIdInMessage;

/**
 * @implements HandlerInterface<array<string, mixed>, array<string, mixed>>
 */
final class ArrayStructureValidateInternalHandler extends AbstractHandler implements HandlerInterface
{
    use TagIndexMethod;

    /** @var array<string, mixed> */
    private array $structure = [];

    private bool $ignoreNulls = false;
    private bool $checkTypes = false;

    /**
     * @param array<string, mixed> $value
     *
     * @return Result<array<string, mixed>>
     */
    public function __invoke(mixed $value): Result
    {
        if (false === (bool) $this->structure) {
            throw new InvalidPropertyValueException('Property structure is not set in ArrayStructureValidateInternalHandler.');
        }

        $structureValidator = new ArrayStructureInternalValidation(
            new ErrorCollector(),
            $this->ignoreNulls,
            $this->checkTypes
        );

        $structureValidator->validate($this->structure, $value);

        if (false === $structureValidator->getErrorCollector()->hasErrors()) {
            return Result::success($value);
        }

        $message = \sprintf(
            '%s [ArrayStructureValidateInternalHandler] Errors: %s.',
            addIdInMessage(),
            \implode(', ', $structureValidator->getErrorCollector()->all())
        );

        /** @var Result<array<string, mixed>> */
        return $this->fail($message, InvalidArgumentException::class);
    }

    /**
     * @param array<string, mixed> $structure
     */
    public function setKeys(array $structure): self
    {
        $this->structure = $structure;

        return $this;
    }

    public function setIgnoreNulls(bool $ignoreNulls = false): self
    {
        $this->ignoreNulls = $ignoreNulls;

        return $this;
    }

    public function setCheckTypes(bool $checkTypes = false): self
    {
        $this->checkTypes = $checkTypes;

        return $this;
    }
}
