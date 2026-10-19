<?php

declare(strict_types=1);

namespace App\Database;

/** Splits MySQL scripts without breaking quoted text or DELIMITER trigger bodies. */
final class SqlScript
{
    /** @return list<string> */
    public static function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $delimiter = ';';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; ++$i) {
            $character = $sql[$i];
            if ($quote !== null) {
                $buffer .= $character;
                if ($character === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($character === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if (($i === 0 || $sql[$i - 1] === "\n") && preg_match('/\G[ \t]*DELIMITER[ \t]+(\S+)[^\r\n]*/Ai', $sql, $match, 0, $i)) {
                $delimiter = $match[1];
                $i += strlen($match[0]) - 1;
                continue;
            }
            if ($character === '#' || ($character === '-' && substr($sql, $i, 2) === '--' && preg_match('/\s/', $sql[$i + 2] ?? "\n"))) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $buffer .= "\n";
                continue;
            }
            if (substr($sql, $i, 2) === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new \RuntimeException('Unterminated SQL comment.');
                }
                // MySQL executable comments are retained for native dump compatibility.
                if (($sql[$i + 2] ?? '') === '!') {
                    $buffer .= substr($sql, $i, $end + 2 - $i);
                } else {
                    $buffer .= ' ';
                }
                $i = $end + 1;
                continue;
            }
            if (in_array($character, ["'", '"', '`'], true)) {
                $quote = $character;
                $buffer .= $character;
                continue;
            }
            if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                $i += strlen($delimiter) - 1;
            } else {
                $buffer .= $character;
            }
        }
        if ($quote !== null) {
            throw new \RuntimeException('Unterminated SQL string.');
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
