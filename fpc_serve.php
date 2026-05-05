<?php
/**
 * Mr. Hanf Full Page Cache v10.4.0 - Cache-Handler
 *
 * Dieses Script wird von Apache via RewriteRule [END] aufgerufen
 * und liefert gecachte HTML-Dateien per readfile() aus.
 *
 * Warum PHP statt direkter Apache-Auslieferung?
 *   - Artfiles cache/.htaccess blockiert .html mit 403
 *   - Direkte Auslieferung verursacht Redirect-Loop mit CLEAN SEO URL
 *   - PHP-Overhead: ~5ms (validiert + readfile + exit)
 *   - Zusaetzliche Validierung zur Laufzeit benoetigt wird
 *
 * CHANGELOG v10.4.0:
 *   - NEU: Stale-While-Revalidate - abgelaufene Cache-Dateien werden weiterhin
 *     ausgeliefert (bis MAX_AGE), statt sofort MISS zu liefern
 *   - NEU: X-FPC-Stale Header wenn abgelaufene Datei ausgeliefert wird
 *   - NEU: TTL und Stale-Grenze aus DB konfigurierbar
 *   - VERBESSERT: Kein Auto-Delete mehr bei abgelaufenen Dateien (Refresh-Modus erneuert sie)
 *
 * CHANGELOG v9.0.0:
 *   - NEU: Request-Logging fuer Live Inspector und SEO-Bot-Tracking
 *   - NEU: X-FPC-Reason Header bei MISS/BYPASS
 *   - NEU: Bot-Erkennung (Googlebot, Bing, Ahrefs, Semrush, GPTBot, etc.)
 *   - NEU: Automatische Log-Rotation (7 Tage)
 *   - v8.3.0: Besucherstatistik-Tracker Pixel-Injection
 *   - v8.2.0: AJAX-Warenkorb fuer gecachte Seiten
 *   - v8.1.0: Session-Initializer JavaScript Injection
 *
 * @version   11.2.0
 * @date      2026-05-03
 */

// ============================================================
// KONFIGURATION
// ============================================================
$FPC_MIN_FILESIZE  = 500;      // Mindestgroesse in Bytes
$FPC_HEALTH_MARKER = '<!-- FPC-VALID -->';  // Pflicht-Marker im HTML
$FPC_AUTO_DELETE   = true;     // Korrupte Dateien automatisch loeschen
$FPC_REQUEST_LOG   = true;     // Request-Logging fuer Inspector/SEO (v9.0.0)
$FPC_LOG_DIR       = __DIR__ . '/fpc_cache/logs';  // Log-Verzeichnis

// v10.4.0: TTL-Konfiguration
// $FPC_CACHE_TTL = primaere Lebensdauer (aus DB, default 24h)
// $FPC_MAX_AGE   = maximales Stale-Alter (Datei wird bis hierhin ausgeliefert, default 48h)
// Zwischen TTL und MAX_AGE: Datei wird als STALE ausgeliefert (Header: X-FPC-Stale: true)
// Nach MAX_AGE: Datei wird NICHT mehr ausgeliefert (MISS)
$FPC_CACHE_TTL = 86400;   // 24h - wird spaeter aus DB ueberschrieben
$FPC_MAX_AGE   = 172800;  // 48h - absolute Obergrenze

// ============================================================
// v10.4.0: TTL aus DB laden (einmalig, gecacht)
// ============================================================
$fpc_ttl_cache_file = __DIR__ . '/fpc_cache/ttl_config.json';
$fpc_ttl_loaded = false;

// TTL-Config wird alle 5 Minuten aus DB aktualisiert
if (is_file($fpc_ttl_cache_file) && (time() - filemtime($fpc_ttl_cache_file)) < 300) {
    $ttl_data = @json_decode(@file_get_contents($fpc_ttl_cache_file), true);
    if (is_array($ttl_data) && isset($ttl_data['cache_ttl'])) {
        $FPC_CACHE_TTL = (int) $ttl_data['cache_ttl'];
        $FPC_MAX_AGE   = (int) ($ttl_data['max_age'] ?? $FPC_CACHE_TTL * 2);
        $fpc_ttl_loaded = true;
    }
}

if (!$fpc_ttl_loaded) {
    // Aus DB laden (nur wenn configure.php existiert)
    if (is_file(__DIR__ . '/includes/configure.php')) {
        if (!defined('_VALID_XTC')) define('_VALID_XTC', true);
        @include_once(__DIR__ . '/includes/configure.php');
        if (defined('DB_SERVER') && defined('DB_SERVER_USERNAME')) {
            $fpc_db = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
            if (!$fpc_db->connect_error) {
                $r = $fpc_db->query("SELECT configuration_value FROM configuration WHERE configuration_key = 'MODULE_MRHANF_FPC_CACHE_TIME' LIMIT 1");
                if ($r && $row = $r->fetch_assoc()) {
                    $FPC_CACHE_TTL = (int) $row['configuration_value'];
                    $FPC_MAX_AGE = $FPC_CACHE_TTL * 2; // Stale-Grenze = 2x TTL
                }
                $fpc_db->close();
                // Cache fuer 5 Minuten
                @file_put_contents($fpc_ttl_cache_file, json_encode(array(
                    'cache_ttl' => $FPC_CACHE_TTL,
                    'max_age'   => $FPC_MAX_AGE,
                    'updated'   => date('Y-m-d H:i:s'),
                )));
            }
        }
    }
}

// ============================================================
// SICHERHEITSCHECKS
// ============================================================

// Request-Start-Zeit fuer TTFB-Messung (v9.0.0)
$fpc_request_start = microtime(true);
$fpc_cache_status = 'MISS';
$fpc_miss_reason = '';
$fpc_http_code = 200;

// Bot-Erkennung (v9.0.0)
$fpc_ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$fpc_is_bot = false;
$fpc_bot_name = '';
$bot_patterns = array(
    'Googlebot' => 'Googlebot',
    'bingbot' => 'Bing',
    'Baiduspider' => 'Baidu',
    'YandexBot' => 'Yandex',
    'DuckDuckBot' => 'DuckDuckGo',
    'Slurp' => 'Yahoo',
    'facebot' => 'Facebook',
    'Twitterbot' => 'Twitter',
    'AhrefsBot' => 'Ahrefs',
    'SemrushBot' => 'Semrush',
    'MJ12bot' => 'Majestic',
    'PetalBot' => 'Petal',
    'Applebot' => 'Apple',
    'GPTBot' => 'GPTBot',
    'ClaudeBot' => 'ClaudeBot',
);
foreach ($bot_patterns as $pattern => $name) {
    if (stripos($fpc_ua, $pattern) !== false) {
        $fpc_is_bot = true;
        $fpc_bot_name = $name;
        break;
    }
}

