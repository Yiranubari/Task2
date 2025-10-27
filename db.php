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
    // --- THIS IS THE FIX ---
    // Use getenv() which is guaranteed to work on Railway
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');
    $port = getenv('DB_PORT');
    // --- END OF FIX ---

    $dsn = "mysql:host=$host;dbname=$dbname";
    if (!empty($port)) {
        $dsn .= ";port=$port";
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA    => __DIR__ . '/ca.pem',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}

// Load environment variables (for local .env file)
loadEnv(__DIR__ . '/.env');

$pdo = null;

try {
    $pdo = getDbConnection();
} catch (\PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    die;
}
