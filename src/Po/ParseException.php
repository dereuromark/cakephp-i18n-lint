<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace I18nLint\Po;

use RuntimeException;

class ParseException extends RuntimeException
{
    public function __construct(string $message, public readonly int $poLine)
    {
        parent::__construct($message);
    }
}
