<?php

// --- CONFIGURATION ---
$sourceUrl = 'https://vpny.online/VPNy.json';
$rawJsonFile = __DIR__ . '/Public_Config_Not_Private.json';
$configLinkFile = __DIR__ . '/This_Link_Is_For_Everyone.txt';

// The base name for all custom configurations.
$newTagNameBase = 'Public-Config-Not-Private';
// An array of reserved/protected tags that should NOT be renamed.
$ignoredTags = ['auto', 'proxy', 'direct', 'block'];
// --- END CONFIGURATION ---


echo "Starting intelligent config processing at " . date('Y-m-d H:i:s') . "\n";

// 1. Fetch the original content
$jsonContent = @file_get_contents($sourceUrl);
if ($jsonContent === false) {
    die("Error: Failed to fetch content from URL: " . $sourceUrl . "\n");
}
echo "Successfully fetched original content.\n";

// 2. Decode the JSON into a PHP array for manipulation
$configData = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON format. " . json_last_error_msg() . "\n");
}

// 3. --- DYNAMIC RENAMING LOGIC ---
$nameMap = []; // To store mappings from old_name => new_name
$counter = 1;

// Pass 1: Find all non-reserved tags, rename them, and build the map.
echo "Pass 1: Identifying and renaming custom tags...\n";
if (isset($configData['outbounds']) && is_array($configData['outbounds'])) {
    foreach ($configData['outbounds'] as &$outbound) {
        // Check if the outbound has a tag and it's not in our ignored list
        if (isset($outbound['tag']) && !in_array($outbound['tag'], $ignoredTags)) {
            $originalTag = $outbound['tag'];
            $newTag = $newTagNameBase . '-' . $counter;

            // Store the mapping and apply the new name
            $nameMap[$originalTag] = $newTag;
            $outbound['tag'] = $newTag;

            echo " - Mapping '{$originalTag}' to '{$newTag}'\n";
            $counter++;
        }
    }
}
unset($outbound); // Unset reference to avoid side effects

// Pass 2: Update references to the renamed tags inside selectors, urltests, etc.
echo "Pass 2: Updating references in selectors and other groups...\n";
if (isset($configData['outbounds']) && is_array($configData['outbounds'])) {
    foreach ($configData['outbounds'] as &$outbound) {
        // Check if this outbound contains a list of other outbounds (by tag name)
        if (isset($outbound['outbounds']) && is_array($outbound['outbounds'])) {
            foreach ($outbound['outbounds'] as &$nestedTag) {
                // If this nested tag was one of the ones we renamed, update it
                if (isset($nameMap[$nestedTag])) {
                    $nestedTag = $nameMap[$nestedTag];
                }
            }
            unset($nestedTag);
        }
    }
}
unset($outbound);
echo "All references updated successfully.\n";


// 4. Re-encode the MODIFIED array back into a pretty JSON string
$modifiedJsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 5. Save the MODIFIED JSON to our new file
if (file_put_contents($rawJsonFile, $modifiedJsonContent) === false) {
    die("Error: Could not write modified JSON to file: " . $rawJsonFile . "\n");
}
echo "Successfully saved fully modified JSON to " . $rawJsonFile . "\n";


// 6. Find the hysteria2 config from our MODIFIED data to build the link
$hysteriaConfig = null;
foreach ($configData['outbounds'] as $outbound) {
    if (isset($outbound['type']) && $outbound['type'] === 'hysteria2') {
        $hysteriaConfig = $outbound;
        break;
    }
}

if ($hysteriaConfig === null) {
    die("Error: Could not find 'hysteria2' config in the modified data.\n");
}

// 7. Build the final URL, which will now use the new, systematically generated tag name
try {
    $server      = $hysteriaConfig['server'];
    $port        = $hysteriaConfig['server_port'];
    $password    = $hysteriaConfig['password'];
    $obfsType    = $hysteriaConfig['obfs']['type'];
    $obfsPass    = $hysteriaConfig['obfs']['password'];
    $tlsInsecure = $hysteriaConfig['tls']['insecure'] ? '1' : '0';
    $tag         = $hysteriaConfig['tag']; // This will now be 'Public-Config-Not-Private-1' (or similar)

    $queryParams = http_build_query([
        'obfs' => $obfsType,
        'obfs-password' => $obfsPass,
        'insecure' => $tlsInsecure,
        'sni' => $server
    ]);

    $hysteriaLink = "hysteria2://{$password}@{$server}:{$port}?{$queryParams}#" . urlencode($tag);
    echo "Successfully built final connection URL: " . $hysteriaLink . "\n";

} catch (Exception $e) {
    die("Error: Missing keys to build URL. Details: " . $e->getMessage() . "\n");
}

// 8. Save the generated URL to the second file
if (file_put_contents($configLinkFile, $hysteriaLink) === false) {
    die("Error: Could not write the link to file: " . $configLinkFile . "\n");
}
echo "Successfully saved final link to " . $configLinkFile . "\n";
echo "Process completed successfully.\n";
?>
