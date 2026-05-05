<?php
/**
 * Mr. Hanf FPC - Uptain Data Endpoint
 * ====================================
 * Liefert Live-Warenkorbdaten und Seiteninfo fuer Uptain auf gecachten Seiten.
 *
 * Aufruf:
 *   GET /fpc_uptain_data.php?page=product_info&currency=EUR
 *   Response: {"ok":true,"cart_total":"47.90","cart_count":2,"currency":"EUR","page":"product_info","customer_id":0}
 *
 * Sicherheit:
 *   - Nur GET-Requests
 *   - Keine sensiblen Kundendaten (E-Mail, Name) im Response fuer Gaeste
 *   - CORS: Nur Same-Origin
 *
 * @version   1.0.0
 * @date      2026-05-05
 */

// Nur GET erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'Method not allowed'));
    exit;
}

// JSON-Response vorbereiten
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-FPC-Uptain-Data: 1');

// modified application_top.php laden (Session + Cart)
$app_top = __DIR__ . '/includes/application_top.php';
$cart_total = '0.00';
$cart_count = 0;
$customer_id = 0;
$customer_email = '';
$customer_firstname = '';
$customer_lastname = '';
$customer_gender = '';
$newsletter = 0;
$revenue = '0.00';

if (is_file($app_top)) {
    // Flag setzen damit Autoincludes wissen, dass wir im Uptain-Data-Modus sind
    define('FPC_UPTAIN_DATA_MODE', true);
    
    // Forbidden history (nicht in Tracking aufnehmen)
    $forbidden_history_sites = array('fpc_uptain_data.php');
    
    ob_start();
    require_once($app_top);
    ob_end_clean();
    
    // Redirect-Header entfernen
    header_remove('Location');
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    
    // Tracking-History bereinigen
    if (isset($_SESSION['tracking']['pageview_history']) && is_array($_SESSION['tracking']['pageview_history'])) {
        $_SESSION['tracking']['pageview_history'] = array_values(
            array_filter($_SESSION['tracking']['pageview_history'], function($url) {
                return (strpos($url, 'fpc_uptain_data') === false);
            })
        );
    }
    
    // Cart-Daten
    if (isset($_SESSION['cart']) && is_object($_SESSION['cart'])) {
        if (method_exists($_SESSION['cart'], 'count_contents')) {
            $cart_count = (int)$_SESSION['cart']->count_contents();
        }
        if (method_exists($_SESSION['cart'], 'show_total')) {
            $cart_total = number_format((float)$_SESSION['cart']->show_total(), 2, '.', '');
        }
    }
    
    // Kundendaten (nur fuer eingeloggte Kunden)
    if (isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0) {
        $customer_id = (int)$_SESSION['customer_id'];
        
        if (function_exists('xtc_db_query')) {
            $cust_query = xtc_db_query(
                "SELECT c.customers_email_address, c.customers_firstname, c.customers_lastname, "
                . "c.customers_gender, c.customers_newsletter, "
                . "COALESCE(SUM(op.final_price * op.products_quantity), 0) AS ordersum "
                . "FROM " . TABLE_CUSTOMERS . " c "
                . "LEFT JOIN " . TABLE_ORDERS . " o ON c.customers_id = o.customers_id "
                . "LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id "
                . "WHERE c.customers_id = " . $customer_id
            );
            $cust_data = xtc_db_fetch_array($cust_query);
            if (is_array($cust_data)) {
                $customer_email = (string)($cust_data['customers_email_address'] ?? '');
                $customer_firstname = (string)($cust_data['customers_firstname'] ?? '');
                $customer_lastname = (string)($cust_data['customers_lastname'] ?? '');
                $customer_gender = (string)($cust_data['customers_gender'] ?? '');
                $newsletter = (int)($cust_data['customers_newsletter'] ?? 0);
                $revenue = number_format((float)($cust_data['ordersum'] ?? 0), 2, '.', '');
            }
        }
    }
}

// Parameter aus Request
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['page']) : 'index';
$currency = isset($_GET['currency']) ? strtoupper(preg_replace('/[^A-Z]/', '', $_GET['currency'])) : 'EUR';

// Response
$response = array(
    'ok'          => true,
    'cart_total'  => $cart_total,
    'cart_count'  => $cart_count,
    'currency'    => $currency,
    'page'        => $page,
    'customer_id' => $customer_id,
);

// Kundendaten nur fuer eingeloggte Kunden mitsenden
if ($customer_id > 0) {
    $response['email'] = $customer_email;
    $response['firstname'] = $customer_firstname;
    $response['lastname'] = $customer_lastname;
    $response['gender'] = $customer_gender;
    $response['newsletter'] = $newsletter;
    $response['revenue'] = $revenue;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
