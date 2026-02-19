<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Sitemap generator for CLI or browser access.
// Web usage: visit https://localhost/items/sitemap_generator.php
// CLI usage : php sitemap_generator.php [--host=www.example.com] [--scheme=http|http] [--path=/items/] [--output-dir=/path] [--image-base=https://cdn.example.com/] [--param=content] [--max=5000]

$siteName = 'GYG888';
$registerUrl = 'https://gyg888th.pages.dev';
require_once __DIR__ . '/helpers.php';

$isCli = PHP_SAPI === 'cli';

// Parse options (CLI) and provide HTTP fallbacks
$options = $isCli ? getopt('', array(
    'host::',
    'scheme::',
    'path::',
    'output-dir::',
    'param::',
    'max::',
    'update::',
)) : array();

$defaultHost = 'localhost';
$defaultScheme = 'http';
$defaultPath = '';
$defaultParam = 'content';

$host = readInput('host', $defaultHost);
$scheme = strtolower((string) readInput('scheme', $defaultScheme)) === 'https' ? 'https' : 'http';
$path = readInput('path', $defaultPath);
$path = trim($path, '/') === '' ? '/' : '/' . trim($path, '/') . '/';
$outputDir = readInput('output-dir', __DIR__);
$outputDir = rtrim($outputDir, '/\\');
$paramName = readInput('param', $defaultParam);
$paramName = $paramName !== '' ? $paramName : $defaultParam;
$maxUrlsPerSitemap = (int) readInput('max', 20000);
$now = gmdate('Y-m-d\TH:i:s\Z');
$indexPath = $outputDir . '/sitemap_index.xml';
$existingLastmod = readExistingLastmod($indexPath);
$shouldUpdateLastmod = (!$isCli && isset($_GET['update'])) || ($isCli && isset($options['update']));
$lastmod = $shouldUpdateLastmod ? $now : ($existingLastmod ?: $now);
$hasIndex = is_file($indexPath);
$shouldGenerate = $shouldUpdateLastmod || !$hasIndex;

if ($maxUrlsPerSitemap < 1) {
    $maxUrlsPerSitemap = 20000;
}

// Build base URL. Prefer explicit path; if none supplied in HTTP, fall back to the directory of the request URI.
if ($isCli) {
    $baseUrl = rtrim($scheme . '://' . $host . $path, '/') . '/';
} else {
    $schemeFromRequest = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $hostFromRequest = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $host;

    $requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
    $pathFromRequest = rtrim(dirname($requestPath), '/\\') . '/';

    // If user didn't supply a path (root), use the request directory to form correct absolute loc values.
    $effectivePath = ($path === '/') ? $pathFromRequest : $path;
    $effectivePath = $effectivePath === '' ? '/' : $effectivePath;

    $baseUrl = rtrim($schemeFromRequest . '://' . $hostFromRequest . $effectivePath, '/') . '/';
}

