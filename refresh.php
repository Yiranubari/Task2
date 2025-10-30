<?php
// This file is updated with transaction logic for speed.

require_once __DIR__ . '/image.php';
function fetchExchangeRates()
{
    $url = 'https://open.er-api.com/v6/latest/USD';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return false;
    }

    $data = json_decode($response, true);
    return $data['rates'];
}

function fetchCountries()
{
    $url = 'https://restcountries.com/v2/all?fields=name,capital,region,population,flag,currencies';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return false;
    }

    return json_decode($response, true);
}


function handleRefresh($pdo)
{
    // Set max time for the whole script (Caddyfile/ini.set might be ignored)
    if (function_exists('set_time_limit')) {
        set_time_limit(300); // 5 minutes just in case
    }

    $exchangeRates = fetchExchangeRates();
    if ($exchangeRates === false) {
        sendJsonResponse([
            'error' => 'External data source unavailable',
            'details' => 'Could not fetch data from Exchange Rate API'
        ], 503);
    }

    $countries = fetchCountries();
    if ($countries === false) {
        sendJsonResponse([
            'error' => 'External data source unavailable',
            'details' => 'Could not fetch data from Rest Countries API'
        ], 503);
    }

    $countriesProcessed = 0;

    // --- START OF THE FIX: DATABASE TRANSACTION ---
    $pdo->beginTransaction();
    
    try {
        // Prepare statements *outside* the loop for efficiency
        $selectStmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
        $insertStmt = $pdo->prepare(
            "INSERT INTO countries (name, capital, region, population, currency_code, exchange_rate, estimated_gdp, flag_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $updateStmt = $pdo->prepare(
            "UPDATE countries SET capital = ?, region = ?, population = ?, currency_code = ?, exchange_rate = ?, estimated_gdp = ?, flag_url = ?, last_refreshed_at = CURRENT_TIMESTAMP WHERE name = ?"
        );

        foreach ($countries as $countryData) {
            
            // Reset timer *inside* loop (this was our previous fix and is still good)
            if (function_exists('set_time_limit')) {
                set_time_limit(30);
            }

            $name = $countryData['name'];
            if (empty($name)) {
                continue;
            }

            $population = $countryData['population'];
            $capital = $countryData['capital'] ?? null;
            $region = $countryData['region'] ?? null;
            $flag = $countryData['flag'] ?? null;

            $currency_code = null;
            $exchange_rate = null;
            $estimated_gdp = null;

            if (isset($countryData['currencies']) && is_array($countryData['currencies']) && !empty($countryData['currencies'])) {
                $currency_code = $countryData['currencies'][0]['code'];

                if ($currency_code !== null && array_key_exists($currency_code, $exchangeRates)) {
                    $exchange_rate = $exchangeRates[$currency_code];
                    $multiplier = rand(1000, 2000);
                    if ($exchange_rate != 0) {
                        $estimated_gdp = ($population * $multiplier) / $exchange_rate;
                    } else {
                         $estimated_gdp = 0;
                    }
                } elseif ($currency_code !== null) {
                    $exchange_rate = null;
                    $estimated_gdp = null;
                }
            } else {
                $currency_code = null;
                $exchange_rate = null;
                $estimated_gdp = 0;
            }

            if (empty($name) || $population === null) {
                continue;
            }
            
            $selectStmt->execute([$name]);
            $existingCountry = $selectStmt->fetch();

            if ($existingCountry === false) {
                $insertStmt->execute([$name, $capital, $region, $population, $currency_code, $exchange_rate, $estimated_gdp, $flag]);
            } else {
                $updateStmt->execute([$capital, $region, $population, $currency_code, $exchange_rate, $estimated_gdp, $flag, $name]);
            }

            $countriesProcessed++;
        }

        // --- COMMIT THE TRANSACTION ---
        // All 250 queries are sent to the DB at once here.
        $pdo->commit();

    } catch (Exception $e) {
        // --- ROLLBACK ON FAILURE ---
        $pdo->rollBack();
        error_log("DB transaction error: " . $e->getMessage());
        // Send a 500 error since the refresh failed
        sendJsonResponse(['error' => 'Database transaction failed', 'details' => $e->getMessage()], 500);
    }
    // --- END OF THE FIX ---
    
    $stmt = $pdo->prepare("UPDATE status SET last_refreshed_at = CURRENT_TIMESTAMP WHERE id = 1");
    $stmt->execute();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM countries");
    $stmt->execute();
    $totalCountries = $stmt->fetch();


    sendJsonResponse([
        'status' => 'success',
        'message' => 'Countries refreshed successfully.',
        'total_processed' => $countriesProcessed,
        'total_in_db' => $totalCountries['total']
    ], 200);
}