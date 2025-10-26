<?php

/**
 * Sends a JSON response.
 *
 * @param mixed $data The data to encode as JSON.
 * @param int $statusCode The HTTP status code to send.
 * @return void
 */
function sendJsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
}