$dbPath = __DIR__ . '/contents.db';
if (!is_file($dbPath)) {
    if ($isCli) {
        fwrite(STDERR, "Missing contents.db at {$dbPath}\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing database';
    exit;
}

$totalBrands = getAllBrandsCount($dbPath);

if ($totalBrands === 0) {
    if ($isCli) {
        fwrite(STDERR, "No brands found in database\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No brands found';
    exit;
}

if (function_exists('ini_set')) {
    ini_set('memory_limit', '128M');
}

$batchSize = 5000;
$offset = 0;
$processedCount = 0;
$totalUrls = 0;
$currentChunk = array();
$chunkNumber = 1;
$chunkFiles = array();

if ($isCli) {
    echo "Processing {$totalBrands} brands in batches of {$batchSize}...\n";
    echo "Starting memory: " . formatBytes(memory_get_usage(true)) . "\n";
}

// Update all brands updated_at timestamp when explicitly requested
if ($shouldUpdateLastmod) {
    $db = getDbConnection($dbPath, true);
    if ($db !== null) {
        $updateTime = gmdate('Y-m-d H:i:s');
        $db->exec("UPDATE brands SET updated_at = '{$updateTime}'");

        if ($isCli) {
            echo "Updated all brands with timestamp: {$updateTime}\n";
        }
    }
}

// Generate when explicitly requested (update) or when no prior sitemap_index.xml exists (first run).
if ($shouldGenerate) {
    while ($offset < $totalBrands) {
        $brands = getBrandsBatch($dbPath, $batchSize, $offset);

        foreach ($brands as $brand) {
            if (empty($brand['slug'])) {
                continue;
            }

            $encode = function_exists('awurlencode') ? 'awurlencode' : 'rawurlencode';
            $url = $baseUrl . '?' . $paramName . '=' . $encode((string) $brand['slug']);

            $brandLastmod = $lastmod;
            if (!empty($brand['updated_at'])) {
                $timestamp = strtotime($brand['updated_at']);
                if ($timestamp !== false) {
                    $brandLastmod = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
                }
            }

            $currentChunk[] = array(
                'loc' => $url,
                'lastmod' => $brandLastmod,
                'changefreq' => 'daily',
                'priority' => '0.8',
            );

            $totalUrls++;

            // Write chunk to file when it reaches the limit
            if (count($currentChunk) >= $maxUrlsPerSitemap) {
                $chunkPath = $outputDir . '/sitemap_' . $chunkNumber . '.xml';
                file_put_contents($chunkPath, renderSitemapChunk($currentChunk, $lastmod));
                $chunkFiles[] = $chunkNumber;
                $chunkNumber++;
                $currentChunk = array();

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }

        $processedCount += count($brands);
        $offset += $batchSize;

        if ($isCli && $processedCount % 10000 === 0) {
            $currentMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);
            echo "Processed {$processedCount}/{$totalBrands} brands... ";
            echo "Memory: " . formatBytes($currentMemory) . " (Peak: " . formatBytes($peakMemory) . ")\n";
        }

        unset($brands);
    }

    // Write remaining entries in the last chunk
    if (count($currentChunk) > 0) {
        $chunkPath = $outputDir . '/sitemap_' . $chunkNumber . '.xml';
        file_put_contents($chunkPath, renderSitemapChunk($currentChunk, $lastmod));
        $chunkFiles[] = $chunkNumber;
        $currentChunk = array();
    }

    // Generate sitemap index
    file_put_contents($indexPath, renderSitemapIndex($chunkFiles, $baseUrl, $lastmod));

    // Store counts before cleanup
    $totalChunks = count($chunkFiles);

    // Final cleanup
    unset($currentChunk);
    unset($chunkFiles);

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
} else {
    $totalChunks = 0;
}

if ($isCli) {
    if ($shouldGenerate) {
        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        echo 'Generated sitemap_index.xml and ' . $totalChunks . " sitemap chunk file(s)\n";
        echo 'Total URLs: ' . $totalUrls . "\n";
        echo 'Base URL: ' . $baseUrl . "\n";
        echo 'Output dir: ' . $outputDir . "\n";
        echo 'Final memory: ' . formatBytes($finalMemory) . "\n";
        echo 'Peak memory: ' . formatBytes($peakMemory) . "\n";
    } else {
        echo "Skipping generation; sitemap_index.xml exists and --update not provided.\n";
    }
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Sitemap Status</title>';
    echo '<style>body{font-family:Arial, sans-serif;line-height:1.5;padding:24px;background:#f5f7fb;color:#222;}code{background:#eef1f5;padding:2px 6px;border-radius:4px;}</style>';
    echo '</head><body>';
    echo '<h1>Sitemap Status</h1>';
    echo '<p>Base URL: <code>' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<p>Output directory: <code>' . htmlspecialchars($outputDir, ENT_QUOTES, 'UTF-8') . '</code></p>';
    if ($shouldGenerate) {
        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        echo '<p>Total URLs: <strong>' . $totalUrls . '</strong></p>';
        echo '<p>Files created: <a href="./sitemap_index.xml" target="_blank">sitemap_index.xml</a> and ' . $totalChunks . ' sitemap chunk file(s).</p>';
        echo '<p>Final memory: <strong>' . formatBytes($finalMemory) . '</strong></p>';
        echo '<p>Peak memory: <strong>' . formatBytes($peakMemory) . '</strong></p>';
    } else {
        echo '<p>Total URLs: <strong>' . $totalBrands . '</strong></p>';
        echo '<p>Skipping generation because existing <code><a href="./sitemap_index.xml" target="_blank">sitemap_index.xml</a></code> found</p>';
    }
    echo '</body></html>';
}

function formatBytes($bytes)
{
    $units = array('B', 'KB', 'MB', 'GB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

function readInput($key, $default = null)
{
    global $isCli, $options;

    if ($isCli) {
        if (isset($options[$key]) && $options[$key] !== '') {
            return $options[$key];
        }
    } else {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            return $_GET[$key];
        }
    }

    return $default;
}

function readExistingLastmod($indexPath)
{
    if (!is_file($indexPath)) {
        return null;
    }

    $content = @file_get_contents($indexPath);
    if ($content === false || $content === '') {
        return null;
    }

    // Avoid heavy XML parsing; a quick regex for <lastmod>...</lastmod> is enough here.
    if (preg_match('~<lastmod>([^<]+)</lastmod>~i', $content, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function renderSitemapIndex($chunkFiles, $baseUrl, $lastmod)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($chunkFiles as $chunkNum) {
        $sitemapUrl = $baseUrl . 'sitemap_' . $chunkNum . '.xml';
        $xml .= "  <sitemap>\n";
        $xml .= '    <loc>' . htmlspecialchars($sitemapUrl, ENT_XML1) . "</loc>\n";
        $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1) . "</lastmod>\n";
        $xml .= "  </sitemap>\n";
    }

    $xml .= '</sitemapindex>';
    return $xml;
}

function renderSitemapChunk($entries, $lastmod)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['loc'])) {
            continue;
        }

        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . "</loc>\n";
        $entryLastmod = isset($entry['lastmod']) && $entry['lastmod'] !== '' ? $entry['lastmod'] : $lastmod;
        $changefreq = isset($entry['changefreq']) ? $entry['changefreq'] : null;
        $priority = isset($entry['priority']) ? $entry['priority'] : null;
        $xml .= '    <lastmod>' . htmlspecialchars($entryLastmod, ENT_XML1) . "</lastmod>\n";
        if ($changefreq) {
            $xml .= '    <changefreq>' . htmlspecialchars($changefreq, ENT_XML1) . "</changefreq>\n";
        }
        if ($priority) {
            $xml .= '    <priority>' . htmlspecialchars($priority, ENT_XML1) . "</priority>\n";
        }

        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';
    return $xml;
}
