<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\PhpUnitTool;

use PHPUnit\Framework\TestCase;

final class PhpUnitTool extends TestCase
{
    /**
     * @param array<int, array<string, mixed>>                $actual
     * @param array<int, array<string, mixed|callable|array>> $expected
     */
    public static function assertArrayRecords(array $actual, array $expected): void
    {
        self::assertCount(\count($expected), $actual);

        foreach ($expected as $index => $expItem) {
            /** @var array<string, mixed> $actItem */
            $actItem = $actual[$index];

            foreach ($expItem as $key => $expValue) {
                self::assertArrayHasKey($key, $actItem);

                $actValue = $actItem[$key];

                //  Ako je callable, pozovi je
                if (\is_callable($expValue)) {
                    $expValue($actValue);
                } // Ako je niz, rekurzivno pozovi
                elseif (\is_array($expValue)) {
                    self::assertIsArray($actValue);

                    /** @var array<string, mixed> $nestedActual */
                    $nestedActual = $actValue;

                    /** @var array<string, mixed> $nestedExpected */
                    $nestedExpected = $expValue;

                    self::assertArrayRecursive($nestedActual, $nestedExpected);
                } // Ako je vrednost, poredi
                else {
                    self::assertSame($expValue, $actValue);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>                $actual
     * @param array<string, mixed|callable|array> $expected
     */
    private static function assertArrayRecursive(array $actual, array $expected): void
    {
        foreach ($expected as $key => $expValue) {
            self::assertArrayHasKey($key, $actual);

            $actValue = $actual[$key];

            if (\is_callable($expValue)) {
                $expValue($actValue);
            } elseif (\is_array($expValue)) {
                self::assertIsArray($actValue);

                /** @var array<string, mixed> $nestedActual */
                $nestedActual = $actValue;

                /** @var array<string, mixed> $nestedExpected */
                $nestedExpected = $expValue;

                self::assertArrayRecursive($nestedActual, $nestedExpected);
            } else {
                self::assertSame($expValue, $actValue);
            }
        }
    }
}
