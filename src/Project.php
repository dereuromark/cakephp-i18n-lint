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

use InvalidArgumentException;

/**
 * Locations of a plugin or application: what to scan and where the catalogs live.
 */
class Project
{
    /**
     * @var array<string>
     */
    public const DEFAULT_PATHS = ['src', 'templates'];

    /**
     * @var string
     */
    public const DEFAULT_LOCALES = 'resources/locales';

    /**
     * @param string $root Absolute project root.
     * @param array<string> $paths Absolute source paths.
     * @param string $locales Absolute locales directory.
     */
    public function __construct(
        public readonly string $root,
        public readonly array $paths,
        public readonly string $locales,
    ) {
    }

    /**
     * @param string|null $root Defaults to the working directory.
     * @param string|null $paths Comma-separated, relative to the root.
     * @param string|null $locales Relative to the root.
     *
     * @throws \InvalidArgumentException
     */
    public static function create(?string $root, ?string $paths, ?string $locales): self
    {
        $resolved = realpath($root ?? (string)getcwd());
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException(sprintf('Root directory `%s` does not exist.', (string)$root));
        }

        $explicit = $paths !== null;
        $sources = [];
        foreach ($explicit ? explode(',', $paths) : static::DEFAULT_PATHS as $path) {
            $absolute = static::absolute($resolved, trim($path));
            if (is_dir($absolute)) {
                $sources[] = $absolute;
            } elseif ($explicit) {
                throw new InvalidArgumentException(sprintf('Source path `%s` does not exist.', $path));
            }
        }
        if (!$sources) {
            throw new InvalidArgumentException(sprintf('No source directories found in `%s`.', $resolved));
        }

        return new self($resolved, $sources, static::absolute($resolved, $locales ?? static::DEFAULT_LOCALES));
    }

    /**
     * @return array<string, string> POT path by domain.
     */
    public function templates(?string $dir = null): array
    {
        $templates = [];
        foreach (glob(($dir ?? $this->locales) . '/*.pot') ?: [] as $file) {
            $templates[basename($file, '.pot')] = $file;
        }
        ksort($templates);

        return $templates;
    }

    /**
     * @return array<array{locale: string, domain: string, path: string}>
     */
    public function translations(): array
    {
        $translations = [];
        foreach (glob($this->locales . '/*/*.po') ?: [] as $file) {
            $translations[] = [
                'locale' => basename(dirname($file)),
                'domain' => basename($file, '.po'),
                'path' => $file,
            ];
        }

        return $translations;
    }

    public function relative(string $path): string
    {
        $prefix = $this->root . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    protected static function absolute(string $root, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return rtrim($path, '/\\');
        }

        return $root . DIRECTORY_SEPARATOR . trim($path, '/\\');
    }
}
