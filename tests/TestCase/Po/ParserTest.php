<?php

declare(strict_types=1);

namespace CakeI18nLint\Test\TestCase\Po;

use CakeI18nLint\Po\ParseException;
use CakeI18nLint\Po\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    protected const PO = <<<'PO'
        # Header comment.
        msgid ""
        msgstr ""
        "Language: de\n"
        "Plural-Forms: nplurals=3; plural=(n != 1);\n"

        #, fuzzy, php-format
        msgid "Line one\n"
        "line \"two\""
        msgstr "Zeile eins\n"
        "Zeile zwei"

        msgctxt "menu"
        msgid "Open"
        msgstr "Öffnen"
        msgid "{0} item"
        msgid_plural "{0} items"
        msgstr[0] "{0} Eintrag"
        msgstr[1] "{0} Einträge"

        #~ msgid "Old"
        #~ msgstr "Alt"
        PO;

    public function testParse(): void
    {
        $catalog = (new Parser())->parse(static::PO);

        $this->assertCount(5, $catalog->entries);
        $this->assertSame(3, $catalog->nplurals());
        $this->assertTrue($catalog->hasPluralForms());
        $this->assertNotNull($catalog->header());

        $messages = array_values($catalog->messages());
        $this->assertCount(3, $messages);

        $this->assertSame("Line one\nline \"two\"", $messages[0]->msgid);
        $this->assertSame(["Zeile eins\nZeile zwei"], $messages[0]->msgstr);
        $this->assertSame(['fuzzy', 'php-format'], $messages[0]->flags);
        $this->assertSame(8, $messages[0]->line);

        $this->assertSame('menu', $messages[1]->msgctxt);
        $this->assertSame('Öffnen', $messages[1]->msgstr[0]);

        // No blank line before this entry in the source.
        $this->assertSame('{0} items', $messages[2]->msgidPlural);
        $this->assertSame(['{0} Eintrag', '{0} Einträge'], $messages[2]->msgstr);

        $this->assertTrue($catalog->entries[4]->obsolete);
    }

    public function testEntriesKeepTheirOriginalText(): void
    {
        $content = "msgid \"\"\nmsgstr \"\"\n\"Language: de\\n\"\n\n# Note.\nmsgid   \"A\"\nmsgstr \"\"\n  \"B\"\n";

        $this->assertSame($content, (new Parser())->parse($content)->toString());
    }

    public function testByteOrderMarkAndWindowsLineEndings(): void
    {
        $catalog = (new Parser())->parse("\xEF\xBB\xBFmsgid \"A\"\r\nmsgstr \"B\"\r\n");

        $this->assertSame('B', $catalog->messages()["\x04A"]->msgstr[0]);
    }

    public function testDefaultsWithoutHeader(): void
    {
        $catalog = (new Parser())->parse("msgid \"A\"\nmsgstr \"B\"\n");

        $this->assertNull($catalog->header());
        $this->assertSame(2, $catalog->nplurals());
        $this->assertFalse($catalog->hasPluralForms());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function invalidProvider(): array
    {
        return [
            'unterminated' => ["msgid \"A\"\nmsgstr \"B\n", 2],
            'escaped closing quote' => ["msgid \"A\"\nmsgstr \"B\\\"\n", 2],
            'stray quote' => ["msgid \"a\"garbage\"\nmsgstr \"B\"\n", 1],
            'dangling backslash' => ["msgid \"A\"\nmsgstr \"B\\\\\\\"\n", 2],
            'indexed msgstr on singular' => ["msgid \"A\"\nmsgstr[1] \"B\"\n", 1],
            'plain msgstr on plural' => ["msgid \"A\"\nmsgid_plural \"B\"\nmsgstr \"C\"\n", 1],
            'garbage' => ["msgid \"A\"\nmsgstr \"B\"\nwhatever\n", 3],
            'no msgstr' => ["msgid \"A\"\n", 1],
            'no msgid' => ["msgstr \"A\"\n", 1],
            'repeated field' => ["msgid \"A\"\nmsgid \"B\"\nmsgstr \"C\"\n", 2],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalid(string $content, int $line): void
    {
        try {
            (new Parser())->parse($content);
            $this->fail('No exception thrown.');
        } catch (ParseException $e) {
            $this->assertSame($line, $e->poLine);
        }
    }
}
