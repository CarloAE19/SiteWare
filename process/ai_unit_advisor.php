<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized session. Please log in.']);
    exit;
}

require_once __DIR__ . '/../Connection/db.php';

// Rate Limiting: Max 20 requests per 60 seconds per user
$rateLimit = check_rate_limit('ai_unit_advisor_' . $_SESSION['user_id'], 20, 60);
if (!$rateLimit['allowed']) {
    http_response_code(429);
    echo json_encode(['error' => "Rate limit reached. Please wait {$rateLimit['retry_after']}s before trying again."]);
    exit;
}
record_rate_limit_attempt('ai_unit_advisor_' . $_SESSION['user_id']);

// Get Input Data
$inputJSON = file_get_contents('php://input');
$requestData = json_decode($inputJSON, true) ?? $_POST;
$inputText = trim($requestData['input_text'] ?? '');
$category = trim($requestData['category'] ?? '');

if (empty($inputText)) {
    echo json_encode(['error' => 'Please provide a unit name or material description.']);
    exit;
}

// Fallback Heuristics in case AI is offline / unconfigured
function getLocalFallback($name)
{
    $n = strtolower(trim($name));
    $rules = [
        ['keywords' => ['cubic meter', 'cu.m', 'cubic yard', 'cu.yd', 'cubic feet', 'cu.ft', 'sand', 'gravel', 'aggregate', 'soil'], 'unit' => 'Cubic Meters', 'abbrev' => 'cu.m', 'level' => 5, 'reason' => 'Bulk volume materials are typically ordered in bulk batches.', 'decimals' => true],
        ['keywords' => ['kilogram', 'kilo', 'kg', 'ton', 'tonne', 'cement additive', 'bentonite', 'nails', 'wire'], 'unit' => 'Kilograms', 'abbrev' => 'kg', 'level' => 20, 'reason' => 'Weight-based construction supply standard.', 'decimals' => true],
        ['keywords' => ['liter', 'litre', 'gallon', 'drum', 'barrel', 'paint', 'thinner', 'epoxy', 'sealer', 'diesel', 'fuel'], 'unit' => 'Liters', 'abbrev' => 'L', 'level' => 5, 'reason' => 'Liquid and chemical finishing supplies with moderate daily usage.', 'decimals' => true],
        ['keywords' => ['meter', 'metre', 'linear meter', 'lm', 'feet', 'foot', 'rebar', 'pipe', 'pvc', 'cable', 'wire'], 'unit' => 'Meters', 'abbrev' => 'm', 'level' => 25, 'reason' => 'Continuous linear material; higher threshold avoids mid-job interruptions.', 'decimals' => true],
        ['keywords' => ['bag', 'sack', 'cement', 'portland', 'mortar', 'grout', 'plaster'], 'unit' => 'Bags', 'abbrev' => 'bg', 'level' => 20, 'reason' => 'High-consumption foundational building supply.', 'decimals' => false],
        ['keywords' => ['box', 'pack', 'carton', 'case', 'screw', 'fastener', 'anchor', 'washer', 'bolt'], 'unit' => 'Boxes', 'abbrev' => 'bx', 'level' => 10, 'reason' => 'Packaged consumables; 10 boxes maintains continuous trades work.', 'decimals' => false],
        ['keywords' => ['sheet', 'plywood', 'drywall', 'gypsum', 'metal deck', 'corrugated'], 'unit' => 'Sheets', 'abbrev' => 'sht', 'level' => 15, 'reason' => 'Board and sheet materials for formwork and partitioning.', 'decimals' => false],
        ['keywords' => ['board foot', 'board feet', 'bdft', 'lumber', 'wood', 'timber'], 'unit' => 'Board Feet', 'abbrev' => 'bdft', 'level' => 30, 'reason' => 'Standard lumber volume measurement.', 'decimals' => false],
        ['keywords' => ['roll', 'geomembrane', 'insulation', 'mesh', 'tape'], 'unit' => 'Rolls', 'abbrev' => 'rl', 'level' => 5, 'reason' => 'Long continuous coverage rolls.', 'decimals' => false],
        ['keywords' => ['piece', 'unit', 'tool', 'equipment', 'scaffold', 'valve', 'fitting'], 'unit' => 'Pieces', 'abbrev' => 'pcs', 'level' => 10, 'reason' => 'Discrete countable units.', 'decimals' => false],
    ];

    foreach ($rules as $r) {
        foreach ($r['keywords'] as $kw) {
            if (strpos($n, $kw) !== false) {
                return [
                    'unit_name' => $r['unit'],
                    'abbreviation' => $r['abbrev'],
                    'reorder_level' => $r['level'],
                    'rationale' => $r['reason'] . ' (Standard Rule)',
                    'allow_decimals' => $r['decimals'],
                    'source' => 'heuristic'
                ];
            }
        }
    }

    return [
        'unit_name' => ucwords($name),
        'abbreviation' => 'pcs',
        'reorder_level' => 10,
        'rationale' => 'General countable construction item.',
        'allow_decimals' => false,
        'source' => 'default'
    ];
}

