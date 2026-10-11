<?php

declare(strict_types=1);

namespace CakeI18nLint\Test\TestCase;

use CakeI18nLint\Po\Parser;
use CakeI18nLint\Syncer;
use PHPUnit\Framework\TestCase;

class SyncerTest extends TestCase
{
    /**
     * @var string
     */
    protected const POT = "msgid \"Kept\"\nmsgstr \"\"\n\nmsgid \"New \\\"one\\\"\\n\"\nmsgstr \"\"\n\n"
        . "msgctxt \"menu\"\nmsgid \"Open\"\nmsgstr \"\"\n\n"
        . "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";

    /**
     * @var string
     */
    protected const PO = "# Kopf\nmsgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=0;\\n\"\n\n"
        . "msgid \"Gone\"\nmsgstr \"Weg\"\n\n# Wichtig\nmsgid \"Kept\"\nmsgstr \"\"\n\"Behalten\"\n\n"
        . "#~ msgid \"Old\"\n#~ msgstr \"Alt\"\n";

    public function testSync(): void
    {
        $parser = new Parser();
        $po = $parser->parse(static::PO);

        $result = (new Syncer())->sync($po, $parser->parse(static::POT));

        $this->assertSame(['Gone'], $result['removed']);
        $this->assertSame(["New \"one\"\n", 'Open', '{0} a'], $result['added']);

        $expected = "# Kopf\nmsgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=0;\\n\"\n\n"
            . "# Wichtig\nmsgid \"Kept\"\nmsgstr \"\"\n\"Behalten\"\n\n"
            . "#~ msgid \"Old\"\n#~ msgstr \"Alt\"\n\n"
            . "msgid \"New \\\"one\\\"\\n\"\nmsgstr \"\"\n\n"
            . "msgctxt \"menu\"\nmsgid \"Open\"\nmsgstr \"\"\n\n"
            . "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"\n";
        $this->assertSame($expected, $po->toString());

        // The result parses back and needs no further sync.
        $again = (new Syncer())->sync($parser->parse($po->toString()), $parser->parse(static::POT));
        $this->assertSame(['added' => [], 'removed' => []], $again);
    }

    public function testPluralShapeChangeResetsTheEntry(): void
    {
        $parser = new Parser();
        $po = $parser->parse(
            "msgid \"{0} a\"\nmsgstr \"{0} x\"\n\n"
            . "msgid \"{0} c\"\nmsgid_plural \"{0} old\"\nmsgstr[0] \"y\"\nmsgstr[1] \"z\"\n\n"
            . "msgid \"Kept\"\nmsgstr \"Behalten\"\n",
        );
        $pot = $parser->parse(
            "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
            . "msgid \"{0} c\"\nmsgid_plural \"{0} new\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
            . "msgid \"Kept\"\nmsgstr \"\"\n",
        );

        $result = (new Syncer())->sync($po, $pot, keepObsolete: true);

        $this->assertSame(['added' => ['{0} a', '{0} c'], 'removed' => ['{0} a', '{0} c']], $result);
        $expected = "msgid \"{0} a\"\nmsgid_plural \"{0} b\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
            . "msgid \"{0} c\"\nmsgid_plural \"{0} new\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
            . "msgid \"Kept\"\nmsgstr \"Behalten\"\n";
        $this->assertSame($expected, $po->toString());
    }

    public function testKeepObsolete(): void
    {
        $parser = new Parser();
        $po = $parser->parse(static::PO);

        $result = (new Syncer())->sync($po, $parser->parse(static::POT), keepObsolete: true);

        $this->assertSame([], $result['removed']);
        $this->assertStringContainsString('msgid "Gone"', $po->toString());
    }
}
