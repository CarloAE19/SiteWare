<?php
// Function to read the .env file and inject into $_ENV
function loadEnv($filePath) {
    // MODIFIED: On Vercel, the .env file won't exist. 
    // We check if it exists; if not, we just return and let Vercel's system variables take over.
    if (!file_exists($filePath)) {
        return; 
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        // Ensure the line contains an '=' before exploding to avoid errors
        if (strpos($line, '=') !== false) {
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
}

loadEnv(__DIR__ . '/../.env');
?>
