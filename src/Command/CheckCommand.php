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
use CakeI18nLint\Lint\Issue;

/**
 * Fails when the committed POT files no longer match the source code.
 */
class CheckCommand extends AbstractCommand
{
    public static function getDescription(): string
    {
        return 'Check that the POT templates match the source code.';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $this->addFormatOption(parent::buildOptionParser($parser));
    }

    public function execute(): int
    {
        $args = $this->args;
        $io = $this->io;
        $project = $this->project($args, $io);
        if ($project === null) {
            return static::CODE_ERROR;
        }
        $format = (string)$args->getOption('format');

        return $this->withFreshTemplates($project, $io, function (array $diff) use ($project, $io, $format, $args): int {
            $drift = 0;
            $rules = [];
            foreach ($diff['domains'] as $state) {
                if ($state['new']) {
                    $drift++;
                    $rules[] = 'pot.missing-file';
                    $this->report($io, $project, new Issue(
                        $state['target'],
                        0,
                        Issue::ERROR,
                        'pot.missing-file',
                        sprintf('Template does not exist yet (%d strings).', count($state['missing'])),
                    ), $format);

                    continue;
                }
                foreach (['missing' => 'is missing from the template', 'stale' => 'is no longer in the source code'] as $kind => $text) {
                    foreach ($state[$kind] as $msgid) {
                        $drift++;
                        $rules[] = 'pot.' . $kind;
                        $this->report($io, $project, new Issue(
                            $state['target'],
                            0,
                            Issue::ERROR,
                            'pot.' . $kind,
                            sprintf('"%s" %s.', $msgid, $text),
                        ), $format);
                    }
                }
            }
            foreach ($diff['orphans'] as $file) {
                $rules[] = 'pot.orphan';
                $this->report($io, $project, new Issue(
                    $file,
                    0,
                    Issue::WARNING,
                    'pot.orphan',
                    'No strings for this domain in the source code anymore.',
                ), $format);
            }

            $this->hints($io, $args, $rules);
            if ($drift) {
                $io->error(sprintf(
                    '%d difference(s). Run `%s` and commit the result.',
                    $drift,
                    $this->commandLine($args, 'extract'),
                ));

                return static::CODE_ERROR;
            }
            $io->success('POT templates are up to date.');

            return static::CODE_SUCCESS;
        });
    }
}
