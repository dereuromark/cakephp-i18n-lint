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

use I18nLint\Po\Parser;

/**
 * Compares freshly extracted POT files with the committed ones by message, not by bytes.
 */
class TemplateDiffer
{
    /**
     * @return array{
     *     domains: array<string, array{generated: string, target: string, new: bool, missing: array<string>, stale: array<string>}>,
     *     orphans: array<string, string>,
     * }
     */
    public function diff(Project $project, string $generatedDir): array
    {
        $parser = new Parser();
        $committed = $project->templates();
        $domains = [];
        foreach ($project->templates($generatedDir) as $domain => $generated) {
            $target = $committed[$domain] ?? $project->locales . DIRECTORY_SEPARATOR . $domain . '.pot';
            $fresh = $this->signatures($parser, $generated);
            $current = isset($committed[$domain]) ? $this->signatures($parser, $target) : [];
            $domains[$domain] = [
                'generated' => $generated,
                'target' => $target,
                'new' => !isset($committed[$domain]),
                'missing' => array_values(array_diff_key($fresh, $current)),
                'stale' => array_values(array_diff_key($current, $fresh)),
            ];
        }

        return ['domains' => $domains, 'orphans' => array_diff_key($committed, $domains)];
    }

    /**
     * @return array<string, string> Display string by signature; a plural change alters the signature.
     */
    protected function signatures(Parser $parser, string $file): array
    {
        $signatures = [];
        foreach ($parser->parseFile($file)->messages() as $key => $entry) {
            $display = $entry->msgctxt !== null ? sprintf('%s [%s]', $entry->msgid, $entry->msgctxt) : $entry->msgid;
            if ($entry->msgidPlural !== null) {
                $display .= ' / ' . $entry->msgidPlural;
            }
            $signatures[$key . "\x04" . ($entry->msgidPlural ?? '')] = $display;
        }

        return $signatures;
    }
}
