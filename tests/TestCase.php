<?php

declare(strict_types=1);

/**
 * A tiny test framework (no PHPUnit needed). Each test file returns a list of
 * closures; a test passes when it finishes without a failed assertion.
 */
final class T
{
    public static int $assertions = 0;

    public static function assertTrue(mixed $value, string $message = 'Expected true'): void
    {
        self::$assertions++;
        if ($value !== true) {
            throw new AssertionError($message);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            throw new AssertionError(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    public static function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        self::$assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionError(($message !== '' ? $message . ': ' : '') . "\"$needle\" not found in output");
        }
    }

    public static function assertThrows(string $class, callable $fn, string $message = ''): \Throwable
    {
        self::$assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $class) {
                return $e;
            }
            throw new AssertionError("Expected $class, got " . $e::class . ': ' . $e->getMessage());
        }
        throw new AssertionError($message !== '' ? $message : "Expected $class to be thrown");
    }
}
