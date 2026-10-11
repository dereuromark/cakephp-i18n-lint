<?php

declare(strict_types=1);

namespace CakeI18nLint\Test\TestCase\Lint;

use CakeI18nLint\Lint\Issue;
use CakeI18nLint\Lint\Linter;
use CakeI18nLint\Po\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LinterTest extends TestCase
{
    /**
     * @var string
     */
    protected const HEADER = "msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";

    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function poProvider(): array
    {
        return [
            'clean' => ["msgid \"Hello {0}\"\nmsgstr \"Hallo {0}\"\n", []],
            'unknown placeholder' => ["msgid \"Hello {0}\"\nmsgstr \"Hallo {1}\"\n", ['po.placeholder-unknown', 'po.placeholder-missing']],
            'dropped placeholder' => ["msgid \"Hello {0}\"\nmsgstr \"Hallo\"\n", ['po.placeholder-missing']],
            'broken icu' => ["msgid \"Hello {0}\"\nmsgstr \"Hallo {0\"\n", ['po.placeholder-missing', 'po.icu']],
            'broken icu plural' => [
                "msgid \"{0, plural, one{# a} other{# b}}\"\nmsgstr \"{0, plural, one{# a}}\"\n",
                ['po.icu'],
            ],
            'braces without arguments are not parsed' => ["msgid \"Use {\"\nmsgstr \"Nutze {\"\n", []],
            'printf' => ["msgid \"%s of %d\"\nmsgstr \"%s von %s\"\n", ['po.printf']],
            'printf swapped types' => ["msgid \"%s has %d\"\nmsgstr \"%d hat %s\"\n", ['po.printf']],
            'printf reordered' => ["msgid \"%1\$s of %2\$d\"\nmsgstr \"%2\$d von %1\$s\"\n", []],
            'percent sign in prose' => ["msgid \"Up to 5% off\"\nmsgstr \"Bis zu 5 % sparen\"\n", []],
            'whitespace' => ["msgid \"Name: \"\nmsgstr \"Name:\"\n", ['po.whitespace']],
            'untranslated' => ["msgid \"A\"\nmsgstr \"\"\n", ['po.untranslated']],
            'fuzzy' => ["#, fuzzy\nmsgid \"A\"\nmsgstr \"B\"\n", ['po.fuzzy']],
            'duplicate' => ["msgid \"A\"\nmsgstr \"B\"\n\nmsgid \"A\"\nmsgstr \"C\"\n", ['po.duplicate']],
            'same id in another context' => ["msgid \"A\"\nmsgstr \"B\"\n\nmsgctxt \"x\"\nmsgid \"A\"\nmsgstr \"C\"\n", []],
            'plural count' => [
                "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"{0} c\"\n",
                ['po.plural-count'],
            ],
            'plural indices' => [
                "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[1] \"{0} c\"\nmsgstr[2] \"{0} d\"\n",
                ['po.plural-count'],
            ],
            'plural may spell out the number' => [
                "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"ein c\"\nmsgstr[1] \"{0} d\"\n",
                [],
            ],
        ];
    }

    /**
     * @param string $entries
     * @param array<string> $expected
     */
    #[DataProvider('poProvider')]
    public function testLintPo(string $entries, array $expected): void
    {
        $catalog = (new Parser())->parse(static::HEADER . $entries);

        $issues = (new Linter())->lintPo('de.po', $catalog, 'de');

        $this->assertSame($expected, array_map(fn (Issue $issue): string => $issue->rule, $issues));
    }

    public function testLintPoAgainstTemplate(): void
    {
        $parser = new Parser();
        $pot = $parser->parse(
            "msgid \"Kept\"\nmsgstr \"\"\n\nmsgid \"New\"\nmsgstr \"\"\n\n"
            . "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n",
        );
        $po = $parser->parse(
            static::HEADER
            . "msgid \"Kept\"\nmsgstr \"Behalten\"\n\nmsgid \"Gone {0}\"\nmsgstr \"Weg {1}\"\n\n"
            . "msgid \"{0} a\"\nmsgstr \"{0} c\"\n",
        );

        $issues = (new Linter())->lintPo('de.po', $po, 'de', $pot);

        $rules = [];
        foreach ($issues as $issue) {
            $rules[$issue->rule] = $issue->severity;
        }
        $this->assertSame([
            'po.obsolete' => Issue::WARNING,
            'po.plural-mismatch' => Issue::ERROR,
            'po.missing' => Issue::WARNING,
        ], $rules);
        $this->assertSame(8, $issues[0]->line);
    }

    public function testChangedPluralSource(): void
    {
        $parser = new Parser();
        $pot = $parser->parse("msgid \"a\"\nmsgid_plural \"new\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n");
        $po = $parser->parse(static::HEADER . "msgid \"a\"\nmsgid_plural \"old\"\nmsgstr[0] \"c\"\nmsgstr[1] \"d\"\n");

        $issues = (new Linter())->lintPo('de.po', $po, 'de', $pot);

        $this->assertSame(['po.plural-mismatch'], array_map(fn (Issue $issue): string => $issue->rule, $issues));
    }

    public function testPluralEntriesWithoutPluralHeader(): void
    {
        $po = (new Parser())->parse("msgid \"a\"\nmsgid_plural \"b\"\nmsgstr[0] \"c\"\nmsgstr[1] \"d\"\n");

        $issues = (new Linter())->lintPo('de.po', $po, 'de');

        $this->assertSame(['po.plural-header'], array_map(fn (Issue $issue): string => $issue->rule, $issues));
    }

    public function testLintPot(): void
    {
        $pot = (new Parser())->parse(
            "msgid \"Fine {0}\"\nmsgstr \"\"\n\nmsgid \"Bad {0, plural, one{#}}\"\nmsgstr \"\"\n\n"
            . "msgid \"Fine {0}\"\nmsgstr \"\"\n",
        );

        $issues = (new Linter())->lintPot('demo.pot', $pot);

        $this->assertSame(['po.duplicate', 'pot.icu'], array_map(fn (Issue $issue): string => $issue->rule, $issues));
    }
}