// Nur GET-Requests cachen
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $fpc_miss_reason = 'NOT_GET';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// v8.0.7: Bypass-Cookie pruefen
if (isset($_COOKIE['fpc_bypass']) && $_COOKIE['fpc_bypass'] === '1') {
    $fpc_miss_reason = 'BYPASS_COOKIE';
    $fpc_cache_status = 'BYPASS';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// Request-URI bereinigen
$uri = $_SERVER['REQUEST_URI'];

// v11.0.0: Bypass fuer URLs mit action= Parameter (Warenkorb, Merkzettel, etc.)
// Diese muessen IMMER durch PHP verarbeitet werden, nie gecacht ausgeliefert.
if (isset($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'action=') !== false) {
    $fpc_miss_reason = 'ACTION_PARAM';
    $fpc_cache_status = 'BYPASS';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// Query-String entfernen (gecachte Seiten sind ohne Parameter)
$pos = strpos($uri, '?');
if ($pos !== false) {
    $uri = substr($uri, 0, $pos);
}

// Pfad normalisieren
$uri = rtrim($uri, '/');
if ($uri === '') {
    $uri = '/';
}

// ============================================================
// v10.0.0: SEO REDIRECT CHECK (vor Cache-Pruefung)
// ============================================================
$fpc_seo_file = __DIR__ . '/fpc_seo.php';
if (is_file($fpc_seo_file)) {
    try {
        require_once $fpc_seo_file;
        $fpc_seo = new FpcSeo(__DIR__ . '/');
        $fpc_redirect = $fpc_seo->findRedirect($uri);
        if ($fpc_redirect) {
            $fpc_redir_code = intval($fpc_redirect['type']);
            if ($fpc_redir_code === 410) {
                // 410 Gone - Seite existiert nicht mehr
                header('HTTP/1.1 410 Gone');
                header('X-FPC-SEO: GONE');
                $fpc_cache_status = 'REDIRECT';
                $fpc_miss_reason = 'SEO_410_GONE';
                $fpc_http_code = 410;
                fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
                echo '<!DOCTYPE html><html><head><title>410 Gone</title></head><body><h1>410 Gone</h1><p>Diese Seite existiert nicht mehr.</p></body></html>';
                exit;
            }
            // 301, 302, 307 Redirect
            if (!in_array($fpc_redir_code, array(301, 302, 307))) $fpc_redir_code = 301;
            $fpc_redir_target = $fpc_redirect['target'];
            // Relative URLs zu absoluten machen
            if (strpos($fpc_redir_target, 'http') !== 0) {
                $fpc_redir_target = 'https://' . $_SERVER['HTTP_HOST'] . $fpc_redir_target;
            }
            header('Location: ' . $fpc_redir_target, true, $fpc_redir_code);
            header('X-FPC-SEO: REDIRECT-' . $fpc_redir_code);
            $fpc_cache_status = 'REDIRECT';
            $fpc_miss_reason = 'SEO_REDIRECT_' . $fpc_redir_code;
            $fpc_http_code = $fpc_redir_code;
            fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
            exit;
        }
    } catch (Exception $e) {
        // SEO-Fehler darf Cache nicht blockieren - still ignorieren
    }
}

// --- Zweite Sicherheitsstufe: URL-basierte Ausschlussliste ---
$excluded_paths = array(
    '/vergleich',           // Produktvergleich (sessionabhaengig)
    '/wishlist',            // Merkzettel (sessionabhaengig)
    '/checkout',            // Bestellprozess
    '/kasse',               // Kasse/Checkout (SEO-URL) - v8.0.1
    '/login',               // Login-Seite
    '/account',             // Kundenkonto
    '/shopping_cart',       // Warenkorb (alt)
    '/warenkorb',           // Warenkorb (SEO-URL) - v8.0.1
    '/logoff',              // Abmelden
    '/password_double_opt', // Passwort-Opt-In
    '/create_account',      // Registrierung
    '/contact_us',          // Kontaktformular
    '/tell_a_friend',       // Weiterempfehlen
    '/product_reviews_write', // Bewertung schreiben
    '/admin',               // Admin-Bereich
);

foreach ($excluded_paths as $excluded) {
    if ($uri === $excluded || strpos($uri, $excluded . '/') === 0 || strpos($uri, $excluded . '?') === 0) {
        return false;
    }
}

// Cache-Datei Pfad berechnen
$cache_dir  = __DIR__ . '/fpc_cache';
$clean_path = trim($uri, '/');

if ($clean_path === '') {
    $cache_file = $cache_dir . '/index.html';
} else {
    $cache_file = $cache_dir . '/' . $clean_path . '/index.html';
}

// Sicherheitscheck: Pfad darf nicht aus dem Cache-Verzeichnis ausbrechen
$real_cache = realpath($cache_dir);
if ($real_cache === false) {
    return false;
}

// Cache-Datei existiert?
if (!is_file($cache_file)) {
    $fpc_miss_reason = 'FILE_NOT_FOUND';
    // v10.0.0: 404-Logging fuer SEO
    if (isset($fpc_seo) && $fpc_seo instanceof FpcSeo) {
        try {
            $fpc_referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $fpc_seo->log404($uri, $fpc_referer, $fpc_ua);
        } catch (Exception $e) {}
    }
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// Realpath-Check (verhindert Directory Traversal)
$real_file = realpath($cache_file);
if ($real_file === false || strpos($real_file, $real_cache) !== 0) {
    $fpc_miss_reason = 'SECURITY_CHECK';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// ============================================================
// VALIDIERUNG VOR AUSLIEFERUNG
// ============================================================

// 1. Dateigroesse pruefen (leere/korrupte Dateien abfangen)
$filesize = filesize($cache_file);
if ($filesize === false || $filesize < $FPC_MIN_FILESIZE) {
    if ($FPC_AUTO_DELETE) {
        @unlink($cache_file);
    }
    $fpc_miss_reason = 'FILE_TOO_SMALL';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// 2. TTL-Check mit Stale-While-Revalidate (v10.4.0)
$mtime = filemtime($cache_file);
$age = time() - $mtime;
$is_stale = false;

if ($age > $FPC_MAX_AGE) {
    // ==========================================================
    // v10.4.0: Datei ist AELTER als MAX_AGE (z.B. > 48h)
    // -> Nicht mehr ausliefern, aber NICHT automatisch loeschen!
    // Der Refresh-Modus (fpc_preloader.php --refresh) kuemmert sich
    // um die Erneuerung. Nur wirklich uralte Dateien (> 7 Tage)
    // werden automatisch geloescht.
    // ==========================================================
    if ($age > 604800) {
        // Aelter als 7 Tage -> definitiv loeschen
        @unlink($cache_file);
    }
    $fpc_miss_reason = 'EXPIRED_MAX_AGE';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
} elseif ($age > $FPC_CACHE_TTL) {
    // ==========================================================
    // v10.4.0: STALE-WHILE-REVALIDATE
    // Datei ist abgelaufen (> TTL) aber noch nicht uralt (< MAX_AGE).
    // -> Trotzdem ausliefern! Der Preloader --refresh erneuert sie
    //    im Hintergrund beim naechsten Lauf.
    // Vorteil: Besucher sieht IMMER eine schnelle gecachte Seite,
    //          auch wenn der Cache gerade erneuert wird.
    // ==========================================================
    $is_stale = true;
}

// 3. Health-Marker pruefen (schnell: nur letzte 200 Bytes lesen)
$fp = fopen($cache_file, 'r');
if ($fp === false) {
    return false;
}
$seek_pos = max(0, $filesize - 200);
fseek($fp, $seek_pos);
$tail = fread($fp, 200);
fclose($fp);

if (strpos($tail, $FPC_HEALTH_MARKER) === false) {
    if ($FPC_AUTO_DELETE) {
        @unlink($cache_file);
    }
    $fpc_miss_reason = 'NO_HEALTH_MARKER';
    fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);
    return false;
}

// ============================================================
// CACHE-DATEI AUSLIEFERN (validiert!)
// ============================================================

header('Content-Type: text/html; charset=utf-8');

if ($is_stale) {
    // v10.4.0: Stale-While-Revalidate - abgelaufene aber noch brauchbare Datei
    header('X-FPC-Cache: STALE');
    header('X-FPC-Stale: true');
    header('X-FPC-Stale-Age: ' . $age . 's');
    $fpc_cache_status = 'STALE';
} else {
    header('X-FPC-Cache: HIT');
    $fpc_cache_status = 'HIT';
}

header('X-FPC-Version: 10.4.0');
$fpc_miss_reason = '';
header('X-FPC-Cached-At: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('X-FPC-Age: ' . $age . 's');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================================
// v8.2.0: JAVASCRIPT INJECTION
// ============================================================
// Injiziert Scripts vor </body>:
// 1. Session-Initializer (v8.1.0) - startet PHP-Session im Hintergrund
// 2. AJAX-Warenkorb (v8.2.0) - fängt Formular-Submit ab, sendet per AJAX
// 3. Live Stock Refresh (v10.5.0) - Lagerbestand per AJAX nachladen
// 4. AJAX Lieferland-Wechsel (v10.7.0) - Country-Dropdown per AJAX
// 5. Besucherstatistik-Tracker (v8.3.0)
// 6. Age Verification Modal (v11.0.0) - JS-only, Cache-kompatibel

$html = file_get_contents($cache_file);

// v11.0.0: Age Verification Uebersetzungen aus JSON laden
$fpc_av_i18n_file = __DIR__ . '/fpc_age_verification_i18n.json';
$fpc_av_config = null;
if (is_file($fpc_av_i18n_file)) {
    $fpc_av_config = @json_decode(@file_get_contents($fpc_av_i18n_file), true);
}
$fpc_av_translations_json = '{}';
$fpc_av_cookie_days = 2;
$fpc_av_logo_path = '/templates/tpl_mrh_2026/img/logo_head.png';
if (is_array($fpc_av_config)) {
    if (isset($fpc_av_config['translations'])) {
        $fpc_av_translations_json = json_encode($fpc_av_config['translations'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    if (isset($fpc_av_config['_cookie_days'])) {
        $fpc_av_cookie_days = (int) $fpc_av_config['_cookie_days'];
    }
    if (isset($fpc_av_config['_logo_path'])) {
        $fpc_av_logo_path = $fpc_av_config['_logo_path'];
    }
}
// Cookie-Expiry als JS-kompatibles Datum berechnen
$fpc_av_expires_js = '';
if ($fpc_av_cookie_days > 0) {
    $fpc_av_date = new DateTime('+' . $fpc_av_cookie_days . ' days');
    $fpc_av_expires_js = $fpc_av_date->format('r');
}
// HTTP_HOST fuer Cookie-Domain (fuer HEREDOC-Interpolation)
$fpc_cookie_domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'mr-hanf.de';


// ============================================================
// v11.3.0: UPTAIN CONFIG LADEN (fuer FPC-Injection)
// ============================================================
$fpc_uptain_enabled = false;
$fpc_uptain_id = '';
$fpc_uptain_consent = true;
$fpc_uptain_config_file = __DIR__ . '/fpc_uptain_config.json';

// Primaer: Aus JSON-Config laden
if (is_file($fpc_uptain_config_file)) {
    $fpc_uptain_cfg = @json_decode(@file_get_contents($fpc_uptain_config_file), true);
    if (is_array($fpc_uptain_cfg) && !empty($fpc_uptain_cfg['uptain_id'])) {
        $fpc_uptain_enabled = !empty($fpc_uptain_cfg['enabled']);
        $fpc_uptain_id = $fpc_uptain_cfg['uptain_id'];
        $fpc_uptain_consent = isset($fpc_uptain_cfg['cookie_consent']) ? (bool)$fpc_uptain_cfg['cookie_consent'] : true;
    }
}

// Fallback: Aus DB laden (wenn JSON nicht existiert oder leer)
if (!$fpc_uptain_enabled && is_file(__DIR__ . '/includes/configure.php')) {
    if (!defined('_VALID_XTC')) define('_VALID_XTC', true);
    if (!defined('DB_SERVER')) {
        @include_once(__DIR__ . '/includes/configure.php');
    }
    if (defined('DB_SERVER') && defined('DB_SERVER_USERNAME')) {
        $fpc_uptain_db = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
        if (!$fpc_uptain_db->connect_error) {
            $r = $fpc_uptain_db->query("SELECT configuration_key, configuration_value FROM configuration WHERE configuration_key IN ('MODULE_MRH_UPTAIN_CONNECT_STATUS','MODULE_MRH_UPTAIN_CONNECT_UPTAIN_ID','MODULE_MRH_UPTAIN_CONNECT_COOKIE_CONSENT')");
            $cfg = array();
            while ($row = $r->fetch_assoc()) {
                $cfg[$row['configuration_key']] = $row['configuration_value'];
            }
            $fpc_uptain_db->close();
            if (isset($cfg['MODULE_MRH_UPTAIN_CONNECT_STATUS']) && $cfg['MODULE_MRH_UPTAIN_CONNECT_STATUS'] === 'True'
                && !empty($cfg['MODULE_MRH_UPTAIN_CONNECT_UPTAIN_ID'])) {
                $fpc_uptain_enabled = true;
                $fpc_uptain_id = $cfg['MODULE_MRH_UPTAIN_CONNECT_UPTAIN_ID'];
                $fpc_uptain_consent = (isset($cfg['MODULE_MRH_UPTAIN_CONNECT_COOKIE_CONSENT']) && $cfg['MODULE_MRH_UPTAIN_CONNECT_COOKIE_CONSENT'] === 'True');
                // JSON-Cache schreiben fuer naechsten Request
                @file_put_contents($fpc_uptain_config_file, json_encode(array(
                    'uptain_id' => $fpc_uptain_id,
                    'enabled' => true,
                    'cookie_consent' => $fpc_uptain_consent,
                    'async' => true,
                    'debug' => false,
                    '_updated' => date('Y-m-d H:i:s'),
                )));
            }
        }
    }
}
$fpc_uptain_id_safe = htmlspecialchars($fpc_uptain_id, ENT_QUOTES, 'UTF-8');

$fpc_uptain_enabled_js = $fpc_uptain_enabled ? 'true' : 'false';
$fpc_uptain_consent_js = $fpc_uptain_consent ? 'true' : 'false';

$fpc_inject_js = <<<FPCJS
<script data-fpc-inject="11.2.0">
(function(){
    'use strict';

    // ========================================================
    // 1. SESSION-INITIALIZER (v8.1.0)
    // ========================================================
    var sessionReady = (document.cookie.indexOf('MODsid=') !== -1);

    function initSession(callback) {
        if (sessionReady) { if (callback) callback(); return; }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/fpc_session_init.php?t=' + Date.now(), true);
        xhr.withCredentials = true;
        xhr.timeout = 10000;
        xhr.onload = function() {
            if (xhr.status === 200) {
                sessionReady = true;
                document.documentElement.setAttribute('data-fpc-session', 'ready');
                // v10.7.0: Lieferland aus Session synchronisieren
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.country && data.country > 0) {
                        syncCountryDropdown(data.country);
                    }
                    // v11.2.0: Wishlist-Counter aus Session synchronisieren
                    if (data.wishlist && data.wishlist > 0) {
                        updateWishlistCounter(data.wishlist);
                    }
                    // v11.2.0: Cart-Counter aus Session synchronisieren
                    if (data.cart && data.cart > 0) {
                        var cartEls = document.querySelectorAll('li.cart .cart_content, [href="#offcanvasCart"] .cart_content');
                        for (var ci = 0; ci < cartEls.length; ci++) {
                            cartEls[ci].textContent = data.cart;
                        }
                    }
                } catch(e) {}
            }
            if (callback) callback();
        };
        xhr.onerror = function() { if (callback) callback(); };
        xhr.ontimeout = function() { if (callback) callback(); };
        xhr.send();
    }

    // v10.7.0: Lieferland-Dropdown auf Session-Wert synchronisieren
    function syncCountryDropdown(countryId) {
        var selectors = [
            '#countries select[name="country"]',
            '#box-shipping-country-select-2',
            'select[name="country"]'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var sel = document.querySelector(selectors[i]);
            if (sel && sel.value != countryId) {
                // Pruefen ob die Option existiert
                for (var j = 0; j < sel.options.length; j++) {
                    if (sel.options[j].value == countryId) {
                        sel.value = countryId;
                        break;
                    }
                }
            }
        }
        // FreeShippingBar aktualisieren wenn geladen
        if (typeof window.fsbCurrentCountryId !== 'undefined') {
            window.fsbCurrentCountryId = countryId;
        }
        if (typeof window.fsbFetch === 'function') {
            window.fsbFetch(countryId);
        }
    }

    // Session sofort im Hintergrund starten
    initSession();

    // ========================================================
    // 2. AJAX-WARENKORB (v8.2.0)
    // ========================================================

    function setCookie(name, value, path, domain) {
        var c = name + '=' + value + '; path=' + (path || '/');
        if (domain) c += '; domain=' + domain;
        c += '; secure; SameSite=Lax';
        document.cookie = c;
    }

    function showToast(message, type) {
        var existing = document.getElementById('fpc-toast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.id = 'fpc-toast';
        toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:14px 24px;border-radius:8px;color:#fff;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,0.3);transition:opacity 0.4s,transform 0.4s;opacity:0;transform:translateY(-10px);';
        toast.style.background = (type === 'success') ? '#28a745' : (type === 'error') ? '#dc3545' : '#ffc107';
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function() {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3000);
    }

    function animateButton(btn, originalHtml) {
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = '<span class="fa fa-spinner fa-spin"></span>&nbsp;&nbsp;Wird hinzugef\u00fcgt...';

        return function(success) {
            btn.disabled = false;
            btn.style.opacity = '1';
            if (success) {
                btn.innerHTML = '<span class="fa fa-check"></span>&nbsp;&nbsp;Hinzugef\u00fcgt!';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-success');
                setTimeout(function() {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-secondary');
                }, 2000);
            } else {
                btn.innerHTML = originalHtml;
            }
        };
    }

    function updateMiniCart(cartCount) {
        var dropdown = document.querySelector('.dropdown-menu.toggle_cart');
        if (dropdown) {
            if (cartCount > 0) {
                var header = dropdown.querySelector('.card-header');
                if (header) {
                    header.innerHTML = '<span class="font-weight-bold">' + cartCount + ' Artikel im Warenkorb</span>' +
                        '<div class="mt-2"><a href="/warenkorb" class="btn btn-success btn-sm btn-block">' +
                        '<span class="fa fa-shopping-cart mr-2"></span>Zum Warenkorb</a></div>';
                }
            }
        }

        var cartLink = document.getElementById('toggle_cart');
        if (cartLink) {
            var badge = cartLink.querySelector('.fpc-cart-badge');
            if (!badge && cartCount > 0) {
                badge = document.createElement('span');
                badge.className = 'fpc-cart-badge';
                badge.style.cssText = 'position:absolute;top:-2px;right:-2px;background:#dc3545;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1;';
                cartLink.style.position = 'relative';
                cartLink.appendChild(badge);
            }
            if (badge) {
                badge.textContent = cartCount;
                badge.style.display = (cartCount > 0) ? 'flex' : 'none';
                badge.style.animation = 'none';
                badge.offsetHeight;
                badge.style.animation = 'fpc-pulse 0.4s ease';
            }
        }

        var cartText = cartLink ? cartLink.querySelector('.d-none.d-lg-block.small') : null;
        if (cartText && cartCount > 0) {
            cartText.textContent = 'Warenkorb (' + cartCount + ')';
        }
    }

    function triggerFSBUpdate() {
        if (typeof fsbFetch === 'function') {
            try { fsbFetch(); } catch(e) {}
        }
    }

    var style = document.createElement('style');
    style.textContent = '@keyframes fpc-pulse{0%{transform:scale(1)}50%{transform:scale(1.3)}100%{transform:scale(1)}}';
    document.head.appendChild(style);

    function handleCartSubmit(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        var action = form.getAttribute('action') || '';
        if (action.indexOf('add_product') === -1) return;

        var submitBtn = form.querySelector('.btn-cart[type="submit"]');
        if (!submitBtn) return;

        var activeElement = document.activeElement;
        if (activeElement && activeElement.name === 'wishlist') return;

        e.preventDefault();
        e.stopPropagation();

        var originalHtml = submitBtn.innerHTML;
        var restoreButton = animateButton(submitBtn, originalHtml);

        initSession(function() {
            var formData = new FormData(form);

            var postUrl = action;
            if (postUrl.indexOf('action=add_product') === -1) {
                postUrl += (postUrl.indexOf('?') === -1 ? '?' : '&') + 'action=add_product';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', postUrl, true);
            xhr.withCredentials = true;
            xhr.timeout = 15000;

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    setCookie('fpc_bypass', '1', '/', '.{$fpc_cookie_domain}');

                    var fsbXhr = new XMLHttpRequest();
                    fsbXhr.open('GET', '/ajax.php?ext=get_free_shipping_bar&t=' + Date.now(), true);
                    fsbXhr.withCredentials = true;
                    fsbXhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    fsbXhr.onload = function() {
                        if (fsbXhr.status === 200) {
                            try {
                                var data = JSON.parse(fsbXhr.responseText);
                                var count = data.cart_count || 1;
                                updateMiniCart(count);
                                triggerFSBUpdate();
                            } catch(ex) {
                                updateMiniCart(1);
                            }
                        } else {
                            updateMiniCart(1);
                        }
                    };
                    fsbXhr.onerror = function() { updateMiniCart(1); };
                    fsbXhr.send();

                    restoreButton(true);
                    showToast('Artikel wurde in den Warenkorb gelegt!', 'success');

                } else {
                    restoreButton(false);
                    showToast('Fehler beim Hinzuf\u00fcgen zum Warenkorb', 'error');
                }
            };

            xhr.onerror = function() {
                restoreButton(false);
                showToast('Netzwerkfehler - bitte erneut versuchen', 'error');
            };

            xhr.ontimeout = function() {
                restoreButton(false);
                showToast('Zeitüberschreitung - bitte erneut versuchen', 'error');
            };

            xhr.send(formData);
        });
    }

    document.addEventListener('submit', handleCartSubmit, true);

    // ========================================================
    // 2b. WISHLIST AJAX (v11.0.0)
    // ========================================================
    // JS-only Merkzettel-Handler (wie Age Verification: komplett im FPC-Inject).
    // Faengt Klicks auf .mrh-btn-wishlist ab, startet Session per AJAX,
    // sendet Wishlist-Request im Hintergrund. Visuelles Feedback: Herz + Toast + Counter.
    // No-JS-Fallback: Normaler Link wird befolgt, FPC-Bypass fuer action= greift (v11.0.0).
    // Auch auf Produktseiten: <button name="wishlist"> im Formular wird abgefangen.

    // v11.2.0: Wishlist-Counter-Funktionen (immer verfuegbar, auch auf Legacy-Seiten)
    function updateWishlistCounter(count) {
        var counters = document.querySelectorAll('li.wishlist .cart_content, [href="#offcanvasWishlist"] .cart_content');
        for (var i = 0; i < counters.length; i++) {
            counters[i].textContent = count;
            if (count > 0) {
                counters[i].classList.add('filled');
            }
        }
        if (count > 0) {
            var hearts = document.querySelectorAll('li.wishlist .fa-regular.fa-heart, [href="#offcanvasWishlist"] .fa-regular.fa-heart');
            for (var j = 0; j < hearts.length; j++) {
                hearts[j].classList.remove('fa-regular');
                hearts[j].classList.add('fa-solid');
            }
        }
        // v11.2.0: Event fuer Bottom-Bar MutationObserver ausloesen
        document.dispatchEvent(new Event('wishlistUpdated'));
    }

    function getWishlistCount() {
        var el = document.querySelector('li.wishlist .cart_content, [href="#offcanvasWishlist"] .cart_content');
        return el ? (parseInt(el.textContent, 10) || 0) : 0;
    }

    // Doppel-Schutz: Wenn alter 50_mrh_ajax_wishlist.php Handler im DOM ist, ueberspringen
    // Pruefen ob alter Handler im DOM ist (Script-Tags durchsuchen)
    var hasLegacyWishlistHandler = false;
    var scripts = document.querySelectorAll('script:not([data-fpc-inject])');
    for (var si = 0; si < scripts.length; si++) {
        if (scripts[si].textContent.indexOf('wishlistLoading') !== -1) {
            hasLegacyWishlistHandler = true;
            break;
        }
    }
    if (!hasLegacyWishlistHandler) {

    // Herz-Animation
    function animateHeart(heartIcon, success) {
        if (!heartIcon) return;
        heartIcon.style.transition = 'transform 0.3s ease, color 0.3s ease';
        if (success) {
            heartIcon.style.transform = 'scale(1.4)';
            heartIcon.style.color = '#dc3545';
            heartIcon.classList.remove('fa-regular');
            heartIcon.classList.add('fa-solid');
            setTimeout(function() { heartIcon.style.transform = 'scale(1)'; }, 300);
        } else {
            heartIcon.style.transform = 'scale(1)';
            heartIcon.style.color = '';
        }
    }

    // Handler fuer Wishlist-Links im Listing (a.mrh-btn-wishlist)
    document.addEventListener('click', function(e) {
        var link = e.target.closest ? e.target.closest('a.mrh-btn-wishlist') : null;
        if (!link) return;
        var href = link.getAttribute('href') || '';
        // Nur buy_now + wishlist abfangen, NICHT remove_product
        if (href.indexOf('wishlist=1') === -1) return;
        if (href.indexOf('action=remove_product') !== -1) return;

        e.preventDefault();
        e.stopPropagation();

        // Doppelklick verhindern
        if (link.dataset.wlLoading === '1') return;
        link.dataset.wlLoading = '1';

        var heartIcon = link.querySelector('.fa-heart, [class*="fa-heart"]');
        animateHeart(heartIcon, true); // Sofort visuelles Feedback

        initSession(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', href, true);
            xhr.withCredentials = true;
            xhr.timeout = 15000;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                link.dataset.wlLoading = '0';
                if (xhr.status >= 200 && xhr.status < 400) {
                    updateWishlistCounter(getWishlistCount() + 1);
                    showToast('Produkt zum Merkzettel hinzugef\u00fcgt!', 'success');
                } else {
                    animateHeart(heartIcon, false);
                    // Fallback: normaler Link
                    window.location.href = href;
                }
            };
            xhr.onerror = function() {
                link.dataset.wlLoading = '0';
                animateHeart(heartIcon, false);
                window.location.href = href;
            };
            xhr.ontimeout = function() {
                link.dataset.wlLoading = '0';
                animateHeart(heartIcon, false);
                window.location.href = href;
            };
            xhr.send();
        });
    }, true);

    // Handler fuer Wishlist-Button auf Produktseiten (button[name="wishlist"] im Formular)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('button[name="wishlist"]') : null;
        if (!btn) return;
        var form = btn.closest('form');
        if (!form) return;

        e.preventDefault();
        e.stopPropagation();

        if (btn.dataset.wlLoading === '1') return;
        btn.dataset.wlLoading = '1';

        var heartIcon = btn.querySelector('.fa-heart, [class*="fa-heart"]');
        animateHeart(heartIcon, true);

        initSession(function() {
            var formData = new FormData(form);
            formData.append('wishlist', '1');

            var postUrl = form.getAttribute('action') || '';
            if (postUrl.indexOf('action=add_product') === -1) {
                postUrl += (postUrl.indexOf('?') === -1 ? '?' : '&') + 'action=add_product';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', postUrl, true);
            xhr.withCredentials = true;
            xhr.timeout = 15000;

            xhr.onload = function() {
                btn.dataset.wlLoading = '0';
                if (xhr.status >= 200 && xhr.status < 400) {
                    updateWishlistCounter(getWishlistCount() + 1);
                    showToast('Produkt zum Merkzettel hinzugef\u00fcgt!', 'success');
                } else {
                    animateHeart(heartIcon, false);
                    // Fallback: normales Formular-Submit
                    form.submit();
                }
            };
            xhr.onerror = function() {
                btn.dataset.wlLoading = '0';
                animateHeart(heartIcon, false);
                form.submit();
            };
            xhr.ontimeout = function() {
                btn.dataset.wlLoading = '0';
                animateHeart(heartIcon, false);
                form.submit();
            };
            xhr.send(formData);
        });
    }, true);

    } // Ende: if (!hasLegacyWishlistHandler)

    // ========================================================
    // 3. LIVE STOCK REFRESH (v10.5.0)
    // ========================================================
    // Auf Produktseiten: Lagerbestand per AJAX nachladen und DOM aktualisieren
    (function refreshStock() {
        // Nur auf Produktseiten ausfuehren (Formular mit add_product)
        var cartForm = document.querySelector('form[action*="add_product"]');
        if (!cartForm) return;

        var pidInput = cartForm.querySelector('input[name="products_id"]');
        if (!pidInput) return;
        var pid = pidInput.value;
        if (!pid || pid === '0') return;

        // Sprache aus HTML-Tag oder Meta
        var lang = document.documentElement.lang || 'de';
        lang = lang.substring(0, 2).toLowerCase();

        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/fpc_stock.php?pid=' + pid + '&lang=' + lang + '&_=' + Date.now(), true);
        xhr.timeout = 5000;
        xhr.onload = function() {
            if (xhr.status !== 200) return;
            try {
                var data = JSON.parse(xhr.responseText);
                applyStockUpdate(data);
            } catch(e) {}
        };
        xhr.send();

        function applyStockUpdate(data) {
            // 1. Lieferstatus aktualisieren (.lb_shipping)
            if (data.shipping_name) {
                var shippingEls = document.querySelectorAll('.lb_shipping');
                for (var i = 0; i < shippingEls.length; i++) {
                    var link = shippingEls[i].querySelector('a');
                    if (link) {
                        link.textContent = data.shipping_name;
                    }
                }
            }

            // 2. Schema.org Availability aktualisieren
            var schemaLink = document.querySelector('link[itemprop="availability"]');
            if (schemaLink) {
                schemaLink.setAttribute('href',
                    data.in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
                );
            }

            // 3. Attribut-Lagerbestand aktualisieren (Packungsgroessen)
            if (data.attributes && data.attributes.length > 0) {
                for (var j = 0; j < data.attributes.length; j++) {
                    var attr = data.attributes[j];
                    // Select-Optionen mit data-id oder value aktualisieren
                    var opts = document.querySelectorAll('select option[value="' + attr.id + '"], select option[data-attributes-id="' + attr.id + '"]');
                    for (var k = 0; k < opts.length; k++) {
                        var optText = opts[k].textContent;
                        // Stock-Badge entfernen und neu setzen
                        optText = optText.replace(/\s*\(\d+ auf Lager\)\s*/g, '').replace(/\s*\(ausverkauft\)\s*/g, '').trim();
                        if (attr.stock <= 0) {
                            optText += ' (ausverkauft)';
                            opts[k].disabled = true;
                            opts[k].style.color = '#999';
                        } else if (attr.stock <= 5) {
                            optText += ' (' + attr.stock + ' auf Lager)';
                            opts[k].disabled = false;
                            opts[k].style.color = '';
                        } else {
                            opts[k].disabled = false;
                            opts[k].style.color = '';
                        }
                        opts[k].textContent = optText;
                    }
                }
            }

            // 4. Warenkorb-Button bei ausverkauft deaktivieren
            if (data.availability === 'out_of_stock') {
                var cartBtns = document.querySelectorAll('.btn-cart[type="submit"]');
                for (var m = 0; m < cartBtns.length; m++) {
                    cartBtns[m].disabled = true;
                    cartBtns[m].classList.remove('btn-secondary');
                    cartBtns[m].classList.add('btn-outline-danger');
                    cartBtns[m].innerHTML = '<span class="fa fa-times-circle"></span> Ausverkauft';
                }
            }

            // 5. Vorbestellung bei date_available
            if (data.availability === 'preorder' && data.date_available) {
                var cartSection = cartForm.closest('.card-footer') || cartForm.parentElement;
                if (cartSection) {
                    var notice = document.createElement('div');
                    notice.className = 'alert alert-info small mt-2';
                    notice.setAttribute('data-fpc-stock', 'preorder');
                    notice.textContent = 'Voraussichtlich verfuegbar ab ' + data.date_available;
                    // Nur einfuegen wenn noch nicht vorhanden
                    if (!cartSection.querySelector('[data-fpc-stock="preorder"]')) {
                        cartSection.appendChild(notice);
                    }
                }
            }

            // 6. Marker setzen dass Stock aktualisiert wurde
            document.documentElement.setAttribute('data-fpc-stock-refreshed', data.ts);
        }
    })();

    // ========================================================
    // 4. AJAX LIEFERLAND-WECHSEL (v10.7.0)
    // ========================================================
    // Faengt das Shipping-Country Formular ab und sendet per AJAX
    // statt Page-Reload. Aktualisiert Dropdown + FreeShippingBar.
    function handleCountrySubmit(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        // Nur das Shipping-Country Formular abfangen
        var action = form.getAttribute('action') || '';
        if (action.indexOf('shipping_country') === -1) return;
        var isCountryForm = form.classList.contains('box-shipping_country')
                         || form.id === 'countries';
        if (!isCountryForm) return;
        e.preventDefault();
        e.stopPropagation();
        var countrySelect = form.querySelector('select[name="country"]');
        if (!countrySelect) return;
        var newCountryId = countrySelect.value;
        initSession(function() {
            var formData = new FormData(form);
            var postUrl = action;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', postUrl, true);
            xhr.withCredentials = true;
            xhr.timeout = 15000;
            xhr.onload = function() {
                // Alle Dropdowns synchronisieren
                syncCountryDropdown(newCountryId);
                // Offcanvas schliessen
                var offcanvas = document.getElementById('offcanvasSettings');
                if (offcanvas) {
                    var bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvas);
                    if (bsOffcanvas) bsOffcanvas.hide();
                }
                showToast('Lieferland aktualisiert', 'success');
            };
            xhr.onerror = function() {
                // Fallback: normaler Submit
                form.submit();
            };
            xhr.ontimeout = function() {
                form.submit();
            };
            xhr.send(formData);
        });
    }
    document.addEventListener('submit', handleCountrySubmit, true);

    // ========================================================
    // 5. BESUCHERSTATISTIK-TRACKER (v8.3.0)
    // ========================================================
    var pageLoadTime = Date.now();

    var trkImg = new Image();
    trkImg.src = '/fpc_tracker.php?t=pv&p=' + encodeURIComponent(location.pathname) + '&r=' + encodeURIComponent(document.referrer) + '&_=' + Date.now();

    function sendLeaveEvent() {
        var duration = Math.round((Date.now() - pageLoadTime) / 1000);
        if (duration < 1 || duration > 3600) return;
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/fpc_tracker.php?t=leave&d=' + duration);
        } else {
            var img = new Image();
            img.src = '/fpc_tracker.php?t=leave&d=' + duration + '&_=' + Date.now();
        }
    }

    window.addEventListener('beforeunload', sendLeaveEvent);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') sendLeaveEvent();
    });


    // ========================================================
    // 4. UPTAIN INTEGRATION (v11.3.0)
    // ========================================================
    var uptainEnabled = {$fpc_uptain_enabled_js};
    var uptainId = '{$fpc_uptain_id_safe}';
    var uptainConsent = {$fpc_uptain_consent_js};
    
    if (uptainEnabled && uptainId) {
        function loadUptain() {
            // __up_data_qp DIV erstellen
            var qpDiv = document.getElementById('__up_data_qp');
            if (!qpDiv) {
                qpDiv = document.createElement('div');
                qpDiv.id = '__up_data_qp';
                qpDiv.style.display = 'none';
                qpDiv.setAttribute('data-plugin', 'modified 5.3.0 FPC');
                // Seitenname aus URL ableiten
                var pathParts = window.location.pathname.split('/').filter(Boolean);
                var pageName = 'index';
                if (pathParts.length > 0) {
                    // Produktseite erkennen
                    if (document.querySelector('[itemprop="product"]') || document.querySelector('.product-info-box')) {
                        pageName = 'product_info';
                    } else if (document.querySelector('.product-listing') || document.querySelector('.listingbox')) {
                        pageName = 'product_listing';
                    }
                }
                qpDiv.setAttribute('data-page', pageName);
                qpDiv.setAttribute('data-currency', 'EUR');
                document.body.appendChild(qpDiv);
            }
            
            // Live-Daten per AJAX nachladen
            var dataXhr = new XMLHttpRequest();
            var pageName2 = qpDiv.getAttribute('data-page') || 'index';
            dataXhr.open('GET', '/fpc_uptain_data.php?page=' + pageName2 + '&currency=EUR&t=' + Date.now(), true);
            dataXhr.withCredentials = true;
            dataXhr.timeout = 8000;
            dataXhr.onload = function() {
                if (dataXhr.status === 200) {
                    try {
                        var data = JSON.parse(dataXhr.responseText);
                        if (data.ok) {
                            if (data.cart_total) qpDiv.setAttribute('data-cart-total', data.cart_total);
                            if (data.cart_count) qpDiv.setAttribute('data-cart-count', data.cart_count);
                            if (data.currency) qpDiv.setAttribute('data-currency', data.currency);
                            if (data.page) qpDiv.setAttribute('data-page', data.page);
                            // Kundendaten (nur wenn vorhanden)
                            if (data.customer_id > 0) {
                                if (data.email) qpDiv.setAttribute('data-email', data.email);
                                if (data.firstname) qpDiv.setAttribute('data-firstname', data.firstname);
                                if (data.lastname) qpDiv.setAttribute('data-lastname', data.lastname);
                                if (data.gender) qpDiv.setAttribute('data-gender', data.gender);
                                qpDiv.setAttribute('data-uid', data.customer_id);
                                if (data.revenue) qpDiv.setAttribute('data-revenue', data.revenue);
                            }
                        }
                    } catch(e) {}
                }
                // Uptain-Script laden (nach Daten-Update)
                injectUptainScript();
            };
            dataXhr.onerror = function() { injectUptainScript(); };
            dataXhr.ontimeout = function() { injectUptainScript(); };
            dataXhr.send();
        }
        
        function injectUptainScript() {
            // Pruefen ob Script bereits geladen
            if (document.getElementById(uptainId)) return;
            var s = document.createElement('script');
            s.src = 'https://app.uptain.de/js/uptain.js?x=' + uptainId;
            s.id = uptainId;
            s.async = true;
            document.body.appendChild(s);
        }
        
        function checkUptainConsent() {
            if (!uptainConsent) {
                // Kein Consent noetig - direkt laden
                loadUptain();
                return;
            }
            // Cookie-Consent pruefen (oil_data Cookie)
            try {
                var cookies = document.cookie.split(';');
                for (var i = 0; i < cookies.length; i++) {
                    var c = cookies[i].trim();
                    if (c.indexOf('oil_data=') === 0) {
                        var val = decodeURIComponent(c.substring(9));
                        var oilData = JSON.parse(val);
                        if (oilData && oilData.opt_in === true) {
                            loadUptain();
                            return;
                        }
                    }
                }
            } catch(e) {}
            // Noch kein Consent - auf Oil.js Event warten
            document.addEventListener('oil_optin_done', function() {
                loadUptain();
            });
            // Fallback: Periodisch pruefen (falls Event verpasst)
            var consentCheck = setInterval(function() {
                try {
                    var cookies2 = document.cookie.split(';');
                    for (var j = 0; j < cookies2.length; j++) {
                        var c2 = cookies2[j].trim();
                        if (c2.indexOf('oil_data=') === 0) {
                            var val2 = decodeURIComponent(c2.substring(9));
                            var oilData2 = JSON.parse(val2);
                            if (oilData2 && oilData2.opt_in === true) {
                                clearInterval(consentCheck);
                                loadUptain();
                                return;
                            }
                        }
                    }
                } catch(e2) {}
            }, 3000);
            // Timeout nach 120s
            setTimeout(function() { clearInterval(consentCheck); }, 120000);
        }
        
        // Uptain erst nach Session-Init laden
        if (sessionReady) {
            checkUptainConsent();
        } else {
            // Warte auf Session-Init (max 5s)
            var uptainWait = setInterval(function() {
                if (sessionReady || document.documentElement.getAttribute('data-fpc-session') === 'ready') {
                    clearInterval(uptainWait);
                    checkUptainConsent();
                }
            }, 500);
            setTimeout(function() { clearInterval(uptainWait); checkUptainConsent(); }, 5000);
        }
    }

    // ========================================================
    // 6. AGE VERIFICATION MODAL (v11.0.0)
    // ========================================================
    // JS-only Age Gate – Industriestandard fuer FPC-kompatible
    // Altersverifikation. Modal-HTML wird per JS ins DOM gebaut.
    // Uebersetzungen aus fpc_age_verification_i18n.json.
    // Spracherkennung: URL-Pfad > Browser-Sprache > Default DE.
    (function ageVerification() {
        // Bereits verifiziert? -> nichts tun
        if (document.cookie.indexOf('age_verification=true') >= 0) return;

        // Modal bereits im HTML vorhanden (Cache-on-Visit mit Modal)?
        // -> PHP-Version uebernimmt, JS-Inject nicht noetig
        if (document.getElementById('ageVerification')) return;

        // Uebersetzungen (aus PHP injiziert)
        var avT = {$fpc_av_translations_json};
        var avLogo = '{$fpc_av_logo_path}';
        var avExpires = '{$fpc_av_expires_js}';

        // Spracherkennung: URL-Pfad > Browser > Default
        var avLang = 'de';
        var pathMatch = window.location.pathname.match(/^\/([a-z]{2})\//i);
        if (pathMatch && avT[pathMatch[1].toLowerCase()]) {
            avLang = pathMatch[1].toLowerCase();
        } else {
            var bLangs = navigator.languages || [navigator.language || ''];
            for (var i = 0; i < bLangs.length; i++) {
                var bc = bLangs[i].substring(0, 2).toLowerCase();
                if (avT[bc]) { avLang = bc; break; }
            }
        }

        var t = avT[avLang] || avT['de'] || {};
        if (!t.title) return; // Keine Uebersetzungen geladen

        // Modal-HTML ins DOM injizieren
        var avOverlay = document.createElement('div');
        avOverlay.id = 'ageVerification';
        avOverlay.className = 'modal fade';
        avOverlay.setAttribute('tabindex', '-1');
        avOverlay.setAttribute('aria-labelledby', 'ageVerificationLabel');
        avOverlay.setAttribute('aria-hidden', 'true');
        avOverlay.setAttribute('data-bs-backdrop', 'static');
        avOverlay.setAttribute('data-bs-keyboard', 'false');
        avOverlay.innerHTML =
            '<div class="modal-dialog modal-dialog-centered" style="max-width:420px;margin:1rem auto;">' +
            '<div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">' +
            '<div class="modal-body text-center px-4 pt-4 pb-3">' +
            '<img src="' + avLogo + '" alt="Mr. Hanf" class="img-fluid mb-3" style="max-height:120px;">' +
            '<h4 class="fw-bold mb-2" id="ageVerificationLabel">' + t.title + '</h4>' +
            '<p class="text-secondary mb-1">' + t.subtitle + '</p>' +
            '</div>' +
            '<div class="modal-footer flex-column border-0 px-4 pb-4 pt-0 gap-2">' +
            '<button type="button" class="btn btn-success w-100 fw-semibold age-confirm" style="font-size:1.15rem;border-radius:0.5rem;">' + t.btn_ok + '</button>' +
            '<a href="javascript:history.back()" class="btn btn-danger w-100 fw-semibold age-cancel" style="font-size:1.15rem;border-radius:0.5rem;">' + t.btn_no + '</a>' +
            '</div></div></div>';
        document.body.appendChild(avOverlay);

        // Bootstrap Modal oeffnen
        var avModal = new bootstrap.Modal(avOverlay, {keyboard: false, backdrop: 'static'});
        avModal.show();

        // Confirm-Button: Cookie setzen + Modal schliessen
        avOverlay.querySelector('.age-confirm').addEventListener('click', function() {
            document.cookie = 'age_verification=true; expires=' + avExpires + '; path=/; SameSite=Lax';
            avModal.hide();
        });

        // Aufraeumen nach Schliessen
        avOverlay.addEventListener('hidden.bs.modal', function() {
            avOverlay.remove();
            var bd = document.querySelector('.modal-backdrop');
            if (bd) bd.remove();
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        });
    })();

})();
</script>
FPCJS;

