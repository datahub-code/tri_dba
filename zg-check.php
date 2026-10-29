<?php
// ZORVO STORE DIAGNOSTIC - temporary. DELETE THIS FILE as soon as you have the results.
const ZG_CHECK_KEY = '7820622ba3fbab2c';

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('html_errors', '0');

if (!isset($_GET['k']) || !is_string($_GET['k']) || !hash_equals(ZG_CHECK_KEY, $_GET['k'])) {
    http_response_code(403);
    exit('Add ?k=YOUR_KEY to the end of the address.');
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
ob_start();

$zgc_issues = 0;
function zgc_line($ok, $label, $detail = '') {
    global $zgc_issues;
    if (!$ok) { $zgc_issues++; }
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . (!$ok && $detail !== '' ? '  -> ' . $detail : '') . "\n";
}
function zgc_info($text) { echo '[info] ' . $text . "\n"; }

echo "ZORVO STORE DIAGNOSTIC\n======================\n\n-- Server --\n";
zgc_line(version_compare(PHP_VERSION, '8.1.0', '>='), 'PHP version ' . PHP_VERSION . ' (' . PHP_SAPI . ')', 'needs 8.1 or newer; choose 8.2 or 8.3 in hPanel > PHP Configuration');
foreach (array('pdo', 'pdo_mysql', 'mbstring', 'session', 'json', 'ctype', 'hash', 'filter') as $ext) {
    zgc_line(extension_loaded($ext), 'PHP extension ' . $ext, 'missing: enable it in hPanel > PHP Configuration > PHP Extensions');
}
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
zgc_info('Opened with HTTPS: ' . ($https ? 'yes' : 'NO'));

echo "\n-- Files and folders --\n";
$root = __DIR__;
$parent = dirname($root);
zgc_info('Website folder: ' . $root);
zgc_info('Private config must be at: ' . $parent . '/zorvo-config.php');
$must = array('index.php', 'setup.php', 'cart.php', 'checkout.php', 'includes/bootstrap.php', 'includes/store.php', 'includes/layout.php', 'includes/admin_ui.php', 'includes/defaults.php', 'admin/login.php', 'admin/index.php', 'assets/css/site.css', 'assets/img/hero.jpg');
foreach ($must as $f) {
    zgc_line(is_file($root . '/' . $f), 'File ' . $f, 'not found. index.php must sit directly inside public_html (not in a sub-folder)');
}
foreach (array('index.html', 'index.htm', 'default.php', 'default.html') as $stray) {
    if (is_file($root . '/' . $stray)) { zgc_line(false, 'Stray file ' . $stray, 'rename or delete it; it can take priority over index.php'); }
}
foreach (array($root, $root . '/includes', $root . '/admin', $root . '/assets', $root . '/index.php') as $p) {
    $perm = @fileperms($p);
    if ($perm === false) { continue; }
    zgc_line(($perm & 0002) === 0, 'Permissions ' . substr(sprintf('%o', $perm), -4) . ' on ' . basename($p), 'writable by everyone: set folders to 755 and files to 644 (hosts often return 500 for 777)');
}
$up = $root . '/assets/uploads';
zgc_line(is_dir($up) && is_writable($up), 'assets/uploads folder is writable (needed for photo uploads)', 'set its permission to 755');

echo "\n-- Syntax check of every PHP file --\n";
if (function_exists('token_get_all') && defined('TOKEN_PARSE')) {
    $count = 0;
    $bad = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php' || $file->getFilename() === basename(__FILE__)) { continue; }
        $count++;
        $rel = substr($file->getPathname(), strlen($root) + 1);
        $src = @file_get_contents($file->getPathname());
        if ($src === false) { $bad++; zgc_line(false, $rel, 'cannot read this file (permissions?)'); continue; }
        if (substr($src, 0, 3) === "\xEF\xBB\xBF") { $bad++; zgc_line(false, $rel, 'starts with a BOM; save as UTF-8 without BOM'); }
        try {
            token_get_all($src, TOKEN_PARSE);
        } catch (ParseError $e) {
            $bad++;
            zgc_line(false, $rel, 'line ' . $e->getLine() . ': ' . $e->getMessage());
        }
    }
    zgc_line($bad === 0, 'Checked ' . $count . ' PHP files (expected 25)', $bad . ' file(s) have a problem; see above');
} else {
    zgc_info('The PHP tokenizer extension is not available, so the syntax check was skipped.');
}

