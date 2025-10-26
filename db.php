<?php

function loadEnv($path)
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function getDbConnection()
{
    $host = $_ENV['DB_HOST'];
    $dbname = $_ENV['DB_NAME'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASSWORD'];

    // --- THIS IS THE NEW, ROBUST LOGIC ---
    // Build the DSN string
    $dsn = "mysql:host=$host;dbname=$dbname";

    // Check if a port is provided and add it
    if (!empty($_ENV['DB_PORT'])) {
        $port = $_ENV['DB_PORT'];
        $dsn .= ";port=$port";
    }
    // --- END OF NEW LOGIC ---

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

// Load environment variables
loadEnv(__DIR__ . '/.env');

$pdo = null;

try {
    $pdo = getDbConnection();
} catch (\PDOException $e) {
    // Send a consistent JSON error
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    die;
}
