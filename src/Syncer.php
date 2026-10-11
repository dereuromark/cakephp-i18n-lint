<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace I18nLint;

use I18nLint\Po\Catalog;
use I18nLint\Po\Entry;

/**
 * Aligns a PO catalog with its POT template without touching existing translations.
 */
class Syncer
{
    /**
     * @return array{added: array<string>, removed: array<string>}
     */
    public function sync(Catalog $po, Catalog $pot, bool $keepObsolete = false): array
    {
        $template = $pot->messages();
        $existing = $po->messages();
        $added = [];
        $removed = [];

        $nplurals = $po->nplurals();
        $kept = [];
        foreach ($po->entries as $entry) {
            if ($entry->obsolete || $entry->isHeader()) {
                $kept[] = $entry;

                continue;
            }
            $source = $template[$entry->key()] ?? null;
            if ($source === null) {
                if ($keepObsolete) {
                    $kept[] = $entry;
                } else {
                    $removed[] = $entry->msgid;
                }

                continue;
            }
            // A translation written for another plural shape cannot be carried over.
            if ($source->msgidPlural !== $entry->msgidPlural && $existing[$entry->key()] === $entry) {
                $removed[] = $entry->msgid;
                $added[] = $source->msgid;
                $entry = Entry::blankFrom($source, $nplurals);
            }
            $kept[] = $entry;
        }
        $po->entries = $kept;

        foreach ($template as $key => $entry) {
            if (isset($existing[$key])) {
                continue;
            }
            $po->entries[] = Entry::blankFrom($entry, $nplurals);
            $added[] = $entry->msgid;
        }

        return ['added' => $added, 'removed' => $removed];
    }
}
