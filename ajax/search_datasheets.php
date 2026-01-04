<?php
/**
 * Datasheet Search Service
 * 
 * This service searches for datasheets online based on equipment brand and model
 * It uses multiple search strategies to find the most relevant datasheet URLs
 */

// Prevent direct access
if (!defined('AJAX_REQUEST')) {
    header("HTTP/1.1 403 Forbidden");
    echo "Direct access forbidden";
    exit;
}

// Set headers for JSON response
header('Content-Type: application/json');

// Get search parameters
$brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
$model = isset($_POST['model']) ? trim($_POST['model']) : '';
$type = isset($_POST['type']) ? trim($_POST['type']) : ''; // 'module' or 'inverter'

// Validate required parameters
if (empty($brand) || empty($model) || empty($type)) {
    echo json_encode([
        'success' => false,
        'message' => 'Brand, model and type are required',
        'results' => []
    ]);
    exit;
}

// Normalize type
$type = strtolower($type);
if (!in_array($type, ['module', 'inverter'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Type must be either "module" or "inverter"',
        'results' => []
    ]);
    exit;
}

/**
 * Search for datasheets using multiple strategies
 * 
 * @param string $brand Equipment brand
 * @param string $model Equipment model
 * @param string $type Equipment type ('module' or 'inverter')
 * @return array Array of potential datasheet URLs with titles
 */
function searchForDatasheets($brand, $model, $type) {
    $results = [];
    
    // Strategy 1: Search using Google Custom Search API (if API key is configured)
    $googleResults = searchViaGoogle($brand, $model, $type);
    if (!empty($googleResults)) {
        $results = array_merge($results, $googleResults);
    }
    
    // Strategy 2: Search on manufacturer websites based on known URL patterns
    $manufacturerResults = searchManufacturerWebsites($brand, $model, $type);
    if (!empty($manufacturerResults)) {
        $results = array_merge($results, $manufacturerResults);
    }
    
    // Strategy 3: Search on common solar equipment databases
    $databaseResults = searchEquipmentDatabases($brand, $model, $type);
    if (!empty($databaseResults)) {
        $results = array_merge($results, $databaseResults);
    }
    
    // Remove duplicates and limit results
    $uniqueResults = [];
    $urls = [];
    
    foreach ($results as $result) {
        if (!in_array($result['url'], $urls)) {
            $urls[] = $result['url'];
            $uniqueResults[] = $result;
            
            // Limit to top 5 results for performance
            if (count($uniqueResults) >= 5) {
                break;
            }
        }
    }
    
    return $uniqueResults;
}

/**
 * Search using Google Custom Search API
 * 
 * @param string $brand Equipment brand
 * @param string $model Equipment model
 * @param string $type Equipment type
 * @return array Search results
 */
function searchViaGoogle($brand, $model, $type) {
    // Google Custom Search API key and Search Engine ID would be needed
    // This is a placeholder - in production, you'd use actual API credentials
    $apiKey = ''; // Add your Google API key here
    $searchEngineId = ''; // Add your Custom Search Engine ID here
    
    if (empty($apiKey) || empty($searchEngineId)) {
        return [];
    }
    
    $additionalTerms = ($type === 'module') ? 'solar panel datasheet pdf' : 'inverter datasheet pdf';
    $query = urlencode("$brand $model $additionalTerms");
    
    $url = "https://www.googleapis.com/customsearch/v1?key=$apiKey&cx=$searchEngineId&q=$query";
    
    $response = makeApiRequest($url);
    $results = [];
    
    if ($response && isset($response->items)) {
        foreach ($response->items as $item) {
            if (isPotentialDatasheet($item->link)) {
                $results[] = [
                    'title' => $item->title,
                    'url' => $item->link,
                    'source' => 'Google Search'
                ];
            }
        }
    }
    
    return $results;
}

/**
 * Search on manufacturer websites based on known URL patterns
 * 
 * @param string $brand Equipment brand
 * @param string $model Equipment model
 * @param string $type Equipment type
 * @return array Search results
 */
