<?php

final class Response {
    public static function json(
        bool $success,
        string $message,
        array $data = [],
        array $errors = [],
        int $status = 200
    ): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
        ];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(
        string $message,
        array $data = [],
        int $status = 200
    ): never {
        self::json(true, $message, $data, [], $status);
    }

    public static function fail(
        string $message,
        int $status,
        array $errors = [],
        array $data = []
    ): never {
        self::json(false, $message, $data, $errors, $status);
    }

    public static function readJson(): array {
        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return [];
        }

        $data = json_decode($input, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            self::fail('Invalid JSON in request body', 400);
        }

        return is_array($data) ? $data : [];
    }

    public static function logError(Throwable|string $error): void {
        $message = is_string($error) ? $error : ($error->getMessage() . "\n" . $error->getTraceAsString());
        if (defined('LOG_DIR') && is_dir(LOG_DIR) && is_writable(LOG_DIR)) {
            $logFile = rtrim(LOG_DIR, '/\\') . '/app.log';
            $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
            @file_put_contents($logFile, $entry, FILE_APPEND);
        } else {
            error_log($message);
        }
    }
}

set_exception_handler(function (Throwable $e): never {
    Response::logError($e);
    Response::fail('Something went wrong', 500);
});

function json_response(
    bool $success,
    string $message,
    array $data = [],
    array $errors = [],
    int $status = 200
): never {
    Response::json($success, $message, $data, $errors, $status);
}

function ok(
    string $message,
    array $data = [],
    int $status = 200
): never {
    Response::ok($message, $data, $status);
}

function fail(
    string $message,
    int $status,
    array $errors = [],
    array $data = []
): never {
    Response::fail($message, $status, $errors, $data);
}

function read_json(): array {
    return Response::readJson();
}