// If AI_API_KEY is not defined, return fallback immediately
if (!defined('AI_API_KEY') || empty(AI_API_KEY)) {
    echo json_encode(getLocalFallback($inputText));
    exit;
}

$apiKey = AI_API_KEY;
$model = defined('AI_MODEL') ? AI_MODEL : 'meta/llama-3.1-8b-instruct';
$isNvidia = (strpos($apiKey, 'nvapi-') === 0) || defined('AI_MODEL');

$systemPrompt = "You are a professional Construction Logistics & Inventory Management AI advisor for a commercial building contractor.
Given a material or unit description, return an accurate JSON object indicating the standard construction Unit of Measurement (UOM), its standard abbreviation, a recommended low-stock reorder alert threshold level (an integer >= 1) based on typical jobsite consumption velocity and supplier lead times, a concise 1-sentence rationale, and whether the unit supports decimals.

CRITICAL: Return ONLY valid JSON in this exact structure with NO markdown fences, no formatting backticks, and no extra commentary:
{
  \"unit_name\": \"Full Unit Name (e.g. Cubic Meters, Bags, Pieces, Kilograms)\",
  \"abbreviation\": \"Standard abbreviation (e.g. cu.m, bg, pcs, kg)\",
  \"reorder_level\": 15,
  \"rationale\": \"Concise 1-sentence engineering reason for this safety threshold.\",
  \"allow_decimals\": true
}";

$userQuery = "Material / Unit input: \"{$inputText}\"" . (!empty($category) ? " (Category: \"{$category}\")" : "");

try {
    if ($isNvidia) {
        $apiUrl = "https://integrate.api.nvidia.com/v1/chat/completions";
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userQuery]
            ],
            'temperature' => 0.1,
            'max_tokens' => 256
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
    } else {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\n" . $userQuery]]]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 256,
                'responseMimeType' => 'application/json'
            ]
        ];
        $headers = [
            'Content-Type: application/json'
        ];
    }

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true,
            'timeout' => 8
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ];

    $context = stream_context_create($options);
    $rawResponse = @file_get_contents($apiUrl, false, $context);

    if ($rawResponse === false) {
        echo json_encode(getLocalFallback($inputText));
        exit;
    }

    $responseData = json_decode($rawResponse, true);
    $textOutput = '';

    if ($isNvidia) {
        $textOutput = $responseData['choices'][0]['message']['content'] ?? '';
    } else {
        $textOutput = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    if (empty($textOutput)) {
        echo json_encode(getLocalFallback($inputText));
        exit;
    }

    // Clean any surrounding backticks/markdown if present
    $cleanedJSON = trim($textOutput);
    $cleanedJSON = preg_replace('/^```(?:json)?\s*/i', '', $cleanedJSON);
    $cleanedJSON = preg_replace('/\s*```$/', '', $cleanedJSON);
    $cleanedJSON = trim($cleanedJSON);

    $parsed = json_decode($cleanedJSON, true);

    if (!is_array($parsed) || empty($parsed['unit_name']) || empty($parsed['abbreviation'])) {
        echo json_encode(getLocalFallback($inputText));
        exit;
    }

    $parsed['reorder_level'] = max(1, (int) ($parsed['reorder_level'] ?? 10));
    $parsed['source'] = 'nvidia_nim';
    echo json_encode($parsed);
    exit;

} catch (Exception $e) {
    echo json_encode(getLocalFallback($inputText));
    exit;
}
