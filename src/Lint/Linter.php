<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace CakeI18nLint\Lint;

use CakeI18nLint\Po\Catalog;
use CakeI18nLint\Po\Entry;
use IntlException;
use MessageFormatter;

class Linter
{
    /**
     * Checks the source strings of a template.
     *
     * @return array<\CakeI18nLint\Lint\Issue>
     */
    public function lintPot(string $file, Catalog $pot): array
    {
        $issues = $this->duplicates($file, $pot);
        foreach ($pot->messages() as $entry) {
            foreach (array_filter([$entry->msgid, $entry->msgidPlural]) as $message) {
                $error = Placeholders::icu($message) ? $this->icuError('en', $message) : null;
                if ($error !== null) {
                    $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'pot.icu', sprintf(
                        'Source string %s is not a valid ICU message: %s',
                        $this->excerpt($message),
                        $error,
                    ));
                }
            }
        }

        return $issues;
    }

    /**
     * Checks a translation catalog, optionally against its template.
     *
     * @return array<\CakeI18nLint\Lint\Issue>
     */
    public function lintPo(string $file, Catalog $po, string $locale, ?Catalog $pot = null): array
    {
        $issues = $this->duplicates($file, $po);
        $messages = $po->messages();
        $template = $pot?->messages();
        $nplurals = $po->nplurals();

        foreach ($messages as $key => $entry) {
            $label = $this->excerpt($entry->msgid);
            if ($template !== null && !isset($template[$key])) {
                $issues[] = new Issue($file, $entry->line, Issue::WARNING, 'po.obsolete', sprintf(
                    '%s is not in the template anymore.',
                    $label,
                ));

                continue;
            }
            if ($template !== null && $template[$key]->isPlural() !== $entry->isPlural()) {
                $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.plural-mismatch', sprintf(
                    '%s is %s in the template but %s here.',
                    $label,
                    $template[$key]->isPlural() ? 'plural' : 'singular',
                    $entry->isPlural() ? 'plural' : 'singular',
                ));
            } elseif ($template !== null && $template[$key]->msgidPlural !== $entry->msgidPlural) {
                $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.plural-mismatch', sprintf(
                    '%s has a different `msgid_plural` than the template.',
                    $label,
                ));
            }
            if ($entry->isPlural() && array_keys($entry->msgstr) !== range(0, $nplurals - 1)) {
                $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.plural-count', sprintf(
                    '%s has plural form(s) %s, the header declares %d (0 to %d).',
                    $label,
                    implode(', ', array_keys($entry->msgstr)),
                    $nplurals,
                    $nplurals - 1,
                ));
            }
            if (in_array('fuzzy', $entry->flags, true)) {
                // CakePHP's PO loader does not skip fuzzy entries.
                $issues[] = new Issue($file, $entry->line, Issue::WARNING, 'po.fuzzy', sprintf(
                    '%s is marked fuzzy, but CakePHP serves it anyway.',
                    $label,
                ));
            }
            if (!$entry->isTranslated()) {
                $issues[] = new Issue($file, $entry->line, Issue::WARNING, 'po.untranslated', sprintf(
                    '%s is not translated.',
                    $label,
                ));
            }
            array_push($issues, ...$this->lintTranslations($file, $entry, $locale));
        }

        if ($template !== null) {
            foreach (array_diff_key($template, $messages) as $entry) {
                $issues[] = new Issue($file, 0, Issue::WARNING, 'po.missing', sprintf(
                    '%s from the template is missing.',
                    $this->excerpt($entry->msgid),
                ));
            }
        }
        if (!$po->hasPluralForms() && array_filter($messages, fn (Entry $entry): bool => $entry->isPlural())) {
            $issues[] = new Issue($file, $po->header()->line ?? 0, Issue::WARNING, 'po.plural-header', 'Plural entries exist but the header has no usable `Plural-Forms`.');
        }

        return $issues;
    }

    /**
     * @return array<\CakeI18nLint\Lint\Issue>
     */
    protected function lintTranslations(string $file, Entry $entry, string $locale): array
    {
        $issues = [];
        $label = $this->excerpt($entry->msgid);
        $sources = array_filter([$entry->msgid, $entry->msgidPlural], fn (?string $source): bool => $source !== null);
        $sourceArgs = array_unique(array_merge(...array_map(Placeholders::icu(...), $sources)));
        $sourcePrintf = Placeholders::printf($entry->msgid);

        foreach ($entry->msgstr as $index => $msgstr) {
            if ($msgstr === '') {
                continue;
            }
            $form = $entry->isPlural() ? sprintf(' (form %d)', $index) : '';

            $args = Placeholders::icu($msgstr);
            $unknown = array_diff($args, $sourceArgs);
            if ($unknown) {
                $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.placeholder-unknown', sprintf(
                    'Translation of %s%s uses placeholder(s) the source does not have: %s.',
                    $label,
                    $form,
                    $this->braced($unknown),
                ));
            }
            $missing = array_diff($sourceArgs, $args);
            // A plural form may legitimately spell the number out instead of using it.
            if ($missing && !$entry->isPlural()) {
                $issues[] = new Issue($file, $entry->line, Issue::WARNING, 'po.placeholder-missing', sprintf(
                    'Translation of %s drops placeholder(s): %s.',
                    $label,
                    $this->braced($missing),
                ));
            }
            // Without arguments CakePHP returns the string as is and never parses it.
            if ($sourceArgs || $args) {
                $error = $this->icuError($locale, $msgstr);
                if ($error !== null) {
                    $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.icu', sprintf(
                        'Translation of %s%s is not a valid ICU message: %s',
                        $label,
                        $form,
                        $error,
                    ));
                }
            }
            if (!$entry->isPlural() && $sourcePrintf && $sourcePrintf !== Placeholders::printf($msgstr)) {
                $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.printf', sprintf(
                    'Translation of %s has different sprintf specifiers than the source.',
                    $label,
                ));
            }
            if (!$entry->isPlural() && $this->edges($entry->msgid) !== $this->edges($msgstr)) {
                $issues[] = new Issue($file, $entry->line, Issue::WARNING, 'po.whitespace', sprintf(
                    'Translation of %s differs in leading or trailing whitespace.',
                    $label,
                ));
            }
        }

        return $issues;
    }

    /**
     * @return array<\CakeI18nLint\Lint\Issue>
     */
    protected function duplicates(string $file, Catalog $catalog): array
    {
        $issues = [];
        $seen = [];
        foreach ($catalog->entries as $entry) {
            if ($entry->obsolete || $entry->isHeader()) {
                continue;
            }
            if (isset($seen[$entry->key()])) {
                $issues[] = new Issue($file, $entry->line, Issue::ERROR, 'po.duplicate', sprintf(
                    '%s is defined more than once (first on line %d).',
                    $this->excerpt($entry->msgid),
                    $seen[$entry->key()],
                ));

                continue;
            }
            $seen[$entry->key()] = $entry->line;
        }

        return $issues;
    }

    protected function icuError(string $locale, string $message): ?string
    {
        try {
            new MessageFormatter($locale, $message);
        } catch (IntlException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array{string, string}
     */
    protected function edges(string $value): array
    {
        preg_match('/^\s*/u', $value, $leading);
        preg_match('/\s*$/u', $value, $trailing);

        return [$leading[0] ?? '', $trailing[0] ?? ''];
    }

    /**
     * @param array<string> $names
     */
    protected function braced(array $names): string
    {
        return implode(', ', array_map(fn (string $name): string => '{' . $name . '}', $names));
    }

    protected function excerpt(string $message): string
    {
        $message = (string)preg_replace('/\s+/u', ' ', $message);
        if (mb_strlen($message) > 60) {
            $message = mb_substr($message, 0, 57) . '...';
        }

        return '"' . $message . '"';
    }
}
