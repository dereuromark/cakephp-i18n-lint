<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace CakeI18nLint\Lint;

class Issue
{
    /**
     * @var string
     */
    public const ERROR = 'error';

    /**
     * @var string
     */
    public const WARNING = 'warning';

    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $severity,
        public readonly string $rule,
        public readonly string $message,
    ) {
    }

    public function asError(): self
    {
        return new self($this->file, $this->line, static::ERROR, $this->rule, $this->message);
    }
}
