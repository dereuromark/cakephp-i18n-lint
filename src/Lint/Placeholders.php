<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 *
 * @author Mark Scherer
 * @license https://opensource.org/licenses/MIT MIT License
 */

namespace I18nLint\Lint;

class Placeholders
{
    /**
     * Argument names of an ICU message, e.g. `0` and `name` in `{0} likes {name}`.
     *
     * @return array<string>
     */
    public static function icu(string $message): array
    {
        $names = [];
        $depth = 0;
        $length = strlen($message);
        for ($i = 0; $i < $length; $i++) {
            $char = $message[$i];
            if ($char === "'") {
                $next = $message[$i + 1] ?? '';
                if ($next === "'") {
                    $i++;
                } elseif ($next === '{' || $next === '}') {
                    // Quoted literal runs to the next single apostrophe.
                    $end = strpos($message, "'", $i + 2);
                    $i = $end === false ? $length : $end;
                }

                continue;
            }
            if ($char === '{') {
                $depth++;
                // Arguments sit at odd depths; even depths are plural/select sub-messages.
                if ($depth % 2 === 1 && preg_match('/\G\s*([A-Za-z0-9_]+)\s*[,}]/', $message, $matches, 0, $i + 1)) {
                    $names[$matches[1]] = true;
                }
            } elseif ($char === '}' && $depth > 0) {
                $depth--;
            }
        }
        $names = array_map(fn (int|string $name): string => (string)$name, array_keys($names));
        sort($names);

        return $names;
    }

    /**
     * Conversion type by argument position of a sprintf message, e.g. `[1 => 's', 2 => 'd']`.
     *
     * @return array<int, string>
     */
    public static function printf(string $message): array
    {
        preg_match_all('/%(?:(\d+)[$])?[-+0]*(?:\x27.)?\d*(?:\.\d+)?([bcdeEfFgGosuxX])/', str_replace('%%', '', $message), $matches, PREG_SET_ORDER);
        $arguments = [];
        $next = 1;
        foreach ($matches as $match) {
            $position = $match[1] !== '' ? (int)$match[1] : $next++;
            $arguments[$position] = $match[2];
        }
        ksort($arguments);

        return $arguments;
    }
}
