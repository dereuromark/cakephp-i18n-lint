<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace CakeI18nLint\Command;

use Cake\Console\ConsoleOptionParser;
use CakeI18nLint\Lint\Hints;
use CakeI18nLint\Lint\Issue;
use CakeI18nLint\Lint\Linter;
use CakeI18nLint\Po\Catalog;
use CakeI18nLint\Po\ParseException;
use CakeI18nLint\Po\Parser;
use CakeI18nLint\Project;

/**
 * Validates POT and PO files.
 */
class LintCommand extends AbstractCommand
{
    public static function getDescription(): string
    {
        return 'Lint the POT templates and PO translations.';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $this->addFormatOption(parent::buildOptionParser($parser))
            ->addOption('strict', [
                'help' => 'Treat warnings (untranslated, fuzzy, out of sync) as failures.',
                'boolean' => true,
            ])
            ->addOption('fail-on', [
                'help' => 'Comma-separated warning rules to report as errors, e.g. `po.missing,po.obsolete`.',
            ]);
    }

    public function execute(): int
    {
        $args = $this->args;
        $io = $this->io;
        $project = $this->project($args, $io);
        if ($project === null) {
            return static::CODE_ERROR;
        }

        $failOn = array_filter(array_map(trim(...), explode(',', (string)$this->stringOption($args, 'fail-on'))));
        $unknown = array_diff($failOn, Hints::rules());
        if ($unknown) {
            $io->error(sprintf('Unknown rule(s) for --fail-on: %s.', implode(', ', $unknown)));

            return static::CODE_ERROR;
        }

        $issues = $this->collect($project);
        $format = (string)$args->getOption('format');
        $counts = [Issue::ERROR => 0, Issue::WARNING => 0];
        $rules = [];
        foreach ($issues as $issue) {
            if (in_array($issue->rule, $failOn, true)) {
                $issue = $issue->asError();
            }
            $counts[$issue->severity]++;
            $rules[] = $issue->rule;
            $this->report($io, $project, $issue, $format);
        }
        $this->hints($io, $args, $rules);

        $summary = sprintf('%d error(s), %d warning(s).', $counts[Issue::ERROR], $counts[Issue::WARNING]);
        if ($counts[Issue::ERROR] || ($args->getOption('strict') && $counts[Issue::WARNING])) {
            $io->error($summary);

            return static::CODE_ERROR;
        }
        $io->success($summary);

        return static::CODE_SUCCESS;
    }

    /**
     * @return array<\CakeI18nLint\Lint\Issue>
     */
    protected function collect(Project $project): array
    {
        $linter = new Linter();
        $issues = [];
        $templates = [];
        foreach ($project->templates() as $domain => $file) {
            $catalog = $this->parse($file, $issues);
            if ($catalog !== null) {
                $templates[$domain] = $catalog;
                array_push($issues, ...$linter->lintPot($file, $catalog));
            }
        }
        foreach ($project->translations() as $translation) {
            $catalog = $this->parse($translation['path'], $issues);
            if ($catalog !== null) {
                array_push($issues, ...$linter->lintPo(
                    $translation['path'],
                    $catalog,
                    $translation['locale'],
                    $templates[$translation['domain']] ?? null,
                ));
            }
        }

        return $issues;
    }

    /**
     * @param string $file
     * @param array<\CakeI18nLint\Lint\Issue> $issues
     */
    protected function parse(string $file, array &$issues): ?Catalog
    {
        $content = (string)file_get_contents($file);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $issues[] = new Issue($file, 0, Issue::ERROR, 'po.encoding', 'File is not valid UTF-8.');

            return null;
        }
        try {
            return (new Parser())->parse($content);
        } catch (ParseException $e) {
            $issues[] = new Issue($file, $e->poLine, Issue::ERROR, 'po.syntax', $e->getMessage());

            return null;
        }
    }
}
