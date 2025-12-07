<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\Functional;

use ZJKiza\HttpResponseValidator\Tests\Resources\KernelTestCase;
use ZJKiza\HttpResponseValidator\Validator\ArrayStructureExactValidation;
use ZJKiza\HttpResponseValidator\Validator\Helper\ErrorCollector;

final class ArrayStructureExactValidatorTest extends KernelTestCase
{
    public function testSuccessfulWithOutCheckType(): void
    {
        $validator = new ArrayStructureExactValidation(new ErrorCollector());

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

        $validator = new ArrayStructureExactValidation(new ErrorCollector(), true, true);

        $validator->validate($structure, $data);

        $this->assertFalse($validator->getErrorCollector()->hasErrors());
    }

    public function testExpectErrorsWhenValidationTypeIsIncorrected(): void
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

        $validator = new ArrayStructureExactValidation(new ErrorCollector(), false, true);

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

    public function testExpectErrorsWithOutCheckTypeAndMissingKeys(): void
    {
        $validator = new ArrayStructureExactValidation(new ErrorCollector());

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

        $expected = [
            'Exact key mismatch at "root.headers". Expected: ["ad","dnt","host"], got: ["ad","dnt","foo","host"]',
            'Exact key mismatch at "root.headers.ad". Expected: ["bb"], got: ["bb","cc"]',
            'Exact key mismatch at "root.body.items.*". Expected: ["name"], got: ["age","name"]',
        ];

        $this->assertSame($expected, $validator->getErrorCollector()->all());
    }

    public function testExpectErrorsWithCheckTypeAndMissingKeys(): void
    {
        $structure = [
            'args' => [
                'test' => 'string',
            ],
            'headers' => [
                'host' => 'string',
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

        $validator = new ArrayStructureExactValidation(new ErrorCollector(), true, true);

        $validator->validate($structure, $data);

        $expected = [
            'Exact key mismatch at "root.headers". Expected: ["ad","foo","host"], got: ["ad","dnt","foo","host"]',
            'Exact key mismatch at "root.headers.ad". Expected: ["bb","cc"], got: ["bb","cc","dd"]',
            'Key "root.headers.ad.cc" expects type "string", got "object"',
            'Exact key mismatch at "root.body.items.*". Expected: ["name"], got: ["age","name"]',
        ];

        $this->assertSame($expected, $validator->getErrorCollector()->all());
    }
}
