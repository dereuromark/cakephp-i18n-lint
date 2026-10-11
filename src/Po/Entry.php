<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace I18nLint\Po;

class Entry
{
    /**
     * @param array<string> $flags
     * @param bool $obsolete
     * @param string $raw
     * @param int $line
     * @param string $msgid
     * @param string|null $msgctxt
     * @param string|null $msgidPlural
     * @param array<int, string> $msgstr
     */
    public function __construct(
        public string $raw,
        public int $line,
        public string $msgid = '',
        public ?string $msgctxt = null,
        public ?string $msgidPlural = null,
        public array $msgstr = [],
        public array $flags = [],
        public bool $obsolete = false,
    ) {
    }

    public function key(): string
    {
        return ($this->msgctxt ?? '') . "\x04" . $this->msgid;
    }

    public function isHeader(): bool
    {
        return !$this->obsolete && $this->msgid === '' && $this->msgctxt === null;
    }

    public function isPlural(): bool
    {
        return $this->msgidPlural !== null;
    }

    public function isTranslated(): bool
    {
        if (!$this->msgstr) {
            return false;
        }
        foreach ($this->msgstr as $msgstr) {
            if ($msgstr === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Builds an untranslated entry mirroring a template entry.
     */
    public static function blankFrom(self $template, int $nplurals): self
    {
        $lines = [];
        if ($template->msgctxt !== null) {
            $lines[] = 'msgctxt ' . static::quote($template->msgctxt);
        }
        $lines[] = 'msgid ' . static::quote($template->msgid);
        $msgstr = [''];
        if ($template->msgidPlural !== null) {
            $lines[] = 'msgid_plural ' . static::quote($template->msgidPlural);
            $msgstr = array_fill(0, max(1, $nplurals), '');
            foreach (array_keys($msgstr) as $i) {
                $lines[] = sprintf('msgstr[%d] ""', $i);
            }
        } else {
            $lines[] = 'msgstr ""';
        }

        return new self(
            raw: implode("\n", $lines),
            line: 0,
            msgid: $template->msgid,
            msgctxt: $template->msgctxt,
            msgidPlural: $template->msgidPlural,
            msgstr: $msgstr,
        );
    }

    public static function quote(string $value): string
    {
        return '"' . addcslashes($value, "\0..\37\"\\") . '"';
    }
}
