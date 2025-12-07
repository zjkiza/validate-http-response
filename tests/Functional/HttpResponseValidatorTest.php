<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\Functional;

use PHPUnit\Framework\Constraint\RegularExpression;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use ZJKiza\HttpResponseValidator\Contract\HandlerFactoryInterface;
use ZJKiza\HttpResponseValidator\Exception\InvalidArgumentException;
use ZJKiza\HttpResponseValidator\Exception\RuntimeException;
use ZJKiza\HttpResponseValidator\Handler\ArrayStructureValidateExactHandler;
use ZJKiza\HttpResponseValidator\Handler\ArrayStructureValidateInternalHandler;
use ZJKiza\HttpResponseValidator\Handler\ExtractResponseJsonHandler;
use ZJKiza\HttpResponseValidator\Handler\HttpResponseLoggerHandler;
use ZJKiza\HttpResponseValidator\Monad\Result;
use ZJKiza\HttpResponseValidator\Tests\PhpUnitTool\PhpUnitTool;
use ZJKiza\HttpResponseValidator\Tests\Resources\KernelTestCase;
use ZJKiza\HttpResponseValidator\Tests\Resources\Logger\TestLogger;

final class HttpResponseValidatorTest extends KernelTestCase
{
    private HandlerFactoryInterface $handlerFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handlerFactory = $this->getContainer()->get(HandlerFactoryInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->handlerFactory,
        );
    }

    public function testSuccessful(): void
    {
        $data = [
            'name' => 'Foo',
            'email' => 'foo@test.com',
            'password' => 'password123',
            'tokenTTL' => 'password123',
        ];

        $mockResponse = new MockResponse(
            \json_encode($data),
            [
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
                'http_code' => 201,
            ]
        );

        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');
        \restore_exception_handler();

        $result = Result::success($response)
            ->bind($this->handlerFactory->create(HttpResponseLoggerHandler::class)->setExpectedStatus(201)->addSensitiveKeys(['tokenTTL']))
            ->bind($this->handlerFactory->create(ExtractResponseJsonHandler::class)->setAssociative(true))
            ->bind($this->handlerFactory->create(ArrayStructureValidateInternalHandler::class)->setKeys(['name', 'email', 'password', 'tokenTTL']))
            ->getOrThrow();

        $expected = [
            'name' => 'Foo',
            'email' => 'foo@test.com',
            'password' => 'password123',
            'tokenTTL' => 'password123',
        ];

        $this->assertSame($expected, $result);
    }

    public function testExpectExceptionForUnexpectedStatusCode(): void
    {
        $data = [
            'name' => 'Foo',
            'email' => 'foo@test.com',
            'password' => 'password123',
            'tokenTTL' => 'password123',
        ];

        $mockResponse = new MockResponse(
            \json_encode($data),
            [
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
                'http_code' => 200,
            ]
        );

        /** @var TestLogger $logger */
        $logger = $this->getContainer()->get(TestLogger::class);

        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');
        \restore_exception_handler();

        $result = Result::success($response)
            ->bind(
                $this->handlerFactory
                ->create(HttpResponseLoggerHandler::class)
                    ->setExpectedStatus(201)
                    ->addSensitiveKeys(
                        ['tokenTTL']
                    )
            );

        $this->expectException(RuntimeException::class);

        $expected = [
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/Message ID=[a-f0-9]+ : .*Unexpected status code 200 expected 201/';
                    self::assertThat($message, new RegularExpression($pattern));
                },
                'context' => [
                    'http_request_failed' => [
                        'method' => 'GET',
                        'url' => 'https://example.com/',
                        'body' => function ($value) {

                            $expected = [
                                'name' => 'Foo',
                                'email' => 'foo@test.com',
                                'password' => '***',
                                'tokenTTL' => '***',
                            ];

                            $this->assertSame($expected, \json_decode($value, true));
                        },
                    ],
                ],
            ],
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/Message ID=[a-f0-9]+ : .*\\[HttpRequestLogger ERROR CODE\\] Unexpected status code 200 expected 201/';
                    self::assertThat($message, new RegularExpression($pattern));
                },
                'context' => [
                    'trace' => function ($trace) {
                        $this->assertIsArray($trace);
                    },
                ],
            ],
        ];

        PhpUnitTool::assertArrayRecords($logger->records, $expected);

        $result->getOrThrow();
    }

    public function testExpectExceptionForInvalidJsonFormat(): void
    {
        /** @var TestLogger $logger */
        $logger = $this->getContainer()->get(TestLogger::class);

        $jsonBody = '{ "name":"Foo", "email"=>"foo@examle.com" }';

        $mockResponse = new MockResponse(
            $jsonBody,
            [
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
                'http_code' => 200,
            ]
        );

        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');
        \restore_exception_handler();

        $result = Result::success($response)
            ->bind($this->handlerFactory->create(ExtractResponseJsonHandler::class)->setAssociative());

        $this->expectException(\Throwable::class);

        $expected = [
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/^[ZJKiza\\\\HttpResponseValidator\\\\Handler\\\\ExtractResponseJsonHandler\] Message ID=[a-f0-9]+ : PHPUnit\\\\Framework\\\\TestCase::runTest -> \[ExtractResponseJsonHandler\] Syntax error$/';
                    self::assertThat($message, new RegularExpression($pattern));
                },
                'context' => function ($trace) {
                    $this->assertIsArray($trace);
                },

            ],
        ];

        PhpUnitTool::assertArrayRecords($logger->records, $expected);

        $result->getOrThrow();
    }

    public function testExpectExceptionForValidateArrayKeysWhereKeyNotExist(): void
    {
        $data = [
            'name' => 'Foo',
            'email' => 'foo@test.com',
        ];

        /** @var TestLogger $logger */
        $logger = $this->getContainer()->get(TestLogger::class);

        $result = Result::success($data)
            ->bind($this->handlerFactory->create(ArrayStructureValidateInternalHandler::class)->setKeys(['name', 'lorem']));
        \restore_exception_handler();


        $this->expectException(InvalidArgumentException::class);

        $expected = [
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/^\[ZJKiza\\\\HttpResponseValidator\\\\Handler\\\\ArrayStructureValidateInternalHandler\] Message ID=[a-f0-9]+ :  PHPUnit\\\\Framework\\\\TestCase::runTest -> \[ArrayStructureValidateInternalHandler\] Errors: Missing required key "root\.lorem"\.$/';
                    self::assertThat($message, new RegularExpression($pattern));
                },
                'context' => function ($trace) {
                    $this->assertIsArray($trace);
                },

            ],
        ];

        PhpUnitTool::assertArrayRecords($logger->records, $expected);

        $result->getOrThrow();
    }

    public function testExpectExceptionForArrayStructureValidateInternalWhereKeyNotExistWithMorKeys(): void
    {
        $data = [
            'name' => 'Foo',
            'email' => 'foo@test.com',
        ];

        /** @var TestLogger $logger */
        $logger = $this->getContainer()->get(TestLogger::class);

        $result = Result::success($data)
            ->bind($this->handlerFactory->create(ArrayStructureValidateInternalHandler::class)->setKeys(['name', 'lorem', 'bar']));
        \restore_exception_handler();


        $this->expectException(InvalidArgumentException::class);

        $expected = [
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/^\[ZJKiza\\\\HttpResponseValidator\\\\Handler\\\\ArrayStructureValidateInternalHandler\] Message ID=[a-f0-9]+ :  PHPUnit\\\\Framework\\\\TestCase::runTest -> \[ArrayStructureValidateInternalHandler\] Errors: Missing required key "root\.lorem", Missing required key "root\.bar"\.$/';
                    self::assertThat($message, new RegularExpression($pattern));
                },
                'context' => function ($trace) {
                    $this->assertIsArray($trace);
                },

            ],
        ];

        PhpUnitTool::assertArrayRecords($logger->records, $expected);

        $result->getOrThrow();
    }

    public function testExpectExceptionForArrayStructureValidateExactWhereKeyNotExistWithMorKeys(): void
    {
        $data = [
            'args' => [
                'test' => '123',
            ],
            'headers' => [
                'host' => 'postman-echo.com',
                'dnt' => '1',
                'foo' => '1',
                'bar' => [
                    'barKey1' => 'lorem',
                    'barKey2' => 1,
                ],
            ],
        ];

        $structure = [
            'args' => [
                'test' => 'string',
            ],
            'headers' => [
                'host' => 'string',
                'dnt' => 'string',
                'foo' => 'string',
                'bar' => [
                    'barKey1' => 'string',
                ],
            ],
        ];

        /** @var TestLogger $logger */
        $logger = $this->getContainer()->get(TestLogger::class);

        $result = Result::success($data)
            ->bind($this->handlerFactory->create(ArrayStructureValidateExactHandler::class)->setKeys($structure));
        \restore_exception_handler();


        $this->expectException(InvalidArgumentException::class);

        $expected = [
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/^\[ZJKiza\\\\HttpResponseValidator\\\\Handler\\\\ArrayStructureValidateExactHandler\] Message ID=[a-f0-9]+ ?: +PHPUnit\\\\Framework\\\\TestCase::runTest -> \[ArrayStructureValidateExactHandler\] Errors: Exact key mismatch at \"root\.headers\.bar\"\. Expected: PHPUnit\\\\Framework\\\\TestCase::runTest -> \[\"barKey1\"\], got: PHPUnit\\\\Framework\\\\TestCase::runTest -> \[\"barKey1\",\"barKey2\"\]\.$/';
                    self::assertThat($message, new RegularExpression($pattern));
                },
                'context' => function ($trace) {
                    $this->assertIsArray($trace);
                },

            ],
        ];

        PhpUnitTool::assertArrayRecords($logger->records, $expected);

        $result->getOrThrow();
    }
}
