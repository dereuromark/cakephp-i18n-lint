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

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIoInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Utility\Filesystem;
use CakeI18nLint\Extractor;
use CakeI18nLint\Lint\Hints;
use CakeI18nLint\Lint\Issue;
use CakeI18nLint\Project;
use CakeI18nLint\TemplateDiffer;
use Closure;
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

    /**
     * How the tool is called from a project root.
     *
     * @var string
     */
    public const BINARY = 'vendor/bin/i18n-lint';

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

    protected function project(Arguments $args, ConsoleIoInterface $io): ?Project
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
     * @param \CakeI18nLint\Project $project
     * @param \Cake\Console\ConsoleIoInterface $io
     * @param \Closure(array{domains: array<string, array{generated: string, target: string, new: bool, missing: array<string>, stale: array<string>}>, orphans: array<string, string>}): T $callback
     *
     * @return T
     */
    protected function withFreshTemplates(Project $project, ConsoleIoInterface $io, Closure $callback): mixed
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
                $io->warning(sprintf(
                    '%d call(s) with a non-literal string cannot be extracted. '
                    . 'List such strings as literals in a file under one of the scanned paths.',
                    $markers,
                ));
            }

            return $callback((new TemplateDiffer())->diff($project, $scratch));
        } finally {
            $filesystem->deleteDir($scratch);
        }
    }

    /**
     * Command line for another subcommand that can be pasted as is, location options included.
     */
    protected function commandLine(Arguments $args, string $command): string
    {
        $line = static::BINARY . ' ' . $command;
        foreach (['root', 'paths', 'locales'] as $option) {
            $value = $this->stringOption($args, $option);
            if ($value !== null) {
                $line .= sprintf(' --%s %s', $option, escapeshellarg($value));
            }
        }

        return $line;
    }

    /**
     * @param \Cake\Console\ConsoleIoInterface $io
     * @param \Cake\Console\Arguments $args
     * @param array<string> $rules Rules that were reported.
     */
    protected function hints(ConsoleIoInterface $io, Arguments $args, array $rules): void
    {
        $hints = Hints::for($rules, $this->commandLine($args, 'sync'), $this->commandLine($args, 'extract'));
        if (!$hints) {
            return;
        }

        $io->out('');
        $io->out('How to fix:');
        foreach ($hints as $rule => $hint) {
            $io->out(sprintf('  [%s] %s', $rule, $hint));
        }
    }

    protected function report(ConsoleIoInterface $io, Project $project, Issue $issue, string $format): void
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
            ), 1, ConsoleIoInterface::QUIET);

            return;
        }

        $io->out(sprintf(
            '%s%s: %s: %s [%s]',
            $file,
            $issue->line > 0 ? ':' . $issue->line : '',
            $issue->severity,
            $issue->message,
            $issue->rule,
        ), 1, ConsoleIoInterface::QUIET);
    }
}
