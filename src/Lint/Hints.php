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

/**
 * One line per rule telling the user how to get rid of the finding.
 */
class Hints
{
    /**
     * `{sync}` and `{extract}` are replaced with the command to run.
     *
     * @var array<string, string>
     */
    protected const HINTS = [
        'po.syntax' => 'Fix the PO syntax on the reported line.',
        'po.encoding' => 'Save the file as UTF-8.',
        'po.duplicate' => 'Remove one of the two entries.',
        'po.placeholder-unknown' => 'Use only the placeholders of the source string in the translation.',
        'po.placeholder-missing' => 'Add the placeholder to the translation, unless it was dropped on purpose.',
        'po.icu' => 'Fix the ICU syntax of the translation; wrap a literal brace in apostrophes.',
        'po.printf' => 'Keep the sprintf specifiers of the source; use numbered ones to reorder them.',
        'po.plural-count' => 'Provide exactly the plural forms `nplurals` in the header declares, starting at `msgstr[0]`.',
        'po.plural-mismatch' => 'Run `{sync}`; it resets the entry, then translate it again.',
        'po.plural-header' => 'Add a `Plural-Forms` header to the PO file.',
        'po.untranslated' => 'Add the translation.',
        'po.fuzzy' => 'Review the translation and remove the `fuzzy` flag.',
        'po.missing' => 'Run `{sync}`.',
        'po.obsolete' => 'Run `{sync}`.',
        'po.whitespace' => 'Match the leading and trailing whitespace of the source string.',
        'pot.icu' => 'Fix the source string in the code, then run `{extract}`.',
        'pot.missing' => 'Run `{extract}` and commit the result.',
        'pot.stale' => 'Run `{extract}` and commit the result.',
        'pot.missing-file' => 'Run `{extract}` and commit the result.',
        'pot.orphan' => 'Delete the template and its PO files if the domain is gone for good.',
    ];

    /**
     * @param array<string> $rules Rules that were reported.
     * @param string $sync Pasteable sync command.
     * @param string $extract Pasteable extract command.
     *
     * @return array<string, string> Hint by rule, in the order of the definition above.
     */
    public static function for(array $rules, string $sync, string $extract): array
    {
        $hints = [];
        foreach (static::HINTS as $rule => $hint) {
            if (in_array($rule, $rules, true)) {
                $hints[$rule] = str_replace(['{sync}', '{extract}'], [$sync, $extract], $hint);
            }
        }

        return $hints;
    }
}
