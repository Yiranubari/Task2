<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

date_default_timezone_set('UTC');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestPath = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
$requestPath = isset($_GET['route']) ? $_GET['route'] : '/';

switch ($requestMethod) {
    case 'POST':
        if ($requestPath === '/countries/refresh') {
            require_once "refresh.php";
            handleRefresh($pdo);
        } else {
            // If it's a POST to any other path, it's a 404
            sendJsonResponse(['error' => 'Not Found', 'path_requested' => $requestPath], 404);
        }
        break;

    case 'GET':
        if ($requestPath === '/status') {
            // Logic for status
            try {
                // Get total countries
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM countries");
                $totalCountries = $stmt->fetch()['total'];

                // Get last refresh time
                $stmt = $pdo->query("SELECT last_refreshed_at FROM status WHERE id = 1");
                $lastRefreshed = $stmt->fetch()['last_refreshed_at'];

                // Send the response
                sendJsonResponse([
                    'total_countries' => (int)$totalCountries,
                    'last_refreshed_at' => $lastRefreshed
                ], 200);
            } catch (PDOException $e) {
                sendJsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
            }

            // --- NEW BLOCK ADDED ---
        } else if ($requestPath === '/countries/image') {
            // Include the image generation logic
            require_once __DIR__ . '/image.php';

            // Call the function to generate and output the image
            // This function now handles its own headers and output
            generateSummaryImage($pdo);
            // --- END OF NEW BLOCK ---

        } else if ($requestPath === '/countries') {
            // Logic for getting all countries with filters
            try {
                $sql = "SELECT * FROM countries";
                $params = [];
                $whereClauses = [];

                // Add region filter
                if (isset($_GET['region'])) {
                    $whereClauses[] = "region = ?";
                    $params[] = $_GET['region'];
                }

                // Add currency filter
                if (isset($_GET['currency'])) {
                    $whereClauses[] = "currency_code = ?";
                    $params[] = $_GET['currency'];
                }

                // Combine WHERE clauses if any
                if (count($whereClauses) > 0) {
                    $sql .= " WHERE " . implode(" AND ", $whereClauses);
                }

                // Add sorting
                if (isset($_GET['sort'])) {
                    if ($_GET['sort'] === 'gdp_desc') {
                        $sql .= " ORDER BY estimated_gdp DESC";
                    }
                    // (You could add more sort options here, e.g., 'name_asc')
                }

                // Prepare and execute the query
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $countries = $stmt->fetchAll();

                sendJsonResponse($countries, 200);
            } catch (PDOException $e) {
                sendJsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
            }
        } else if (preg_match('/^\/countries\/(.+)$/', $requestPath, $matches)) {
            // Logic for getting a single country by name
            try {
                $countryName = urldecode($matches[1]); // Decode URL-encoded characters like %20

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
            // All other GET requests are 404
            sendJsonResponse(['error' => 'Not Found', 'path_requested' => $requestPath], 404);
        }
        break;

    case 'DELETE':
        if (preg_match('/^\/countries\/(.+)$/', $requestPath, $matches)) {
            // Logic for deleting a single country by name
            try {
                $countryName = urldecode($matches[1]);

                $sql = "DELETE FROM countries WHERE name = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$countryName]);

                // Check if any row was actually deleted
                if ($stmt->rowCount() > 0) {
                    sendJsonResponse(['message' => 'Country deleted successfully'], 200);
                } else {
                    sendJsonResponse(['error' => 'Country not found'], 404);
                }
            } catch (PDOException $e) {
                sendJsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
            }
        } else {
            // All other DELETE requests are 404
            sendJsonResponse(['error' => 'Not Found', 'path_requested' => $requestPath], 404);
        }
        break;

    default:
        // Handle other methods like PUT
        sendJsonResponse(['error' => 'Method Not Allowed', 'method' => $requestMethod], 405);
        break;
}
