<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// --- ADD THIS LINE ---
// Set max execution time to 5 minutes (300 seconds) for ALL requests
ini_set('max_execution_time', 300);

date_default_timezone_set('UTC');

$requestMethod = $_SERVER['REQUEST_METHOD'];
// FIX: Revert to query param routing
$requestPath = isset($_GET['route']) ? $_GET['route'] : '/';

switch ($requestMethod) {
    case 'POST':
        if ($requestPath === '/countries/refresh') {
            require_once "refresh.php";
            handleRefresh($pdo);
        } else {
            sendJsonResponse(['error' => 'Not Found', 'path_requested' => $requestPath], 404);
        }
        break;

    case 'GET':
        if ($requestPath === '/') {
            sendJsonResponse([
                'status' => 'success',
                'message' => 'HNG Stage 2 API is live and running.',
                'github_repo' => 'https://github.com/Yiranubari/Task2'
            ], 200);
        } else if ($requestPath === '/status') {
            // FIX: This logic is now safe and handles empty databases
            try {
                $totalResult = $pdo->query("SELECT COUNT(*) as total FROM countries")->fetch();
                $statusResult = $pdo->query("SELECT last_refreshed_at FROM status WHERE id = 1")->fetch();

                sendJsonResponse([
                    'total_countries' => $totalResult ? (int)$totalResult['total'] : 0,
                    'last_refreshed_at' => $statusResult ? $statusResult['last_refreshed_at'] : null
                ], 200);
            } catch (PDOException $e) {
                sendJsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
            }
        
        } else if ($requestPath === '/countries/image') {
            require_once __DIR__ . '/image.php';
            generateSummaryImage($pdo);
        
        } else if ($requestPath === '/countries') {
            try {
                $sql = "SELECT * FROM countries";
                $params = [];
                $whereClauses = [];

                if (isset($_GET['region'])) {
                    $whereClauses[] = "region = ?";
                    $params[] = $_GET['region'];
                }
                if (isset($_GET['currency'])) {
                    $whereClauses[] = "currency_code = ?";
                    $params[] = $_GET['currency'];
                }
                if (count($whereClauses) > 0) {
                    $sql .= " WHERE " . implode(" AND ", $whereClauses);
                }
                if (isset($_GET['sort'])) {
                    if ($_GET['sort'] === 'gdp_desc') {
                        $sql .= " ORDER BY estimated_gdp DESC";
                    }
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $countries = $stmt->fetchAll();

                // FIX: Send empty array instead of 404 if no countries match
                sendJsonResponse($countries, 200);

            } catch (PDOException $e) {
                sendJsonResponse(['error's => 'Database error', 'details' => $e->getMessage()], 500);
            }
        } else if (preg_match('/^\/countries\/(.+)$/', $requestPath, $matches)) {
            try {
                $countryName = urldecode($matches[1]);
                $sql = "SELECT * FROM countries WHERE name = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$countryName]);
                $country = $stmt->fetch();

                if ($country) {
                    sendJsonResponse($country, 200);
                } else {
                    sendJsonResponse(['error' => 'Country not found'], 404);
                }
            } catch (PDOException $e) {
                sendJsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
            }
        } else {
            sendJsonResponse(['error' => 'Not Found', 'path_requested' => $requestPath], 404);
        }
        break;

    case 'DELETE':
        if (preg_match('/^\/countries\/(.+)$/', $requestPath, $matches)) {
            try {
                $countryName = urldecode($matches[1]);
                $sql = "DELETE FROM countries WHERE name = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$countryName]);

                if ($stmt->rowCount() > 0) {
                    sendJsonResponse(['message' => 'Country deleted successfully'], 200);
                } else {
                    sendJsonResponse(['error' => 'Country not found'], 404);
                }
            } catch (PDOException $e) {
                sendJsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
            }
        } else {
            sendJsonResponse(['error' => 'Not Found', 'path_requested' => $requestPath], 404);
        }
        break;

    default:
        sendJsonResponse(['error' => 'Method Not Allowed', 'method' => $requestMethod], 405);
        break;
}