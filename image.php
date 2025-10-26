<?php

function generateSummaryImage($pdo)
{
    try {
        // --- 1. Create the cache directory if it doesn't exist ---
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $filePath = $cacheDir . '/summary.png';

        // --- 2. Fetch data from the database ---
        $totalCountries = $pdo->query("SELECT COUNT(*) as total FROM countries")->fetch()['total'];
        $lastRefreshed = $pdo->query("SELECT last_refreshed_at FROM status WHERE id = 1")->fetch()['last_refreshed_at'];
        $topCountries = $pdo->query("SELECT name, estimated_gdp FROM countries WHERE estimated_gdp IS NOT NULL ORDER BY estimated_gdp DESC LIMIT 5")->fetchAll();

        // --- 3. Create the image canvas ---
        $width = 500;
        $height = 300;
        $image = imagecreate($width, $height);

        // --- 4. Allocate colors ---
        $bgColor = imagecolorallocate($image, 22, 22, 22); // Dark background
        $textColor = imagecolorallocate($image, 255, 255, 255); // White text
        $titleColor = imagecolorallocate($image, 70, 200, 255); // Light blue for title
        $gdpColor = imagecolorallocate($image, 100, 255, 100); // Light green for GDP

        imagefill($image, 0, 0, $bgColor);

        // --- 5. Draw the text ---
        // We use imagestring() which is built-in. 5 is the largest default font size.
        $lineHeight = 20; // Height of one line of text
        $currentLine = 10; // Start 10px from the top

        imagestring($image, 5, 10, $currentLine, "Country API Status Summary", $titleColor);
        $currentLine += ($lineHeight * 2); // Add extra space

        imagestring($image, 5, 10, $currentLine, "Total Countries: " . $totalCountries, $textColor);
        $currentLine += $lineHeight;

        imagestring($image, 5, 10, $currentLine, "Last Refresh: " . $lastRefreshed, $textColor);
        $currentLine += ($lineHeight * 2);

        imagestring($image, 5, 10, $currentLine, "Top 5 Countries by GDP:", $titleColor);
        $currentLine += $lineHeight;

        foreach ($topCountries as $index => $country) {
            $rank = $index + 1;
            $gdp = number_format($country['estimated_gdp'], 0); // Format GDP to be readable
            imagestring($image, 5, 10, $currentLine, "$rank. {$country['name']}", $textColor);
            imagestring($image, 5, 250, $currentLine, "GDP: $gdp", $gdpColor);
            $currentLine += $lineHeight;
        }

        // --- 6. Save the image to the file ---
        imagepng($image, $filePath);

        // --- 7. Clean up memory ---
        imagedestroy($image);

        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Failed to generate image: " . $e->getMessage());
        return false;
    }
}
