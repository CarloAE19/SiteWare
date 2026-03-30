<?php
// Function to read the .env file and inject into $_ENV
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("Critical Error: .env file is missing. The system cannot start securely.");
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {

        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;

        // Split "KEY=VALUE"
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Inject into PHP's global environment variables
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
// Automatically execute the loader when this file is included
// Adjust the path so it looks for .env in the root folder
loadEnv(__DIR__ . '/../.env');
?>
