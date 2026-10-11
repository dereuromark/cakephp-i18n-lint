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

class Catalog
{
    /**
     * @param array<\I18nLint\Po\Entry> $entries In file order, header and obsolete entries included.
     */
    public function __construct(public array $entries = [])
    {
    }

    public function header(): ?Entry
    {
        foreach ($this->entries as $entry) {
            if ($entry->isHeader()) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Active entries indexed by key; the first one wins on duplicates.
     *
     * @return array<string, \I18nLint\Po\Entry>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->entries as $entry) {
            if ($entry->obsolete || $entry->isHeader()) {
                continue;
            }
            $messages[$entry->key()] ??= $entry;
        }

        return $messages;
    }

    public function nplurals(): int
    {
        $header = $this->header()?->msgstr[0] ?? '';
        if (preg_match('/nplurals\s*=\s*(\d+)/', $header, $matches)) {
            return max(1, (int)$matches[1]);
        }

        return 2;
    }

    public function hasPluralForms(): bool
    {
        return (bool)preg_match('/nplurals\s*=\s*\d+/', $this->header()?->msgstr[0] ?? '');
    }

    public function toString(): string
    {
        $blocks = [];
        foreach ($this->entries as $entry) {
            $blocks[] = $entry->raw;
        }

        return implode("\n\n", $blocks) . "\n";
    }
}
