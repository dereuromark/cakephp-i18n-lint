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
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use I18nLint\Lint\Issue;

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

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $project = $this->project($args, $io);
        if ($project === null) {
            return static::CODE_ERROR;
        }
        $format = (string)$args->getOption('format');

        return $this->withFreshTemplates($project, $io, function (array $diff) use ($project, $io, $format): int {
            $drift = 0;
            foreach ($diff['domains'] as $state) {
                if ($state['new']) {
                    $drift++;
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
                $this->report($io, $project, new Issue(
                    $file,
                    0,
                    Issue::WARNING,
                    'pot.orphan',
                    'No strings for this domain in the source code anymore.',
                ), $format);
            }

            if ($drift) {
                $io->error(sprintf('%d difference(s). Run `i18n-lint extract` and commit the result.', $drift));

                return static::CODE_ERROR;
            }
            $io->success('POT templates are up to date.');

            return static::CODE_SUCCESS;
        });
    }
}