function searchManufacturerWebsites($brand, $model, $type) {
    $results = [];
    $brandLower = strtolower($brand);
    
    // Known manufacturer website patterns
    $manufacturerPatterns = [
        // Solar Module Manufacturers
        'jinko' => [
            'url' => 'https://jinkosolar.com/uploads/DATASHEETS/{MODEL}_EN.pdf',
            'type' => 'module'
        ],
        'canadian solar' => [
            'url' => 'https://www.canadiansolar.com/wp-content/uploads/{MODEL}-datasheet.pdf',
            'type' => 'module'
        ],
        'ja solar' => [
            'url' => 'https://www.jasolar.com/uploadfile/specs/{MODEL}.pdf',
            'type' => 'module'
        ],
        'trina' => [
            'url' => 'https://static.trinasolar.com/sites/default/files/{MODEL}_datasheet.pdf',
            'type' => 'module'
        ],
        'longi' => [
            'url' => 'https://www.longi.com/downloadRepository/datasheets/{MODEL}_EN.pdf',
            'type' => 'module'
        ],
        
        // Inverter Manufacturers
        'sma' => [
            'url' => 'https://files.sma.de/downloads/{MODEL}-DEN.pdf',
            'type' => 'inverter'
        ],
        'fronius' => [
            'url' => 'https://www.fronius.com/~/downloads/Solar%20Energy/Datasheets/{MODEL}_EN.pdf',
            'type' => 'inverter'
        ],
        'huawei' => [
            'url' => 'https://solar.huawei.com/en/download?p={MODEL}',
            'type' => 'inverter'
        ],
        'solaredge' => [
            'url' => 'https://www.solaredge.com/sites/default/files/{MODEL}-datasheet.pdf',
            'type' => 'inverter'
        ],
        'goodwe' => [
            'url' => 'https://en.goodwe.com/Public/Uploads/products/{MODEL}.pdf',
            'type' => 'inverter'
        ],
    ];
    
    // Check if we have patterns for this manufacturer
    foreach ($manufacturerPatterns as $knownBrand => $pattern) {
        if (stripos($brandLower, $knownBrand) !== false && $pattern['type'] === $type) {
            // Try different model number formats
            $modelVariants = [
                $model,
                str_replace(' ', '-', $model),
                str_replace(' ', '_', $model),
                strtolower($model),
                strtoupper($model)
            ];
            
            foreach ($modelVariants as $variant) {
                $datasheetUrl = str_replace('{MODEL}', $variant, $pattern['url']);
                
                // We'll add this URL as a potential match without checking if it exists
                // The frontend can verify if the URL is valid when the user selects it
                $results[] = [
                    'title' => "$brand $model Datasheet (Manufacturer Website)",
                    'url' => $datasheetUrl,
                    'source' => 'Manufacturer Website'
                ];
            }
        }
    }
    
    return $results;
}

/**
 * Search on common solar equipment databases
 * 
 * @param string $brand Equipment brand
 * @param string $model Equipment model
 * @param string $type Equipment type
 * @return array Search results
 */
function searchEquipmentDatabases($brand, $model, $type) {
    $results = [];
    
    // ENF Solar Database
    if ($type === 'module') {
        $enfSolarUrl = "https://www.enfsolar.com/pv/panel?manufacturer=" . urlencode($brand) . "&model=" . urlencode($model);
        $results[] = [
            'title' => "$brand $model on ENF Solar Database",
            'url' => $enfSolarUrl,
            'source' => 'ENF Solar Database'
        ];
    } else {
        $enfSolarUrl = "https://www.enfsolar.com/pv/inverter?manufacturer=" . urlencode($brand) . "&model=" . urlencode($model);
        $results[] = [
            'title' => "$brand $model on ENF Solar Database",
            'url' => $enfSolarUrl,
            'source' => 'ENF Solar Database'
        ];
    }
    
    // SolarDesignTool Database
    if ($type === 'module') {
        $solarDesignToolUrl = "https://www.solardesigntool.com/components/module-panel-solar/" . urlencode($brand) . "/" . urlencode($model) . "/specification-data.html";
        $results[] = [
            'title' => "$brand $model on SolarDesignTool",
            'url' => $solarDesignToolUrl,
            'source' => 'SolarDesignTool Database'
        ];
    }
    
    return $results;
}

/**
 * Helper function to make API requests
 * 
 * @param string $url API URL
 * @return object|false JSON response or false on failure
 */
function makeApiRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return json_decode($response);
    }
    
    return false;
}

/**
 * Check if a URL is likely to be a datasheet
 * 
 * @param string $url URL to check
 * @return bool True if likely a datasheet, false otherwise
 */
function isPotentialDatasheet($url) {
    $pdfPattern = '/\.pdf$/i';
    $datasheetPattern = '/datasheet|spec(ification)?|technical[_\s]data|product[_\s]data/i';
    
    // Check if it's a PDF
    if (preg_match($pdfPattern, $url)) {
        return true;
    }
    
    // Check if URL contains datasheet-related terms
    if (preg_match($datasheetPattern, $url)) {
        return true;
    }
    
    return false;
}

// Perform the search
$results = searchForDatasheets($brand, $model, $type);

// Return results
echo json_encode([
    'success' => true,
    'message' => count($results) > 0 ? 'Found ' . count($results) . ' potential datasheets' : 'No datasheets found',
    'results' => $results
]);