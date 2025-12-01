<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\HttpLogger;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use ZJKiza\HttpResponseValidator\Exception\RuntimeException;

use function ZJKiza\HttpResponseValidator\addIdInMessage;

final class HttpResponseLogger
{
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;

    /**
     * @var array<string, string>
     */
    private array $sensitiveKeys = [
        'password' => 'password',
        'token' => 'token',
        'apiKey' => 'apiKey',
    ];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function validateResponse(ResponseInterface $response, int $expected = Response::HTTP_OK): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode === $expected) {
            return;
        }

        $context = \sprintf('[HttpRequestLogger ERROR CODE] Unexpected status code %d expected %d', $statusCode, $expected);
        $messageId = addIdInMessage();

        $this->logger->error(
            addIdInMessage($context),
            ['http_request_failed' => $this->httpRequestFailed($response)]
        );

        $this->throwHttpException(
            $statusCode,
            \sprintf('%s %s', $messageId, $context)
        );
    }

    /**
     * @param string[] $addKeys
     */
    public function addSensitiveKeys(array $addKeys): void
    {
        foreach ($addKeys as $key) {
            if (isset($this->sensitiveKeys[$key])) {
                continue;
            }

            $this->sensitiveKeys[$key] = $key;
        }
    }

    /**
     * @return array{
     *      method: string,
     *      url: string,
     *      options: mixed,
     *      code: int,
     *      body: string
     * }
     */
    private function httpRequestFailed(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $info */
        $info = $response->getInfo();

        return [
            'method' => isset($info['http_method']) && \is_string($info['http_method'])
                ? $info['http_method']
                : 'UNKNOWN',
            'url' => isset($info['url']) && \is_string($info['url'])
                ? $info['url']
                : 'UNKNOWN',
            'options' => $response->getRequestOptions(),
            'code' => isset($info['http_code']) && \is_int($info['http_code'])
                ? $info['http_code']
                : $response->getStatusCode(),
            'body' => $this->formatJsonContent($response->getContent(false)),
        ];
    }

    private function throwHttpException(int $statusCode, string $message): void
    {
        match ($statusCode) {
            self::HTTP_BAD_REQUEST => throw new BadRequestHttpException(addIdInMessage()),
            self::HTTP_UNAUTHORIZED => throw new UnauthorizedHttpException('bearer', addIdInMessage('Invalid credentials')),
            self::HTTP_FORBIDDEN => throw new AccessDeniedHttpException(addIdInMessage()),
            self::HTTP_NOT_FOUND => throw new NotFoundHttpException(addIdInMessage()),
            default => throw new RuntimeException($message, $statusCode),
        };
    }

    private function formatJsonContent(string $content): string
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $encoded = \json_encode($this->maskSensitiveData($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return \is_string($encoded) ? $encoded : $content;
        } catch (\Throwable) {
            return $content;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (isset($this->sensitiveKeys[$key])) {
                $data[$key] = '***';
            } elseif (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
