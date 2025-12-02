<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Handler;

use Psr\Log\LoggerInterface;
use ZJKiza\HttpResponseValidator\Monad\Result;

use function ZJKiza\HttpResponseValidator\addIdInMessage;

/**
 * @psalm-suppress LessSpecificReturnStatement
 * @psalm-suppress MoreSpecificReturnType
 */
abstract class AbstractHandler
{
    private const NAMESPACE = 'ZJKiza\\HttpResponseValidator\\';

    public function __construct(protected LoggerInterface $logger)
    {
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     *
     * @return Result<non-falsy-string>
     */
    protected function fail(string $message, string $exceptionClass = \RuntimeException::class, int $errorCode = 400): Result
    {
        /** @var list<array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $backtrace */
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $runPlace = $this->getBacktrace($backtrace);

        /** @var string $overrideMessage */
        $overrideMessage = \preg_replace(
            '/(:\s*)(\[[^\]]+\])/',
            '$1'.$runPlace.' $2',
            addIdInMessage($message)
        );

        $loggerMessage = \sprintf(
            '[%s] %s',
            static::class,
            $overrideMessage
        );

        $this->logger->error($loggerMessage, ['trace' => $backtrace]);

        /** @var \Throwable $exception */
        $exception = new $exceptionClass($overrideMessage, $errorCode);

        /** @psalm-suppress LessSpecificReturnStatement */
        return Result::failure($loggerMessage, $exception);
    }

    /**
     * @param list<array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $backtrace
     */
    private function getBacktrace(array $backtrace): string
    {
        foreach ($backtrace as $item) {
            if (isset($item['class']) && \str_starts_with($item['class'], self::NAMESPACE)) {
                continue;
            }

            if (isset($item['class'])) {
                return \sprintf('%s::%s ->', $item['class'], $item['function']);
            }

            return \sprintf('%s -> ', $item['function']);
        }

        return '';
    }
}
