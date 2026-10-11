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

use Cake\Console\ConsoleIo;
use I18nLint\Command\CheckCommand;
use I18nLint\Command\ExtractCommand;
use I18nLint\Command\LintCommand;
use I18nLint\Command\SyncCommand;

class Runner
{
    /**
     * @var array<string, class-string<\I18nLint\Command\AbstractCommand>>
     */
    public const COMMANDS = [
        'check' => CheckCommand::class,
        'extract' => ExtractCommand::class,
        'sync' => SyncCommand::class,
        'lint' => LintCommand::class,
    ];

    /**
     * @param array<string> $argv Arguments without the script name.
     * @param \Cake\Console\ConsoleIo|null $io
     */
    public function run(array $argv, ?ConsoleIo $io = null): int
    {
        $io ??= new ConsoleIo();
        $name = array_shift($argv);
        if ($name === null || in_array($name, ['help', '--help', '-h'], true)) {
            $this->usage($io);

            return 0;
        }
        if (!isset(static::COMMANDS[$name])) {
            $io->error(sprintf('Unknown command `%s`.', $name));
            $this->usage($io);

            return 1;
        }

        $class = static::COMMANDS[$name];
        $command = new $class();
        $command->setName('i18n-lint ' . $name);

        return (int)$command->run($argv, $io);
    }

    protected function usage(ConsoleIo $io): void
    {
        $io->out('Usage: i18n-lint <command> [options]');
        $io->out('');
        foreach (static::COMMANDS as $name => $class) {
            $io->out(sprintf('  %-8s %s', $name, $class::getDescription()));
        }
        $io->out('');
        $io->out('Run `i18n-lint <command> --help` for the options of a command.');
    }
}