echo "\n-- Private config file --\n";
$cfgPath = $parent . '/zorvo-config.php';
$cfg = null;
if (is_file($cfgPath)) {
    try {
        $cfg = include $cfgPath;
    } catch (Throwable $e) {
        zgc_line(false, 'Reading zorvo-config.php', get_class($e) . ': ' . $e->getMessage() . ' (line ' . $e->getLine() . ')');
    }
}
zgc_line(is_array($cfg), 'zorvo-config.php found and returns settings', 'missing, misnamed (e.g. .php.txt), in the wrong folder, or edited so the "return [ ... ];" is broken');
$configOk = false;
if (is_array($cfg)) {
    $configOk = true;
    foreach (array('db_host', 'db_name', 'db_user', 'db_password') as $k) {
        $v = isset($cfg[$k]) ? (string)$cfg[$k] : '';
        $okv = $v !== '' && strpos($v, 'YOUR_') !== 0;
        if (!$okv) { $configOk = false; }
        zgc_line($okv, 'Config value ' . $k . ' is filled in', 'still empty or still the example text');
    }
    zgc_info('db_name = ' . (isset($cfg['db_name']) ? $cfg['db_name'] : '') . ' | db_user = ' . (isset($cfg['db_user']) ? $cfg['db_user'] : '') . ' (compare with hPanel; the password is never shown)');
    $sk = isset($cfg['setup_key']) ? (string)$cfg['setup_key'] : '';
    zgc_line(strlen($sk) >= 32 && strpos($sk, 'REPLACE_') !== 0, 'setup_key is your own code of 32+ characters', 'needed only for setup.php; replace the example text');
    $needHttps = !isset($cfg['require_https']) || $cfg['require_https'];
    if ($needHttps && !$https) { $configOk = false; zgc_line(false, 'HTTPS', 'open this page with https:// (turn on SSL for the domain in hPanel)'); }
}

echo "\n-- Loading the store code --\n";
$loaded = false;
if ($configOk) {
    define('ZG_APP', true);
    try {
        require $root . '/includes/bootstrap.php';
        ini_set('display_errors', '1');
        require $root . '/includes/store.php';
        require $root . '/includes/layout.php';
        require $root . '/includes/admin_ui.php';
        $loaded = true;
        zgc_line(true, 'Store code loads (bootstrap, store, layout, admin_ui)');
    } catch (Throwable $e) {
        zgc_line(false, 'Loading the store code', get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ' line ' . $e->getLine());
    }
} else {
    zgc_info('Skipped: fix the config problems above first.');
}

echo "\n-- Database --\n";
if ($loaded) {
    try {
        $pdo = db();
        zgc_line(true, 'Database connection works');
        zgc_info('Database server version: ' . $pdo->query('SELECT VERSION()')->fetchColumn());
        $found = $pdo->query("SHOW TABLES LIKE 'zg\\_%'")->fetchAll(PDO::FETCH_COLUMN);
        $expected = array('zg_settings', 'zg_admins', 'zg_categories', 'zg_products', 'zg_orders', 'zg_order_items', 'zg_inquiries', 'zg_limits');
        $missing = array_diff($expected, $found);
        if (count($found) === 0) {
            zgc_info('No store tables yet. That is expected BEFORE you run setup.php.');
        } else {
            zgc_line(count($missing) === 0, 'Store tables present (' . count($found) . ' of 8)', 'missing: ' . implode(', ', $missing));
        }
        if (in_array('zg_admins', $found, true)) {
            $admins = (int)$pdo->query('SELECT COUNT(*) FROM zg_admins')->fetchColumn();
            zgc_info('Admin accounts: ' . $admins . ($admins > 0 ? ' (setup is done: delete setup.php)' : ' (run setup.php)'));
        }
        if (count($missing) === 0 && count($found) > 0) {
            $s = settings();
            zgc_line(is_array($s), 'Store settings load');
            zgc_info('Products: ' . (int)$pdo->query('SELECT COUNT(*) FROM zg_products')->fetchColumn());
        }
    } catch (Throwable $e) {
        zgc_line(false, 'Database', get_class($e) . ': ' . $e->getMessage());
    }
} else {
    zgc_info('Skipped.');
}

echo "\n" . ($zgc_issues === 0 ? "No problems found by this check.\n" : $zgc_issues . " problem(s) found. Copy this whole page and send it to the assistant.\n");
echo "\nIMPORTANT: delete zg-check.php from public_html now.\n";
ob_end_flush();
