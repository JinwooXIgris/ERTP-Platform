<?php
require_once __DIR__ . '/response.php';

final class Validator {
    public function requireMethod(string $method): void {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        if ($requestMethod !== strtoupper($method)) {
            fail("Method not allowed. Use {$method}.", 405);
        }
    }

    public function cleanString(mixed $v, int $min, int $max): ?string {
        if (!is_string($v)) {
            return null;
        }

        $v = trim($v);
        $len = strlen($v);
        if ($len < $min || $len > $max) {
            return null;
        }

        return $v;
    }

    public function validEmail(mixed $v): ?string {
        if (!is_string($v)) {
            return null;
        }

        $v = trim($v);
        $validated = filter_var($v, FILTER_VALIDATE_EMAIL);
        if ($validated === false) {
            return null;
        }

        return strtolower($validated);
    }

    public function validInt(mixed $v, int $min, int $max): ?int {
        if (is_string($v)) {
            $v = trim($v);
            if (!is_numeric($v) || strpos($v, '.') !== false) {
                return null;
            }
            $v = (int)$v;
        } elseif (is_int($v)) {
            // Already integer
        } elseif (is_float($v)) {
            return null;
        } else {
            return null;
        }

        if ($v < $min || $v > $max) {
            return null;
        }

        return $v;
    }

    public function validMoney(mixed $v, string $min, string $max): ?string {
        if (is_string($v)) {
            $v = trim($v);
            if (!is_numeric($v)) {
                return null;
            }
            $v = (float)$v;
        } elseif (is_int($v) || is_float($v)) {
            $v = (float)$v;
        } else {
            return null;
        }

        $minFloat = (float)$min;
        $maxFloat = (float)$max;

        if ($v < $minFloat || $v > $maxFloat) {
            return null;
        }

        return number_format($v, 2, '.', '');
    }
}

function require_method(string $method): void {
    (new Validator())->requireMethod($method);
}

function clean_string(mixed $v, int $min, int $max): ?string {
    return (new Validator())->cleanString($v, $min, $max);
}

function valid_email(mixed $v): ?string {
    return (new Validator())->validEmail($v);
}

function valid_int(mixed $v, int $min, int $max): ?int {
    return (new Validator())->validInt($v, $min, $max);
}

function valid_money(mixed $v, string $min, string $max): ?string {
    return (new Validator())->validMoney($v, $min, $max);
}

