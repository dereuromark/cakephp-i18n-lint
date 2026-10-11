<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace I18nLint\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Utility\Filesystem;
use Closure;
use I18nLint\Extractor;
use I18nLint\Lint\Issue;
use I18nLint\Project;
use I18nLint\TemplateDiffer;
use InvalidArgumentException;

abstract class AbstractCommand extends BaseCommand
{
    /**
     * @var string
     */
    public const FORMAT_TEXT = 'text';

    /**
     * @var string
     */
    public const FORMAT_GITHUB = 'github';

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->addOption('root', [
                'help' => 'Plugin or application root. Defaults to the working directory.',
            ])
            ->addOption('paths', [
                'help' => 'Comma-separated source paths relative to the root. Defaults to `src,templates`.',
            ])
            ->addOption('locales', [
                'help' => 'Locales directory relative to the root. Defaults to `resources/locales`.',
            ]);
    }

    protected function addFormatOption(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->addOption('format', [
            'help' => 'Output format. `github` emits workflow annotations.',
            'choices' => [static::FORMAT_TEXT, static::FORMAT_GITHUB],
            'default' => getenv('GITHUB_ACTIONS') === 'true' ? static::FORMAT_GITHUB : static::FORMAT_TEXT,
        ]);
    }

    protected function project(Arguments $args, ConsoleIo $io): ?Project
    {
        try {
            return Project::create(
                $this->stringOption($args, 'root'),
                $this->stringOption($args, 'paths'),
                $this->stringOption($args, 'locales'),
            );
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return null;
        }
    }

    protected function stringOption(Arguments $args, string $name): ?string
    {
        $value = $args->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Extracts into a scratch directory and hands the comparison to the callback.
     *
     * @template T
     *
     * @param \I18nLint\Project $project
     * @param \Cake\Console\ConsoleIo $io
     * @param \Closure(array{domains: array<string, array{generated: string, target: string, new: bool, missing: array<string>, stale: array<string>}>, orphans: array<string, string>}): T $callback
     *
     * @return T
     */
    protected function withFreshTemplates(Project $project, ConsoleIo $io, Closure $callback): mixed
    {
        $scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'i18n-lint-' . bin2hex(random_bytes(6));
        $filesystem = new Filesystem();
        $filesystem->mkdir($scratch);
        try {
            $diagnostics = (new Extractor())->extract($project->root, $project->paths, $scratch);
            $markers = 0;
            foreach ($diagnostics as $line) {
                $line = str_replace($project->root . DIRECTORY_SEPARATOR, '', $line);
                // Reflection noise stays behind -v; a marker error means a string was skipped.
                if (str_contains($line, 'Invalid marker content')) {
                    $markers++;
                    $io->warning($line);
                } else {
                    $io->verbose($line);
                }
            }
            if ($markers) {
                $io->warning(sprintf('%d call(s) with a non-literal string cannot be extracted.', $markers));
            }

            return $callback((new TemplateDiffer())->diff($project, $scratch));
        } finally {
            $filesystem->deleteDir($scratch);
        }
    }

    protected function report(ConsoleIo $io, Project $project, Issue $issue, string $format): void
    {
        $file = $project->relative($issue->file);
        if ($format === static::FORMAT_GITHUB) {
            $io->out(sprintf(
                '::%s file=%s%s,title=%s::%s',
                $issue->severity,
                $file,
                $issue->line > 0 ? ',line=' . $issue->line : '',
                $issue->rule,
                str_replace(["\r", "\n"], ['%0D', '%0A'], $issue->message),
            ), 1, ConsoleIo::QUIET);

            return;
        }

        $io->out(sprintf(
            '%s%s: %s: %s [%s]',
            $file,
            $issue->line > 0 ? ':' . $issue->line : '',
            $issue->severity,
            $issue->message,
            $issue->rule,
        ), 1, ConsoleIo::QUIET);
    }
}
