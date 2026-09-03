<?php

function load_env(string $path): void
{
    static $loaded = [];
    if (isset($loaded[$path]) || !is_file($path)) {
        return;
    }
    $loaded[$path] = true;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (strlen($value) >= 2) {
            $isQuoted = ($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'");
            if ($isQuoted) {
                $value = substr($value, 1, -1);
            }
        }

        if (getenv($name) === false) {
            putenv("$name=$value");
        }
        $_ENV[$name] = $value;
    }
}

function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false ? $default : $value;
}
