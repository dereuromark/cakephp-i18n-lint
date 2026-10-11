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
use Cake\Console\ConsoleIo;
use Cake\Utility\Filesystem;

/**
 * Regenerates the POT files, leaving unchanged ones untouched.
 */
class ExtractCommand extends AbstractCommand
{
    public static function getDescription(): string
    {
        return 'Regenerate the POT templates from the source code.';
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $project = $this->project($args, $io);
        if ($project === null) {
            return static::CODE_ERROR;
        }

        return $this->withFreshTemplates($project, $io, function (array $diff) use ($project, $io): int {
            $filesystem = new Filesystem();
            foreach ($diff['domains'] as $domain => $state) {
                $file = $project->relative($state['target']);
                if (!$state['new'] && !$state['missing'] && !$state['stale']) {
                    $io->out(sprintf('%s is up to date.', $file));

                    continue;
                }
                $filesystem->dumpFile($state['target'], (string)file_get_contents($state['generated']));
                $io->success(sprintf(
                    '%s %s: %d added, %d removed.',
                    $file,
                    $state['new'] ? 'created' : 'updated',
                    count($state['missing']),
                    count($state['stale']),
                ));
            }
            foreach ($diff['orphans'] as $file) {
                $io->warning(sprintf('%s has no strings in the source code anymore.', $project->relative($file)));
            }
            if (!$diff['domains']) {
                $io->warning('No translatable strings found.');
            }

            return static::CODE_SUCCESS;
        });
    }
}
