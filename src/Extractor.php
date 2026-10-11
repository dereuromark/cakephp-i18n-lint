<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace CakeI18nLint;

use Cake\Command\I18nExtractCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Controller\Controller;
use Cake\View\View;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Runs CakePHP's own `i18n extract` without a booted application.
 */
class Extractor
{
    /**
     * Host application classes a plugin commonly extends but cannot autoload on its own.
     *
     * @var array<string, class-string>
     */
    protected const APP_CLASS_FALLBACKS = [
        'App\Controller\AppController' => Controller::class,
        'App\View\AppView' => View::class,
    ];

    protected static bool $autoloaderRegistered = false;

    /**
     * @param string $root Project root.
     * @param array<string> $paths Absolute source paths.
     * @param string $outputDir Directory the POT files are written to.
     *
     * @return array<string> Diagnostic lines reported by the extractor.
     */
    public function extract(string $root, array $paths, string $outputDir): array
    {
        $this->prepareEnvironment($root);

        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $command = new I18nExtractCommand();
        try {
            $code = $command->run([
                '--paths', implode(',', $paths),
                '--output', $outputDir,
                '--extract-core', 'no',
                '--merge', 'no',
                '--overwrite',
                '--no-location',
                '--marker-error',
            ], new ConsoleIo($out, $err));
        } catch (Throwable $e) {
            throw new RuntimeException('String extraction failed: ' . $e->getMessage(), 0, $e);
        }

        $diagnostics = array_values(array_filter(
            array_map(trim(...), $err->messages()),
            fn (string $line): bool => $line !== '',
        ));
        if ($code !== I18nExtractCommand::CODE_SUCCESS) {
            throw new RuntimeException("String extraction failed:\n" . implode("\n", $diagnostics));
        }

        return $diagnostics;
    }

    protected function prepareEnvironment(string $root): void
    {
        // The command reads these even when explicit paths are given.
        if (!defined('ROOT')) {
            define('ROOT', $root);
        }
        if (!defined('APP')) {
            define('APP', $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR);
        }
        if (!defined('CAKE_CORE_INCLUDE_PATH')) {
            $file = (string)(new ReflectionClass(I18nExtractCommand::class))->getFileName();
            define('CAKE_CORE_INCLUDE_PATH', dirname($file, 3));
        }

        if (static::$autoloaderRegistered) {
            return;
        }
        static::$autoloaderRegistered = true;
        // CakePHP 5.4+ reflects every scanned class, which autoloads its parents.
        spl_autoload_register(function (string $class): void {
            if (isset(static::APP_CLASS_FALLBACKS[$class])) {
                class_alias(static::APP_CLASS_FALLBACKS[$class], $class);
            }
        });
    }
}
