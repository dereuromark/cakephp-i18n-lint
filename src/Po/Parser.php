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

/**
 * Parses PO/POT files into entries that keep their original text, so a
 * rewrite only touches the entries that actually changed.
 */
class Parser
{
    public function parseFile(string $path): Catalog
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new ParseException(sprintf('Cannot read `%s`.', $path), 0);
        }

        return $this->parse($content);
    }

    public function parse(string $content): Catalog
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

        $entries = [];
        /** @var array<array{int, string}> $block */
        $block = [];
        $seenMsgstr = false;
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            $isObsolete = str_starts_with($trimmed, '#~');
            $body = $isObsolete ? ltrim(substr($trimmed, 2)) : $trimmed;
            $startsEntry = str_starts_with($trimmed, '#') && !$isObsolete
                || str_starts_with($body, 'msgctxt')
                || str_starts_with($body, 'msgid ')
                || str_starts_with($body, 'msgid"');
            if ($trimmed === '' || ($seenMsgstr && $startsEntry)) {
                if ($block) {
                    $entries[] = $this->parseBlock($block);
                }
                $block = [];
                $seenMsgstr = false;
            }
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($body, 'msgstr')) {
                $seenMsgstr = true;
            }
            $block[] = [$index + 1, $line];
        }
        if ($block) {
            $entries[] = $this->parseBlock($block);
        }

        return new Catalog($entries);
    }

    /**
     * @param array<array{int, string}> $block
     *
     * @throws \I18nLint\Po\ParseException
     */
    protected function parseBlock(array $block): Entry
    {
        $entry = new Entry(
            raw: implode("\n", array_map(fn (array $row): string => rtrim($row[1]), $block)),
            line: $block[0][0],
        );
        $fields = [];
        $current = null;
        $obsolete = false;
        $hasLine = false;
        foreach ($block as [$number, $line]) {
            $line = trim($line);
            if (str_starts_with($line, '#~')) {
                $obsolete = true;
                $line = ltrim(substr($line, 2));
                if ($line === '') {
                    continue;
                }
            } elseif (str_starts_with($line, '#')) {
                if (str_starts_with($line, '#,')) {
                    $flags = array_map(trim(...), explode(',', substr($line, 2)));
                    $entry->flags = array_merge($entry->flags, array_filter($flags, fn (string $flag): bool => $flag !== ''));
                }

                continue;
            }

            if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s*(".*)$/', $line, $matches)) {
                $current = $matches[1];
                if (array_key_exists($current, $fields)) {
                    throw new ParseException(sprintf('Duplicate `%s` in one entry.', $current), $number);
                }
                $fields[$current] = $this->unquote($matches[3], $number);
                if ($current === 'msgid' && !$hasLine) {
                    $entry->line = $number;
                    $hasLine = true;
                }

                continue;
            }
            if (str_starts_with($line, '"') && $current !== null) {
                $fields[$current] .= $this->unquote($line, $number);

                continue;
            }

            throw new ParseException(sprintf('Unexpected content `%s`.', $line), $number);
        }

        $entry->obsolete = $obsolete;
        if (!$fields) {
            // Comment-only block, e.g. a trailing note; kept verbatim.
            $entry->obsolete = true;

            return $entry;
        }
        if (!array_key_exists('msgid', $fields)) {
            throw new ParseException('Entry without `msgid`.', $block[0][0]);
        }

        $entry->msgid = $fields['msgid'];
        $entry->msgctxt = $fields['msgctxt'] ?? null;
        $entry->msgidPlural = $fields['msgid_plural'] ?? null;
        foreach ($fields as $name => $value) {
            if ($name === 'msgstr') {
                if ($entry->msgidPlural !== null) {
                    throw new ParseException('Plural entry needs `msgstr[0]`, not `msgstr`.', $entry->line);
                }
                $entry->msgstr[0] = $value;
            } elseif (preg_match('/^msgstr\[(\d+)\]$/', $name, $matches)) {
                if ($entry->msgidPlural === null) {
                    throw new ParseException('`msgstr[n]` without `msgid_plural`.', $entry->line);
                }
                $entry->msgstr[(int)$matches[1]] = $value;
            }
        }
        if (!$entry->msgstr) {
            throw new ParseException('Entry without `msgstr`.', $entry->line);
        }
        ksort($entry->msgstr);

        return $entry;
    }

    protected function unquote(string $value, int $line): string
    {
        $value = trim($value);
        if (!preg_match('/^"(?:[^"\\\\]|\\\\.)*"$/', $value)) {
            throw new ParseException('Malformed string, check the quotes.', $line);
        }

        return stripcslashes(substr($value, 1, -1));
    }
}