// Injiziere vor </body>
$html = str_replace('</body>', $fpc_inject_js . "\n</body>", $html);

// Request-Logging (v9.0.0)
fpc_log_request($fpc_request_start, $fpc_cache_status, $fpc_miss_reason, $fpc_is_bot, $fpc_bot_name, $fpc_http_code);

// Ausgabe
echo $html;
exit;

// ============================================================
// v9.0.0: REQUEST-LOGGING FUNKTION
// ============================================================
function fpc_log_request($start, $status, $reason, $is_bot, $bot_name, $http_code) {
    global $FPC_REQUEST_LOG, $FPC_LOG_DIR;
    if (!$FPC_REQUEST_LOG) return;

    $ttfb = round((microtime(true) - $start) * 1000, 1);
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-';

    // Log-Verzeichnis erstellen
    if (!is_dir($FPC_LOG_DIR)) {
        @mkdir($FPC_LOG_DIR, 0755, true);
    }

    // Tages-Log-Datei
    $logfile = $FPC_LOG_DIR . '/requests_' . date('Y-m-d') . '.log';

    // Kompaktes JSON-Format pro Zeile
    $entry = json_encode(array(
        'ts' => time(),
        'url' => $uri,
        'status' => $status,
        'reason' => $reason,
        'ttfb' => $ttfb,
        'bot' => $is_bot,
        'bot_name' => $bot_name,
        'http_code' => $http_code,
        'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '-',
    ), JSON_UNESCAPED_SLASHES) . "\n";

    @file_put_contents($logfile, $entry, FILE_APPEND | LOCK_EX);

    // Alte Logs aufraumen (aelter als 7 Tage)
    static $cleanup_done = false;
    if (!$cleanup_done && mt_rand(1, 100) === 1) {
        $cleanup_done = true;
        $files = glob($FPC_LOG_DIR . '/requests_*.log');
        if ($files) {
            $cutoff = time() - (7 * 86400);
            foreach ($files as $f) {
                if (filemtime($f) < $cutoff) @unlink($f);
            }
        }
    }
}
