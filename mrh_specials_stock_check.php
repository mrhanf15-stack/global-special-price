<?php
/**
 * MRH Attribute Specials - Stock Check Cronjob
 *
 * Deaktiviert automatisch Attribut-Sonderpreise wenn der Lagerstand
 * des Attributs auf 0 oder darunter faellt.
 *
 * Logik:
 *   - Prueft alle aktiven Eintraege in mrh_attribute_specials (status=1, check_stock=1)
 *   - Vergleicht mit attributes_stock in products_attributes
 *   - Setzt status=0 wenn attributes_stock <= 0
 *
 * Cronjob: alle 5 Minuten
 * Eintrag in crontab -e:
 *   [star]/5 [star] [star] [star] [star] php /path/to/mrh_specials_stock_check.php >> /tmp/mrh_specials_stock.log 2>&1
 *
 * @version 1.0.0
 * @date 2026-07-02
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0);

chdir("/home/www/doc/28856/dcp288560004/mr-hanf.de/www");
define('_VALID_XTC', true);
require_once('includes/configure.php');

$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if (!$link) { die("[" . date('Y-m-d H:i:s') . "] ERROR: DB connection failed\n"); }
mysqli_set_charset($link, 'utf8mb4');

// Finde alle aktiven Attribut-Sonderpreise wo Lagerstand <= 0
$query = "
    SELECT mas.id, mas.products_id, mas.options_values_id, 
           mas.specials_type, mas.specials_discount_percent, mas.specials_new_price,
           pa.attributes_stock
    FROM mrh_attribute_specials mas
    JOIN products_attributes pa 
        ON mas.products_id = pa.products_id 
        AND mas.options_values_id = pa.options_values_id
    WHERE mas.status = 1 
    AND mas.check_stock = 1
    AND pa.attributes_stock <= 0
";

$result = mysqli_query($link, $query);
$count = mysqli_num_rows($result);

if ($count == 0) {
    // Nichts zu tun
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] $count Attribut-Sonderpreise mit Lager <= 0 gefunden\n";

$deactivated = 0;
$ids_to_deactivate = [];
$products_to_invalidate = [];

while ($row = mysqli_fetch_assoc($result)) {
    $ids_to_deactivate[] = (int)$row['id'];
    $products_to_invalidate[] = (int)$row['products_id'];
    echo "  Deaktiviere: PID=" . $row['products_id'] 
         . " OV=" . $row['options_values_id'] 
         . " Stock=" . $row['attributes_stock'] 
         . " (" . $row['specials_type'] . "=" 
         . ($row['specials_type'] == 'percent' ? $row['specials_discount_percent'] . "%" : $row['specials_new_price']) 
         . ")\n";
}

// Batch-Update: Alle auf einmal deaktivieren
if (!empty($ids_to_deactivate)) {
    $ids_str = implode(',', $ids_to_deactivate);
    $update_q = "UPDATE mrh_attribute_specials 
                 SET status = 0, last_modified = NOW() 
                 WHERE id IN ($ids_str)";
    
    if (mysqli_query($link, $update_q)) {
        $deactivated = mysqli_affected_rows($link);
        echo "  => $deactivated Sonderpreise deaktiviert\n";
    } else {
        echo "  ERROR: " . mysqli_error($link) . "\n";
    }
}

// FPC-Cache invalidieren fuer betroffene Produkte
// Da wir standalone laufen (ohne xtc_db_query), loeschen wir den FPC direkt
$unique_pids = array_unique($products_to_invalidate);
$fpc_dir = DIR_FS_CATALOG . 'cache/fpc/';
if (is_dir($fpc_dir)) {
    $cleared = 0;
    foreach ($unique_pids as $pid) {
        $pattern = $fpc_dir . '*_p' . $pid . '_*';
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $f) { @unlink($f); $cleared++; }
        }
    }
    if ($cleared > 0) echo "  FPC: $cleared Cache-Dateien geloescht\n";
} else {
    echo "  FPC: Kein Cache-Verzeichnis gefunden (OK)\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Fertig: $deactivated deaktiviert\n";
mysqli_close($link);
