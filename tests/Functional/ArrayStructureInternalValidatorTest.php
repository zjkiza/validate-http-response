<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\Functional;

use ZJKiza\HttpResponseValidator\Tests\Resources\KernelTestCase;
use ZJKiza\HttpResponseValidator\Validator\ArrayStructureInternalValidation;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;

final class ArrayStructureInternalValidatorTest extends KernelTestCase
{
    public function testSuccessfulWithOutCheckType(): void
    {
        $validator = new ArrayStructureInternalValidation(new ErrorCollector());

        $structure = [
            'args' => [
                'test',
            ],
            'headers' => [
                'host',
                'dnt',
                'foo',
                'ad' => [
                    'bb',
                    'cc',
                ],
            ],
            'body' => [
                'items' => [
                    '*' => [
                        'name',
                        'age',
                    ],
                ],
            ],
        ];

        $data = [
            'args' => [
                'test' => '123',
            ],
            'headers' => [
                'host' => 'postman-echo.com',
                'dnt' => '1',
                'foo' => '1',
                'ad' => [
                    'bb' => 'lorem',
                    'cc' => 1,
                ],
            ],
            'body' => [
                'items' => [
                    [
                        'name' => 'name1',
                        'age' => 20,
                    ],
                    [
                        'name' => 'name2',
                        'age' => 22,
                    ],
                ],

            ],
        ];

        $validator->validate($structure, $data);

        $this->assertFalse($validator->getErrorCollector()->hasErrors());
    }

    public function testSuccessfulWithCheckType(): void
    {
        $structure = [
            'args' => [
                'test' => 'string',
            ],
            'headers' => [
                'host' => 'string',
                'dnt' => 'float',
                'foo' => true,
                'ad' => [
                    'bb' => 'array',
                    'cc' => 'object',
                    'dd' => 'null',
                ],
            ],
            'body' => [
                'items' => [
                    '*' => [
                        'name' => 'string',
                        'age' => 'int',
                    ],
                ],
            ],
        ];

        $data = [
            'args' => [
                'test' => '123',
            ],
            'headers' => [
                'host' => 'postman-echo.com',
                'dnt' => 1.23,
                'foo' => 'bool',
                'ad' => [
                    'bb' => [],
                    'cc' => new class () {
                    },
                    'dd' => null,
                ],
            ],
            'body' => [
                'items' => [
                    [
                        'name' => 'name1',
                        'age' => 20,
                    ],
                    [
                        'name' => 'name2',
                        'age' => 22,
                    ],
                ],

            ],
        ];

        $validator = new ArrayStructureInternalValidation(new ErrorCollector(), true, true);

        $validator->validate($structure, $data);

        $this->assertFalse($validator->getErrorCollector()->hasErrors());
    }

    public function testSuccessfulWithOutCheckTypeWhenTheSubarrayIs(): void
    {
        $validator = new ArrayStructureInternalValidation(new ErrorCollector());

        $structure = [
            'args' => [
                'test',
            ],
            'headers' => [
                'host',
                'dnt',
                'ad' => [
                    'bb',
                ],
            ],
            'body' => [
                'items' => [
                    '*' => [
                        'name',
                    ],
                ],
            ],
        ];

        $data = [
            'args' => [
                'test' => '123',
            ],
            'headers' => [
                'host' => 'postman-echo.com',
                'dnt' => '1',
                'foo' => '1',
                'ad' => [
                    'bb' => 'lorem',
                    'cc' => 1,
                ],
            ],
            'body' => [
                'items' => [
                    [
                        'name' => 'name1',
                        'age' => 20,
                    ],
                    [
                        'name' => 'name2',
                        'age' => 22,
                    ],
                ],

            ],
        ];

        $validator->validate($structure, $data);

        $this->assertFalse($validator->getErrorCollector()->hasErrors());
    }

    public function testExpectedErrorsWhenValidationTypeIsIncorrected(): void
    {
        $data = [
            'args' => [
                'test_string' => 123,
                'test_int' => '123',
                'test_float' => '123',
                'test_bool' => '123',
                'test_array' => '123',
                'test_object' => '123',
                'test_null' => '123',
            ],
        ];

        $structure = [
            'args' => [
                'test_string' => 'string',
                'test_int' => 'int',
                'test_float' => 'float',
                'test_bool' => 'bool',
                'test_array' => 'array',
                'test_object' => 'object',
                'test_null' => 'null',
            ],
        ];

        $validator = new ArrayStructureInternalValidation(new ErrorCollector(), false, true);

        $validator->validate($structure, $data);

        $expected = [
            'Key "root.args.test_string" expects type "string", got "integer"',
            'Key "root.args.test_int" expects type "int", got "string"',
            'Key "root.args.test_float" expects type "float", got "string"',
            'Key "root.args.test_bool" expects type "bool", got "string"',
            'Key "root.args.test_array" expects type "array", got "string"',
            'Key "root.args.test_object" expects type "object", got "string"',
            'Key "root.args.test_null" expects type "null", got "string"',
        ];

        $this->assertSame($expected, $validator->getErrorCollector()->all());
    }

    public function testExpectErrorsWithCheckTypeExpectErrorMissingKeyAndWrongType(): void
    {
        $structure = [
            'args' => [
                'test' => 'string',
            ],
            'headers' => [
                'host' => 'string',
                'bar' => 'string',
                'foo' => true,
                'ad' => [
                    'bb' => 'array',
                    'cc' => 'string',

                ],
            ],
            'body' => [
                'items' => [
                    '*' => [
                        'name' => 'string',
                    ],
                ],
            ],
        ];

        $data = [
            'args' => [
                'test' => '123',
            ],
            'headers' => [
                'host' => 'postman-echo.com',
                'dnt' => 1.23,
                'foo' => 'bool',
                'ad' => [
                    'bb' => [],
                    'cc' => new class () {
                    },
                    'dd' => null,
                ],
            ],
            'body' => [
                'items' => [
                    [
                        'name' => 'name1',
                        'age' => 20,
                    ],
                    [
                        'name' => 'name2',
                        'age' => 22,
                    ],
                ],

            ],
        ];

        $validator = new ArrayStructureInternalValidation(new ErrorCollector(), true, true);

        $validator->validate($structure, $data);

        $expected = [
            'Missing required key "root.headers.bar"',
            'Key "root.headers.ad.cc" expects type "string", got "object"',
        ];

        $this->assertSame($expected, $validator->getErrorCollector()->all());
    }
}
