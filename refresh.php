<?php

/**
 * Fetches the latest exchange rates from the API.
 *
 * @return array|false An array of exchange rates or false on failure.
 */
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

/**
 * Fetches country data from the API.
 *
 * @return array|false An array of country data or false on failure.
 */
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
    // --- THIS IS THE FIX ---
    // Set max execution time to 5 minutes (300 seconds)
    ini_set('max_execution_time', 300);

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

    foreach ($countries as $countryData) {
        try {
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
                // --- BUG FIX HERE ---
                // The API returns an *array* of currencies, we need the first one at index 0
                $currency_code = $countryData['currencies'][0]['code'];

                if ($currency_code !== null && array_key_exists($currency_code, $exchangeRates)) {
                    $exchange_rate = $exchangeRates[$currency_code];
                    $multiplier = rand(1000, 2000);
                    $estimated_gdp = ($population * $multiplier) / $exchange_rate;
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
            $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
            $stmt->execute([$name]);
            $existingCountry = $stmt->fetch();

            if ($existingCountry === false) {
                $stmt = $pdo->prepare(
                    "INSERT INTO countries (name, capital, region, population, currency_code, exchange_rate, estimated_gdp, flag_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$name, $capital, $region, $population, $currency_code, $exchange_rate, $estimated_gdp, $flag]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE countries SET capital = ?, region = ?, population = ?, currency_code = ?, exchange_rate = ?, estimated_gdp = ?, flag_url = ?, last_refreshed_at = CURRENT_TIMESTAMP WHERE name = ?"
                );
                $stmt->execute([$capital, $region, $population, $currency_code, $exchange_rate, $estimated_gdp, $flag, $name]);
            }

            $countriesProcessed++;
        } catch (PDOException $e) {
            error_log("DB error for country: " . $e->getMessage());
            continue;
        }
    }
    $stmt = $pdo->prepare("UPDATE status SET last_refreshed_at = CURRENT_TIMESTAMP WHERE id = 1");
    $stmt->execute();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM countries");
    $stmt->execute();
    $totalCountries = $stmt->fetch();

    // --- NEW LINE ADDED ---
    // Generate the summary image


    sendJsonResponse([
        'status' => 'success',
        'message' => 'Countries refreshed successfully.',
        'total_processed' => $countriesProcessed,
        'total_in_db' => $totalCountries['total']
    ], 200);
}
