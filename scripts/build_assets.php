<?php
/**
 * GB Construction & Enterprise Inc. - CIMS
 * Pure PHP Asset Minifier (Fallback for environments without Node.js)
 */

$cssDir = __DIR__ . '/../assets/css';
$jsDir  = __DIR__ . '/../assets/js';

function strip_css_comments_and_whitespace($css) {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Remove spaces around separators
    $css = preg_replace('/\s*([\{\};:,])\s*/', '$1', $css);
    // Remove extra whitespace
    $css = preg_replace('/\s+/', ' ', $css);
    return trim($css);
}

function strip_js_comments($js) {
    // Strip multi-line and single-line comments safely while preserving strings
    $pattern = '/(([\'"`])(?:\\\\.|(?!\2)[^\\\\])*\2)|(\/\/.*$)|(\/\*[\s\S]*?\*\/)/m';
    return preg_replace_callback($pattern, function($m) {
        // If it's a quoted string, return as-is
        if (!empty($m[1])) {
            return $m[1];
        }
        // Otherwise, it was a comment -> strip it
        return '';
    }, $js);
}

echo "\n--- Building Assets via PHP ---\n";

if (is_dir($cssDir)) {
    foreach (glob($cssDir . '/*.css') as $file) {
        if (str_ends_with($file, '.min.css')) continue;
        $content = file_get_contents($file);
        $minified = strip_css_comments_and_whitespace($content);
        $outFile = preg_replace('/\.css$/i', '.min.css', $file);
        file_put_contents($outFile, $minified);
        echo "[CSS] " . basename($file) . " -> " . basename($outFile) . "\n";
    }
}

if (is_dir($jsDir)) {
    foreach (glob($jsDir . '/*.js') as $file) {
        if (str_ends_with($file, '.min.js')) continue;
        $content = file_get_contents($file);
        $minified = strip_js_comments($content);
        $outFile = preg_replace('/\.js$/i', '.min.js', $file);
        file_put_contents($outFile, $minified);
        echo "[JS]  " . basename($file) . " -> " . basename($outFile) . "\n";
    }
}

echo "--- PHP Build Complete ---\n\n";
