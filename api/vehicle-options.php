<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Free NHTSA vPIC proxy for vehicle make/model dropdowns (no API key).
 * Docs: https://vpic.nhtsa.dot.gov/api/
 */

header('Cache-Control: public, max-age=86400');

$action = strtolower(trim((string) ($_GET['action'] ?? 'makes')));

function nhtsa_get(string $path): array
{
    $url = 'https://vpic.nhtsa.dot.gov/api/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        return [];
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

if ($action === 'models') {
    $make = trim((string) ($_GET['make'] ?? ''));
    if ($make === '') {
        json_response(['success' => true, 'models' => []]);
    }
    $data = nhtsa_get('vehicles/GetModelsForMake/' . rawurlencode($make) . '?format=json');
    $models = [];
    foreach (($data['Results'] ?? []) as $row) {
        $name = trim((string) ($row['Model_Name'] ?? ''));
        if ($name !== '') {
            $models[$name] = $name;
        }
    }
    natcasesort($models);
    json_response(['success' => true, 'make' => $make, 'models' => array_values($models)]);
}

// Makes: merge passenger car + truck + MPV for better coverage
$types = ['car', 'truck', 'multipurpose passenger vehicle'];
$makes = [];
foreach ($types as $type) {
    $data = nhtsa_get('vehicles/GetMakesForVehicleType/' . rawurlencode($type) . '?format=json');
    foreach (($data['Results'] ?? []) as $row) {
        $name = trim((string) ($row['MakeName'] ?? ''));
        if ($name !== '') {
            $makes[strtoupper($name)] = $name;
        }
    }
}

uksort($makes, 'strnatcasecmp');
json_response(['success' => true, 'makes' => array_values($makes)]);
