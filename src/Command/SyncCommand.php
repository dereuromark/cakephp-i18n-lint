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
use Cake\Utility\Filesystem;
use CakeI18nLint\Po\ParseException;
use CakeI18nLint\Po\Parser;
use CakeI18nLint\Syncer;

/**
 * Brings existing PO files in line with their POT template.
 */
class SyncCommand extends AbstractCommand
{
    public static function getDescription(): string
    {
        return 'Add new and drop removed strings in the PO files, based on the POT templates.';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->addOption('dry-run', [
                'help' => 'Report what would change and fail if anything would.',
                'boolean' => true,
            ])
            ->addOption('keep-obsolete', [
                'help' => 'Keep translations whose string left the template.',
                'boolean' => true,
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
        $dryRun = (bool)$args->getOption('dry-run');
        $keepObsolete = (bool)$args->getOption('keep-obsolete');

        $parser = new Parser();
        $syncer = new Syncer();
        $filesystem = new Filesystem();
        $templates = $project->templates();
        $changed = 0;
        $failed = false;
        foreach ($project->translations() as $translation) {
            $file = $project->relative($translation['path']);
            if (!isset($templates[$translation['domain']])) {
                $io->verbose(sprintf('%s has no template, skipped.', $file));

                continue;
            }
            try {
                $po = $parser->parseFile($translation['path']);
                $pot = $parser->parseFile($templates[$translation['domain']]);
            } catch (ParseException $e) {
                $io->error(sprintf('%s: %s (line %d)', $file, $e->getMessage(), $e->poLine));
                $failed = true;

                continue;
            }
            $result = $syncer->sync($po, $pot, $keepObsolete);
            if (!$result['added'] && !$result['removed']) {
                $io->verbose(sprintf('%s is in sync.', $file));

                continue;
            }
            $changed++;
            $io->out(sprintf(
                '%s: %d to add, %d to remove.',
                $file,
                count($result['added']),
                count($result['removed']),
            ));
            foreach ($result['added'] as $msgid) {
                $io->verbose('  + ' . $msgid);
            }
            foreach ($result['removed'] as $msgid) {
                $io->verbose('  - ' . $msgid);
            }
            if (!$dryRun) {
                $filesystem->dumpFile($translation['path'], $po->toString());
            }
        }

        if ($failed) {
            return static::CODE_ERROR;
        }
        if (!$changed) {
            $io->success('PO files are in sync with their templates.');

            return static::CODE_SUCCESS;
        }
        if ($dryRun) {
            $io->error(sprintf('%d PO file(s) out of sync. Run `%s`.', $changed, $this->commandLine($args, 'sync')));

            return static::CODE_ERROR;
        }
        $io->success(sprintf('%d PO file(s) updated.', $changed));

        return static::CODE_SUCCESS;
    }
}
