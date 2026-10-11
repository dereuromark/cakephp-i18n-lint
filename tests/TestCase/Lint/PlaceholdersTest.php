<?php

declare(strict_types=1);

namespace CakeI18nLint\Test\TestCase\Lint;

use CakeI18nLint\Lint\Placeholders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlaceholdersTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function icuProvider(): array
    {
        return [
            'none' => ['Saved.', []],
            'positional' => ['{1} and {0}', ['0', '1']],
            'named with spaces' => ['Hi { name }', ['name']],
            'repeated' => ['{0} {0}', ['0']],
            'typed' => ['{0, number, integer} at {when, date}', ['0', 'when']],
            'nested in plural' => ['{count, plural, one{# file of {user}} other{# files}}', ['count', 'user']],
            'quoted braces' => ["Use '{0}' literally, {1}", ['1']],
            'apostrophe in prose' => ["Don't lose {0}", ['0']],
            'doubled apostrophe' => ["It''s {0}", ['0']],
            'not an argument' => ['{ "json": 1 }', []],
            'unbalanced' => ['} {0', []],
        ];
    }

    /**
     * @param string $message
     * @param array<string> $expected
     */
    #[DataProvider('icuProvider')]
    public function testIcu(string $message, array $expected): void
    {
        $this->assertSame($expected, Placeholders::icu($message));
    }

    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function printfProvider(): array
    {
        return [
            'none' => ['Saved.', []],
            'simple' => ['%s of %d', [1 => 's', 2 => 'd']],
            'positional' => ['%2$s %1$d', [1 => 'd', 2 => 's']],
            'padded' => ['%05.2f', [1 => 'f']],
            'other conversions' => ["%x %'*10s %e", [1 => 'x', 2 => 's', 3 => 'e']],
            'literal percent' => ['100%% of %s', [1 => 's']],
            'percent in prose' => ['2%, 2.5%, or 3% for some', []],
        ];
    }

    /**
     * @param string $message
     * @param array<int, string> $expected
     */
    #[DataProvider('printfProvider')]
    public function testPrintf(string $message, array $expected): void
    {
        $this->assertSame($expected, Placeholders::printf($message));
    }
}
