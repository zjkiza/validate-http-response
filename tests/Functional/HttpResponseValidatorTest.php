<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\Functional;

use PHPUnit\Framework\Constraint\RegularExpression;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use ZJKiza\HttpResponseValidator\Contract\HandlerFactoryInterface;
use ZJKiza\HttpResponseValidator\Exception\InvalidArgumentException;
use ZJKiza\HttpResponseValidator\Exception\RuntimeException;
use ZJKiza\HttpResponseValidator\Handler\ExtractResponseJsonHandler;
use ZJKiza\HttpResponseValidator\Handler\HttpResponseLoggerHandler;
use ZJKiza\HttpResponseValidator\Handler\ValidateArrayKeysExistHandler;
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

    public function testSuccess(): void
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
        restore_exception_handler();

        $result = Result::success($response)
            ->bind($this->handlerFactory->create(HttpResponseLoggerHandler::class)->setExpectedStatus(201)->addSensitiveKeys(['tokenTTL']))
            ->bind($this->handlerFactory->create(ExtractResponseJsonHandler::class)->setAssociative(true))
            ->bind($this->handlerFactory->create(ValidateArrayKeysExistHandler::class)->setKeys(['name', 'email']))
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
        restore_exception_handler();

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
                        'options' => function ($value) {
                            $this->assertIsArray($value);
                        },
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
        restore_exception_handler();

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
            ->bind($this->handlerFactory->create(ValidateArrayKeysExistHandler::class)->setKeys(['name', 'lorem']));
        restore_exception_handler();


        $this->expectException(InvalidArgumentException::class);

        $expected = [
            [
                'level' => 'error',
                'message' => function (string $message) {
                    $pattern = '/^\[ZJKiza\\\HttpResponseValidator\\\Handler\\\ValidateArrayKeyExistHandler\] Message ID=[a-f0-9]+ :  PHPUnit\\\\Framework\\\\TestCase::runTest -> \[ValidateArrayKeyExistHandler\] There is no required field "lorem" in the array \(name, email\)\.$/';
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
