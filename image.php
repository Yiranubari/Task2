<?php

function generateSummaryImage($pdo)
{
    try {
        // --- 1. Fetch data from the database ---
        $totalCountries = $pdo->query("SELECT COUNT(*) as total FROM countries")->fetch()['total'];
        $lastRefreshed = $pdo->query("SELECT last_refreshed_at FROM status WHERE id = 1")->fetch()['last_refreshed_at'];
        $topCountries = $pdo->query("SELECT name, estimated_gdp FROM countries WHERE estimated_gdp IS NOT NULL ORDER BY estimated_gdp DESC LIMIT 5")->fetchAll();

        // --- 2. Create the image canvas ---
        $width = 500;
        $height = 300;
        $image = imagecreate($width, $height);

        // --- 3. Allocate colors ---
        $bgColor = imagecolorallocate($image, 22, 22, 22); // Dark background
        $textColor = imagecolorallocate($image, 255, 255, 255); // White text
        $titleColor = imagecolorallocate($image, 70, 200, 255); // Light blue for title
        $gdpColor = imagecolorallocate($image, 100, 255, 100); // Light green for GDP
        imagefill($image, 0, 0, $bgColor);

        // --- 4. Draw the text ---
        $lineHeight = 20;
        $currentLine = 10;
        imagestring($image, 5, 10, $currentLine, "Country API Status Summary", $titleColor);
        $currentLine += ($lineHeight * 2);
        imagestring($image, 5, 10, $currentLine, "Total Countries: " . $totalCountries, $textColor);
        $currentLine += $lineHeight;
        imagestring($image, 5, 10, $currentLine, "Last Refresh: " . $lastRefreshed, $textColor);
        $currentLine += ($lineHeight * 2);
        imagestring($image, 5, 10, $currentLine, "Top 5 Countries by GDP:", $titleColor);
        $currentLine += $lineHeight;

        foreach ($topCountries as $index => $country) {
            $rank = $index + 1;
            $gdp = number_format($country['estimated_gdp'], 0);
            imagestring($image, 5, 10, $currentLine, "$rank. {$country['name']}", $textColor);
            imagestring($image, 5, 250, $currentLine, "GDP: $gdp", $gdpColor);
            $currentLine += $lineHeight;
        }

        // --- 5. Output the image directly ---
        header('Content-Type: image/png');
        imagepng($image);

        // --- 6. Clean up memory ---
        imagedestroy($image);
        die; // Stop script

    } catch (Exception $e) {
        // If it fails, create a small error image
        header('Content-Type: image/png');
        $errorImg = imagecreate(300, 50);
        $errBg = imagecolorallocate($errorImg, 255, 0, 0);
        $errText = imagecolorallocate($errorImg, 255, 255, 255);
        imagestring($errorImg, 5, 5, 5, "Error: Failed to gen image", $errText);
        imagepng($errorImg);
        imagedestroy($errorImg);
        die;
    }
}
