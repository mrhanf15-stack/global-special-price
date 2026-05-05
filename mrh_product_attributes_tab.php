<?php
/**
 * MRH Product Attributes - categories.php Product Edit Hook
 * Autoinclude: ~/admin/includes/extra/modules/new_product/
 *
 * Injects the "Eigenschaften (MRH)" tab into the product edit form.
 *
 * v1.6.0 Changes:
 * - Custom Fields D&D: Can now be positioned BETWEEN standard fields (not just at bottom)
 * - Custom Field positions sync across ALL languages
 * - Badge-Presets: Multiple presets can be selected simultaneously (additive)
 * - Current Icons: Added "Als Preset speichern" button per badge selection
 * - Icon Library (FA7): Default CLOSED (accordion), click header to expand
 *
 * @package MRH_Product_Attributes
 * @version 1.6.0
 */

if (!defined('TABLE_CONFIGURATION') && !defined('_VALID_XTC')) { return; }

// Load module class
if (!class_exists('MrhProductAttributes')) {
    $mrh_pa_class = DIR_FS_CATALOG . 'includes/external/mrh_product_attributes/mrh_product_attributes.php';
    if (file_exists($mrh_pa_class)) {
        require_once($mrh_pa_class);
    }
}

// Self-install check (runs once per admin session)
if (class_exists('MrhProductAttributes')) {
    MrhProductAttributes::checkAndInstall();
}

// Only show for existing products (not new ones without ID)
$mrh_pa_products_id = isset($pInfo->products_id) ? (int)$pInfo->products_id : 0;
if ($mrh_pa_products_id == 0 && isset($_GET['pID'])) {
    $mrh_pa_products_id = (int)$_GET['pID'];
}

// Load existing attributes for all languages
$mrh_pa_all_attrs = [];
if ($mrh_pa_products_id > 0 && class_exists('MrhProductAttributes')) {
    $mrh_pa_all_attrs = MrhProductAttributes::getAllLanguageAttributes($mrh_pa_products_id);
}

// Get languages
$mrh_pa_languages = [];
$mrh_pa_lang_q = xtc_db_query("SELECT languages_id, name, code, image, directory FROM languages ORDER BY sort_order");
while ($mrh_pa_lang_row = xtc_db_fetch_array($mrh_pa_lang_q)) {
    $mrh_pa_languages[] = $mrh_pa_lang_row;
}

// Standard fields definition
$mrh_pa_fields = [
    'gender'         => ['label' => defined('MRH_PA_FIELD_GENDER') ? MRH_PA_FIELD_GENDER : 'Geschlecht', 'type' => 'select', 'priority' => false],
    'flowering_type' => ['label' => defined('MRH_PA_FIELD_FLOWERING_TYPE') ? MRH_PA_FIELD_FLOWERING_TYPE : 'Bluetentyp', 'type' => 'select', 'priority' => false],
    'type'           => ['label' => defined('MRH_PA_FIELD_TYPE') ? MRH_PA_FIELD_TYPE : 'Sorte', 'type' => 'select', 'priority' => 'prio'],
    'thc'            => ['label' => defined('MRH_PA_FIELD_THC') ? MRH_PA_FIELD_THC : 'THC', 'type' => 'text', 'priority' => 'prio'],
    'cbd'            => ['label' => defined('MRH_PA_FIELD_CBD') ? MRH_PA_FIELD_CBD : 'CBD', 'type' => 'text', 'priority' => 'prio'],
    'cross_genetics' => ['label' => defined('MRH_PA_FIELD_CROSS') ? MRH_PA_FIELD_CROSS : 'Kreuzung', 'type' => 'text', 'priority' => 'alt'],
    'flowering_time' => ['label' => defined('MRH_PA_FIELD_FLOWERING_TIME') ? MRH_PA_FIELD_FLOWERING_TIME : 'Bluetezeit', 'type' => 'text', 'priority' => 'alt'],
    'yield_indoor'   => ['label' => defined('MRH_PA_FIELD_YIELD_INDOOR') ? MRH_PA_FIELD_YIELD_INDOOR : 'Ertrag Indoor', 'type' => 'text', 'priority' => 'alt'],
    'harvest_time'   => ['label' => defined('MRH_PA_FIELD_HARVEST_TIME') ? MRH_PA_FIELD_HARVEST_TIME : 'Erntezeit', 'type' => 'text', 'priority' => 'alt'],
    'yield_outdoor'  => ['label' => defined('MRH_PA_FIELD_YIELD_OUTDOOR') ? MRH_PA_FIELD_YIELD_OUTDOOR : 'Ertrag Outdoor', 'type' => 'text', 'priority' => false],
    'height_indoor'  => ['label' => defined('MRH_PA_FIELD_HEIGHT_INDOOR') ? MRH_PA_FIELD_HEIGHT_INDOOR : 'Hoehe Indoor', 'type' => 'text', 'priority' => false],
    'height_outdoor' => ['label' => defined('MRH_PA_FIELD_HEIGHT_OUTDOOR') ? MRH_PA_FIELD_HEIGHT_OUTDOOR : 'Hoehe Outdoor', 'type' => 'text', 'priority' => false],
    'climate'        => ['label' => defined('MRH_PA_FIELD_CLIMATE') ? MRH_PA_FIELD_CLIMATE : 'Klima', 'type' => 'text', 'priority' => false],
    'effect'         => ['label' => defined('MRH_PA_FIELD_EFFECT') ? MRH_PA_FIELD_EFFECT : 'Wirkung', 'type' => 'text', 'priority' => false],
    'taste'          => ['label' => defined('MRH_PA_FIELD_TASTE') ? MRH_PA_FIELD_TASTE : 'Geschmack', 'type' => 'text', 'priority' => false],
    'growing'        => ['label' => defined('MRH_PA_FIELD_GROWING') ? MRH_PA_FIELD_GROWING : 'Anbau', 'type' => 'select', 'priority' => false],
];

// Apply saved field order (if exists for this product)
if ($mrh_pa_products_id > 0 && class_exists('MrhProductAttributes')) {
    $mrh_pa_saved_order_json = MrhProductAttributes::getConfig('field_order_' . $mrh_pa_products_id);
    if ($mrh_pa_saved_order_json) {
        $mrh_pa_saved_order = json_decode($mrh_pa_saved_order_json, true);
        if (is_array($mrh_pa_saved_order) && !empty($mrh_pa_saved_order)) {
            $mrh_pa_fields_ordered = [];
            foreach ($mrh_pa_saved_order as $key) {
                if (isset($mrh_pa_fields[$key])) {
                    $mrh_pa_fields_ordered[$key] = $mrh_pa_fields[$key];
                }
            }
            foreach ($mrh_pa_fields as $key => $def) {
                if (!isset($mrh_pa_fields_ordered[$key])) {
                    $mrh_pa_fields_ordered[$key] = $def;
                }
            }
            $mrh_pa_fields = $mrh_pa_fields_ordered;
        }
    }
}

// Select options
$mrh_pa_select_options = [
    'gender' => [
        '' => '-- Bitte waehlen --',
        'feminized' => defined('MRH_PA_GENDER_FEMINIZED') ? MRH_PA_GENDER_FEMINIZED : 'Feminisiert',
        'regular' => defined('MRH_PA_GENDER_REGULAR') ? MRH_PA_GENDER_REGULAR : 'Regulaer',
        'autoflower' => defined('MRH_PA_GENDER_AUTOFLOWER') ? MRH_PA_GENDER_AUTOFLOWER : 'Autoflowering',
    ],
    'flowering_type' => [
        '' => '-- Bitte waehlen --',
        'photoperiod' => defined('MRH_PA_FLOWERING_PHOTOPERIOD') ? MRH_PA_FLOWERING_PHOTOPERIOD : 'Photoperiodisch',
        'autoflower' => defined('MRH_PA_FLOWERING_AUTOFLOWER') ? MRH_PA_FLOWERING_AUTOFLOWER : 'Autoflowering',
    ],
    'type' => [
        '' => '-- Bitte waehlen --',
        'indica' => defined('MRH_PA_TYPE_INDICA') ? MRH_PA_TYPE_INDICA : 'Indica',
        'sativa' => defined('MRH_PA_TYPE_SATIVA') ? MRH_PA_TYPE_SATIVA : 'Sativa',
        'hybrid' => defined('MRH_PA_TYPE_HYBRID') ? MRH_PA_TYPE_HYBRID : 'Hybrid',
        'indica_dom' => defined('MRH_PA_TYPE_INDICA_DOM') ? MRH_PA_TYPE_INDICA_DOM : 'Indica-dominant',
        'sativa_dom' => defined('MRH_PA_TYPE_SATIVA_DOM') ? MRH_PA_TYPE_SATIVA_DOM : 'Sativa-dominant',
    ],
    'growing' => [
        '' => '-- Bitte waehlen --',
        'indoor' => defined('MRH_PA_GROWING_INDOOR') ? MRH_PA_GROWING_INDOOR : 'Indoor',
        'outdoor' => defined('MRH_PA_GROWING_OUTDOOR') ? MRH_PA_GROWING_OUTDOOR : 'Outdoor',
        'greenhouse' => defined('MRH_PA_GROWING_GREENHOUSE') ? MRH_PA_GROWING_GREENHOUSE : 'Gewaechshaus',
        'all' => defined('MRH_PA_GROWING_ALL') ? MRH_PA_GROWING_ALL : 'Indoor/Outdoor',
    ],
];

// Decode pictos from first language for initial display
$mrh_pa_first_attr = !empty($mrh_pa_all_attrs) ? reset($mrh_pa_all_attrs) : [];
$mrh_pa_pictos = [];
if (!empty($mrh_pa_first_attr['pictos'])) {
    $mrh_pa_pictos = json_decode($mrh_pa_first_attr['pictos'], true) ?: [];
}
$mrh_pa_cups = (int)($mrh_pa_first_attr['cannabis_cups'] ?? 0);

// Auto-detect preset from loaded data
$mrh_pa_detected_preset = '';
if (!empty($mrh_pa_first_attr)) {
    $g = $mrh_pa_first_attr['gender'] ?? '';
    $f = $mrh_pa_first_attr['flowering_type'] ?? '';
    if ($g === 'feminized' && $f === 'autoflower') $mrh_pa_detected_preset = 'autoflower';
    elseif ($g === 'feminized' && $f === 'photoperiod') $mrh_pa_detected_preset = 'feminized';
    elseif ($g === 'regular' && $f === 'autoflower') $mrh_pa_detected_preset = 'auto_regular';
    elseif ($g === 'regular' && $f === 'photoperiod') $mrh_pa_detected_preset = 'regular';
    elseif ($g === 'regular') $mrh_pa_detected_preset = 'regular';
    elseif ($g === 'feminized') $mrh_pa_detected_preset = 'feminized';
}

// SVG badge base URL
$mrh_pa_badge_base = 'templates/tpl_mrh_2026/img/badges/';

// Load saved icon presets for product tab display
$mrh_pa_icon_presets = [];
if (class_exists('MrhProductAttributes') && method_exists('MrhProductAttributes', 'getIconPresets')) {
    $mrh_pa_icon_presets = MrhProductAttributes::getIconPresets();
}
?>

<!-- FontAwesome 7 from template (required for FA7 icon previews in admin) -->
<link rel="stylesheet" href="/templates/tpl_mrh_2026/css/fontawesome-7.css" />
<link rel="stylesheet" href="/templates/tpl_mrh_2026/css/fontawesome-6.css" />
<link rel="stylesheet" href="/templates/tpl_mrh_2026/css/fontawesome-6-custom.css" />
<!-- FontAwesome 4.7 CDN (fallback for legacy icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha256-eZrrJcwDc/3uDhsdt61sL2oOBY362qM3lon1gyExkL0=" crossorigin="anonymous" />

<!-- MRH Product Attributes Tab v1.6.0 -->
<style>
#mrh-pa-container { margin: 10px 0; padding: 0; }
#mrh-pa-container .mrh-pa-header {
    background: #2c3e50; color: #fff; padding: 12px 20px;
    border-radius: 6px 6px 0 0; display: flex; align-items: center; justify-content: space-between;
}
#mrh-pa-container .mrh-pa-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
#mrh-pa-container .mrh-pa-body {
    border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px;
    padding: 15px; background: #fafafa;
}
#mrh-pa-container .mrh-pa-preset-tabs {
    display: flex; gap: 0; margin-bottom: 15px;
    border: 2px solid #2c3e50; border-radius: 6px; overflow: hidden;
}
#mrh-pa-container .mrh-pa-preset-tab {
    flex: 1; padding: 10px 12px; border: none; border-right: 1px solid #dee2e6;
    background: #fff; cursor: pointer; font-size: 13px; font-weight: 600;
    transition: all 0.2s; text-align: center; white-space: nowrap;
}
#mrh-pa-container .mrh-pa-preset-tab:last-child { border-right: none; }
#mrh-pa-container .mrh-pa-preset-tab:hover { background: #e8f5e9; }
#mrh-pa-container .mrh-pa-preset-tab.active { background: #27ae60; color: #fff; }
#mrh-pa-container .mrh-pa-preset-tab .fa { margin-right: 4px; }
#mrh-pa-container .mrh-pa-preset-tab .preset-svg { width: 14px; height: 14px; vertical-align: middle; margin-right: 4px; }
#mrh-pa-container .mrh-pa-lang-tabs {
    display: flex; gap: 3px; margin-bottom: 10px; border-bottom: 2px solid #27ae60;
}
#mrh-pa-container .mrh-pa-lang-tab {
    padding: 6px 14px; border: 1px solid #ccc; border-bottom: none;
    border-radius: 4px 4px 0 0; background: #fff; cursor: pointer; font-size: 12px;
}
#mrh-pa-container .mrh-pa-lang-tab.active { background: #27ae60; color: #fff; border-color: #27ae60; }
#mrh-pa-container .mrh-pa-lang-panel { display: none; }
#mrh-pa-container .mrh-pa-lang-panel.active { display: block; }
#mrh-pa-container .mrh-pa-field-row {
    display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 6px 0;
    border-bottom: 1px solid #eee; transition: all 0.2s;
}
#mrh-pa-container .mrh-pa-field-row[draggable] { cursor: grab; }
#mrh-pa-container .mrh-pa-field-row.dragging { opacity: 0.4; border-style: dashed; }
#mrh-pa-container .mrh-pa-field-row.drag-over-field { border-top: 3px solid #27ae60; margin-top: -3px; }
#mrh-pa-container .mrh-pa-field-row .field-drag-handle {
    cursor: grab; color: #bbb; font-size: 14px; flex-shrink: 0; width: 18px; text-align: center;
}
#mrh-pa-container .mrh-pa-field-row .field-drag-handle:hover { color: #27ae60; }
#mrh-pa-container .mrh-pa-field-row .field-remove-btn {
    cursor: pointer; color: #e74c3c; font-weight: 700; font-size: 16px; flex-shrink: 0;
    width: 22px; height: 22px; text-align: center; line-height: 22px; border-radius: 50%;
    transition: all 0.15s; border: none; background: none; padding: 0;
}
#mrh-pa-container .mrh-pa-field-row .field-remove-btn:hover { background: #fde8e8; }
#mrh-pa-container .mrh-pa-field-row.priority { background: #f0fdf4; padding: 6px 8px; border-radius: 4px; }
#mrh-pa-container .mrh-pa-field-row.alt-priority { background: #f0f4fd; padding: 6px 8px; border-radius: 4px; }
#mrh-pa-container .mrh-pa-field-label { width: 180px; font-weight: 600; font-size: 13px; flex-shrink: 0; }
#mrh-pa-container .mrh-pa-field-label .priority-star { color: #27ae60; margin-left: 4px; }
#mrh-pa-container .mrh-pa-field-label .alt-priority-star { color: #3498db; margin-left: 4px; }
#mrh-pa-container .mrh-pa-field-input { flex: 1; }
#mrh-pa-container .mrh-pa-field-input input,
#mrh-pa-container .mrh-pa-field-input select {
    width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;
    font-size: 13px; box-sizing: border-box;
}
#mrh-pa-container .mrh-pa-field-input select { background: #fff; }
#mrh-pa-container .mrh-pa-seed-toggle {
    display: flex; align-items: center; gap: 10px; margin-bottom: 15px;
    padding: 10px; background: #fff3cd; border-radius: 4px; border: 1px solid #ffc107;
}
#mrh-pa-container .mrh-pa-actions {
    display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;
}
#mrh-pa-container .mrh-pa-btn {
    padding: 8px 18px; border: none; border-radius: 4px; cursor: pointer;
    font-size: 13px; font-weight: 600; transition: all 0.2s;
}
#mrh-pa-container .mrh-pa-btn-primary { background: #27ae60; color: #fff; }
#mrh-pa-container .mrh-pa-btn-primary:hover { background: #219a52; }
#mrh-pa-container .mrh-pa-btn-ai { background: #3498db; color: #fff; }
#mrh-pa-container .mrh-pa-btn-ai:hover { background: #2980b9; }
#mrh-pa-container .mrh-pa-btn-secondary { background: #95a5a6; color: #fff; }
#mrh-pa-container .mrh-pa-btn-add { background: #fff; border: 1px dashed #27ae60; color: #27ae60; }
#mrh-pa-container .mrh-pa-btn-add:hover { background: #f0fdf4; }
#mrh-pa-container .mrh-pa-custom-fields { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc; }
#mrh-pa-container .mrh-pa-status { font-size: 12px; color: #666; margin-top: 5px; }

/* Save Button */
#mrh-pa-container .mrh-pa-btn-save {
    background: #27ae60; color: #fff; font-size: 14px; font-weight: 700;
    padding: 10px 28px; border: none; border-radius: 6px; cursor: pointer;
    transition: all 0.2s; box-shadow: 0 2px 6px rgba(39,174,96,0.3);
}
#mrh-pa-container .mrh-pa-btn-save:hover { background: #219a52; box-shadow: 0 4px 12px rgba(39,174,96,0.4); }
#mrh-pa-container .mrh-pa-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
#mrh-pa-container .mrh-pa-btn-save .fa-spin { margin-right: 6px; }
#mrh-pa-container .mrh-pa-save-status {
    display: inline-block; font-size: 13px; margin-left: 12px; vertical-align: middle;
}
#mrh-pa-container .mrh-pa-status .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
#mrh-pa-container .mrh-pa-status .badge-success { background: #d4edda; color: #155724; }
#mrh-pa-container .mrh-pa-status .badge-warning { background: #fff3cd; color: #856404; }
#mrh-pa-container .mrh-pa-status .badge-info { background: #d1ecf1; color: #0c5460; }

/* ================================================================ */
/* ICON EDITOR v1.3.0 - DnD, numeric size, SVG support              */
/* ================================================================ */
#mrh-pa-container .mrh-pa-icon-section {
    margin-top: 20px; padding: 15px; background: #fff; border: 2px solid #2c3e50; border-radius: 6px;
}
#mrh-pa-container .mrh-pa-icon-section h4 {
    margin: 0 0 12px 0; font-size: 15px; color: #2c3e50; font-weight: 700;
    border-bottom: 2px solid #27ae60; padding-bottom: 8px;
}

/* Current icons list (editable + draggable) */
#mrh-pa-container .mrh-pa-icon-list {
    display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; min-height: 44px;
    padding: 10px; background: #f8f9fa; border-radius: 6px; border: 2px dashed #dee2e6;
}
#mrh-pa-container .mrh-pa-icon-item {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px;
    background: #fff; border: 1px solid #ddd; border-radius: 20px; font-size: 12px;
    cursor: grab; transition: all 0.2s; position: relative; user-select: none;
}
#mrh-pa-container .mrh-pa-icon-item:hover { border-color: #27ae60; box-shadow: 0 2px 8px rgba(39,174,96,0.15); }
#mrh-pa-container .mrh-pa-icon-item.dragging { opacity: 0.4; border-style: dashed; }
#mrh-pa-container .mrh-pa-icon-item.drag-over { border-color: #e74c3c; box-shadow: 0 0 0 2px #e74c3c; }
#mrh-pa-container .mrh-pa-icon-item .icon-preview { font-size: 18px; min-width: 20px; text-align: center; }
#mrh-pa-container .mrh-pa-icon-item .icon-preview-svg { width: 18px; height: 18px; vertical-align: middle; }
#mrh-pa-container .mrh-pa-icon-item .icon-title { font-weight: 500; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#mrh-pa-container .mrh-pa-icon-item .icon-edit-color {
    width: 24px; height: 24px; padding: 0; border: 1px solid #ccc; border-radius: 50%;
    cursor: pointer; vertical-align: middle;
}
#mrh-pa-container .mrh-pa-icon-item .icon-edit-bgcolor {
    width: 24px; height: 24px; padding: 0; border: 1px solid #ccc; border-radius: 4px;
    cursor: pointer; vertical-align: middle;
}
#mrh-pa-container .mrh-pa-icon-item .icon-edit-bordercolor {
    width: 24px; height: 24px; padding: 0; border: 1px solid #ccc; border-radius: 4px;
    cursor: pointer; vertical-align: middle;
}
#mrh-pa-container .mrh-pa-icon-item .icon-color-label {
    font-size: 9px; color: #888; white-space: nowrap;
}
#mrh-pa-container .mrh-pa-icon-item .icon-badge-preview {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 3px 6px; border-radius: 12px; border: 1px solid #ddd;
    font-size: 11px; min-width: 28px; min-height: 24px;
}
#mrh-pa-container .mrh-pa-icon-item .icon-edit-size {
    width: 50px; padding: 2px 4px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;
    background: #fff; text-align: center;
}
#mrh-pa-container .mrh-pa-icon-item .icon-remove {
    cursor: pointer; color: #e74c3c; font-weight: 700; margin-left: 2px;
    width: 18px; height: 18px; text-align: center; line-height: 18px;
    border-radius: 50%; font-size: 14px; transition: all 0.15s;
}
#mrh-pa-container .mrh-pa-icon-item .icon-remove:hover { background: #fde8e8; }
#mrh-pa-container .mrh-pa-icon-item .drag-handle {
    cursor: grab; color: #aaa; font-size: 14px; margin-right: 2px;
}

/* Icon Library (searchable) - ACCORDION v1.6.0 */
#mrh-pa-container .mrh-pa-icon-library {
    margin-top: 12px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;
}
#mrh-pa-container .mrh-pa-icon-library-header {
    background: #2c3e50; color: #fff; padding: 10px 14px; display: flex; align-items: center; gap: 10px;
    cursor: pointer; user-select: none;
}
#mrh-pa-container .mrh-pa-icon-library-header:hover { background: #34495e; }
#mrh-pa-container .mrh-pa-icon-library-header .lib-toggle {
    font-size: 12px; transition: transform 0.3s; margin-right: 4px;
}
#mrh-pa-container .mrh-pa-icon-library-header .lib-toggle.open { transform: rotate(90deg); }
#mrh-pa-container .mrh-pa-icon-library-header .lib-title { font-weight: 600; font-size: 13px; white-space: nowrap; }
#mrh-pa-container .mrh-pa-icon-library-header input {
    flex: 1; padding: 6px 10px; border: none; border-radius: 4px; font-size: 13px; background: rgba(255,255,255,0.9);
}
#mrh-pa-container .mrh-pa-icon-library-header .lib-count { font-size: 11px; opacity: 0.8; white-space: nowrap; }
#mrh-pa-container .mrh-pa-icon-library-body { display: none; }
#mrh-pa-container .mrh-pa-icon-library-body.open { display: block; }
#mrh-pa-container .mrh-pa-icon-library-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 6px;
    padding: 12px; max-height: 400px; overflow-y: auto; background: #fafafa;
}
#mrh-pa-container .mrh-pa-icon-lib-btn {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 12px 6px; background: #fff; border: 1px solid #e8e8e8; border-radius: 6px;
    cursor: pointer; transition: all 0.15s; min-height: 80px;
}
#mrh-pa-container .mrh-pa-icon-lib-btn:hover { border-color: #27ae60; background: #f0fdf4; transform: scale(1.05); }
#mrh-pa-container .mrh-pa-icon-lib-btn.selected { border-color: #27ae60; background: #d4edda; box-shadow: 0 0 0 2px #27ae60; }
#mrh-pa-container .mrh-pa-icon-lib-btn .fa,
#mrh-pa-container .mrh-pa-icon-lib-btn .fa-solid,
#mrh-pa-container .mrh-pa-icon-lib-btn .fa-regular,
#mrh-pa-container .mrh-pa-icon-lib-btn .fa-brands { font-size: 32px; color: #333; margin-bottom: 6px; }
#mrh-pa-container .mrh-pa-icon-lib-btn .icon-name { font-size: 10px; color: #666; text-align: center; word-break: break-all; line-height: 1.3; max-width: 90px; overflow: hidden; text-overflow: ellipsis; }

/* Quick-Pick predefined icons */
#mrh-pa-container .mrh-pa-quickpick {
    margin-bottom: 12px; padding: 10px; background: #eef7ee; border: 1px solid #c3e6c3; border-radius: 6px;
}
#mrh-pa-container .mrh-pa-quickpick-title { font-size: 12px; font-weight: 700; color: #2c3e50; margin-bottom: 8px; }
#mrh-pa-container .mrh-pa-quickpick-grid { display: flex; flex-wrap: wrap; gap: 6px; }
#mrh-pa-container .mrh-pa-quickpick-btn {
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px;
    background: #fff; border: 2px solid #ddd; border-radius: 20px; cursor: pointer;
    font-size: 12px; transition: all 0.15s; white-space: nowrap;
}
#mrh-pa-container .mrh-pa-quickpick-btn:hover { border-color: #27ae60; background: #f0fdf4; transform: scale(1.03); }
#mrh-pa-container .mrh-pa-quickpick-btn.active { border-color: #27ae60; background: #d4edda; box-shadow: 0 0 0 2px #27ae60; }
#mrh-pa-container .mrh-pa-quickpick-btn .fa,
#mrh-pa-container .mrh-pa-quickpick-btn .fa-solid,
#mrh-pa-container .mrh-pa-quickpick-btn .fa-regular,
#mrh-pa-container .mrh-pa-quickpick-btn .fa-brands { font-size: 16px; }
#mrh-pa-container .mrh-pa-quickpick-btn .qp-svg { width: 16px; height: 16px; vertical-align: middle; }
#mrh-pa-container .mrh-pa-quickpick-btn .qp-edit { display: none; align-items: center; gap: 4px; margin-left: 4px; }
#mrh-pa-container .mrh-pa-quickpick-btn.active .qp-edit { display: inline-flex; }
#mrh-pa-container .mrh-pa-quickpick-btn .qp-color { width: 20px; height: 20px; padding: 0; border: 1px solid #aaa; border-radius: 50%; cursor: pointer; }
#mrh-pa-container .mrh-pa-quickpick-btn .qp-size-input { width: 40px; padding: 1px 3px; border: 1px solid #aaa; border-radius: 3px; font-size: 10px; text-align: center; }
#mrh-pa-container .mrh-pa-quickpick-btn .qp-title-input { width: 80px; padding: 1px 3px; border: 1px solid #aaa; border-radius: 3px; font-size: 10px; }
#mrh-pa-container .mrh-pa-style-switcher { display: inline-flex; gap: 4px; margin: 0 8px; }
#mrh-pa-container .mrh-pa-style-btn { padding: 3px 10px; border: 1px solid #ccc; border-radius: 4px; background: #f5f5f5; cursor: pointer; font-size: 11px; font-weight: 600; }
#mrh-pa-container .mrh-pa-style-btn:hover { background: #e0e0e0; }
#mrh-pa-container .mrh-pa-style-btn.active { background: #27ae60; color: #fff; border-color: #27ae60; }

/* Add icon controls */
#mrh-pa-container .mrh-pa-icon-add-controls {
    display: flex; gap: 8px; align-items: center; margin-top: 10px; padding: 10px;
    background: #f0f0f0; border-radius: 6px; font-size: 12px;
}
#mrh-pa-container .mrh-pa-icon-add-controls input[type="color"] {
    width: 32px; height: 28px; padding: 0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;
}
#mrh-pa-container .mrh-pa-icon-add-controls .add-color-group {
    display: inline-flex; align-items: center; gap: 3px;
}
#mrh-pa-container .mrh-pa-icon-add-controls .add-color-label {
    font-size: 10px; color: #666; white-space: nowrap;
}

/* Badge Presets in product tab (v1.6.0) */
#mrh-pa-container .mrh-pa-badge-presets {
    margin: 12px 0; padding: 10px; background: #f0f4fd; border: 1px solid #b8d4fe; border-radius: 6px;
}
#mrh-pa-container .mrh-pa-badge-presets-title {
    font-size: 12px; font-weight: 700; color: #2c3e50; margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between;
}
#mrh-pa-container .mrh-pa-badge-presets-grid { display: flex; flex-wrap: wrap; gap: 6px; }
#mrh-pa-container .mrh-pa-badge-preset-btn {
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px;
    background: #fff; border: 2px solid #ddd; border-radius: 20px; cursor: pointer;
    font-size: 12px; transition: all 0.15s; white-space: nowrap;
}
#mrh-pa-container .mrh-pa-badge-preset-btn:hover { border-color: #3498db; background: #eef6fd; }
#mrh-pa-container .mrh-pa-badge-preset-btn.active { border-color: #3498db; background: #d1ecf1; box-shadow: 0 0 0 2px #3498db; }
#mrh-pa-container .mrh-pa-badge-preset-btn .preset-icon { font-size: 14px; }
#mrh-pa-container .mrh-pa-save-preset-btn {
    padding: 4px 12px; background: #3498db; color: #fff; border: none; border-radius: 4px;
    cursor: pointer; font-size: 11px; font-weight: 600; transition: all 0.2s;
}
#mrh-pa-container .mrh-pa-save-preset-btn:hover { background: #2980b9; }

/* Cannabis Cup section */
#mrh-pa-container .mrh-pa-cups-section {
    margin-top: 15px; padding: 15px; background: #fffbf0; border: 2px solid #f0c040; border-radius: 6px;
}
#mrh-pa-container .mrh-pa-cups-section h4 {
    margin: 0 0 10px 0; font-size: 14px; color: #8a6d3b; font-weight: 700;
}
#mrh-pa-container .mrh-pa-cups-row { display: flex; align-items: center; gap: 12px; }
#mrh-pa-container .mrh-pa-cups-row input[type="number"] {
    width: 80px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;
}
#mrh-pa-container .mrh-pa-cups-preview { display: flex; align-items: center; gap: 2px; font-size: 18px; color: #f39c12; }
#mrh-pa-container .mrh-pa-cups-preview .cup-number { font-weight: 700; font-size: 16px; margin-left: 4px; color: #8a6d3b; }

/* Custom field row within standard fields (v1.6.0) */
#mrh-pa-container .mrh-pa-field-row.mrh-pa-custom-row { background: #fffef5; border-left: 3px solid #f39c12; padding-left: 8px; }
#mrh-pa-container .mrh-pa-field-row .mrh-pa-field-drag {
    cursor: grab; color: #bbb; font-size: 14px; flex-shrink: 0; width: 18px; text-align: center;
}
</style>

<div id="mrh-pa-container">
    <div class="mrh-pa-header">
        <h3><span class="fa fa-leaf"></span> <?php echo defined('MRH_PA_PRODUCT_TAB') ? MRH_PA_PRODUCT_TAB : 'Eigenschaften (MRH)'; ?></h3>
        <div class="mrh-pa-status">
            <?php if (!empty($mrh_pa_all_attrs)): ?>
                <?php
                    $first_attr = reset($mrh_pa_all_attrs);
                    $filled = (int)($first_attr['fields_filled'] ?? 0);
                    $source = $first_attr['data_source'] ?? 'manual';
                ?>
                <span class="badge badge-success"><?php echo $filled; ?> Felder</span>
                <span class="badge badge-info"><?php echo ucfirst($source); ?></span>
            <?php else: ?>
                <span class="badge badge-warning">Keine Daten</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mrh-pa-body">
        <?php if ($mrh_pa_products_id == 0): ?>
            <p><em>Bitte speichern Sie das Produkt zuerst, bevor Sie Eigenschaften hinzufuegen.</em></p>
        <?php else: ?>

        <!-- Seed/Non-Seed Toggle -->
        <div class="mrh-pa-seed-toggle">
            <label><strong><?php echo defined('MRH_PA_PRODUCT_IS_SEED') ? MRH_PA_PRODUCT_IS_SEED : 'Ist Saatgut-Produkt'; ?>:</strong></label>
            <select name="mrh_pa[is_seed]" id="mrh_pa_is_seed">
                <option value="1" <?php echo (empty($mrh_pa_all_attrs) || ($first_attr['is_seed'] ?? 1) == 1) ? 'selected' : ''; ?>>
                    <?php echo defined('MRH_PA_PRODUCT_IS_SEED_YES') ? MRH_PA_PRODUCT_IS_SEED_YES : 'Ja (Samen)'; ?>
                </option>
                <option value="0" <?php echo (!empty($mrh_pa_all_attrs) && ($first_attr['is_seed'] ?? 1) == 0) ? 'selected' : ''; ?>>
                    <?php echo defined('MRH_PA_PRODUCT_IS_SEED_NO') ? MRH_PA_PRODUCT_IS_SEED_NO : 'Nein (Non-Seed)'; ?>
                </option>
            </select>
        </div>

        <!-- Preset Tabs -->
        <div class="mrh-pa-preset-tabs">
            <div class="mrh-pa-preset-tab <?php echo $mrh_pa_detected_preset === 'feminized' ? 'active' : ''; ?>" data-preset="feminized" onclick="mrhPaApplyPreset('feminized')">
                <span class="fa fa-venus"></span> <?php echo defined('MRH_PA_PRESET_FEMINIZED') ? MRH_PA_PRESET_FEMINIZED : 'Feminisiert'; ?>
            </div>
            <div class="mrh-pa-preset-tab <?php echo $mrh_pa_detected_preset === 'autoflower' ? 'active' : ''; ?>" data-preset="autoflower" onclick="mrhPaApplyPreset('autoflower')">
                <span class="fa fa-bolt"></span> <?php echo defined('MRH_PA_PRESET_AUTOFLOWER') ? MRH_PA_PRESET_AUTOFLOWER : 'Autoflowering'; ?>
            </div>
            <div class="mrh-pa-preset-tab <?php echo $mrh_pa_detected_preset === 'regular' ? 'active' : ''; ?>" data-preset="regular" onclick="mrhPaApplyPreset('regular')">
                <img class="preset-svg" src="/<?php echo $mrh_pa_badge_base; ?>male.svg" alt="M"> <?php echo defined('MRH_PA_PRESET_REGULAR') ? MRH_PA_PRESET_REGULAR : 'Regulaer'; ?>
            </div>
            <div class="mrh-pa-preset-tab <?php echo $mrh_pa_detected_preset === 'auto_regular' ? 'active' : ''; ?>" data-preset="auto_regular" onclick="mrhPaApplyPreset('auto_regular')">
                <span class="fa fa-bolt"></span><img class="preset-svg" src="/<?php echo $mrh_pa_badge_base; ?>male.svg" alt="M"> <?php echo defined('MRH_PA_PRESET_AUTO_REGULAR') ? MRH_PA_PRESET_AUTO_REGULAR : 'Auto Regulaer'; ?>
            </div>
        </div>

        <!-- Language Tabs -->
        <div class="mrh-pa-lang-tabs">
            <?php foreach ($mrh_pa_languages as $idx => $lang): ?>
                <div class="mrh-pa-lang-tab <?php echo $idx === 0 ? 'active' : ''; ?>"
                     data-lang-id="<?php echo $lang['languages_id']; ?>"
                     onclick="mrhPaSwitchLang(<?php echo $lang['languages_id']; ?>, this)">
                    <?php echo $lang['name']; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Language Panels -->
        <?php foreach ($mrh_pa_languages as $idx => $lang): ?>
            <?php
                $lid = $lang['languages_id'];
                $attrs = $mrh_pa_all_attrs[$lid] ?? [];
            ?>
            <div class="mrh-pa-lang-panel <?php echo $idx === 0 ? 'active' : ''; ?>"
                 id="mrh-pa-lang-<?php echo $lid; ?>">

                <!-- v1.6.0: Unified field container (standard + custom mixed, all draggable) -->
                <div class="mrh-pa-fields-container" id="mrh-pa-fields-<?php echo $lid; ?>">
                    <?php foreach ($mrh_pa_fields as $field_key => $field_def): ?>
                        <?php
                            $row_class = '';
                            if ($field_def['priority'] === 'prio') $row_class = 'priority';
                            elseif ($field_def['priority'] === 'alt') $row_class = 'alt-priority';
                        ?>
                        <div class="mrh-pa-field-row <?php echo $row_class; ?>" draggable="true" data-field-key="<?php echo $field_key; ?>" data-field-type="standard">
                            <span class="field-drag-handle" title="Ziehen zum Sortieren">&#9776;</span>
                            <div class="mrh-pa-field-label">
                                <?php echo $field_def['label']; ?>
                                <?php if ($field_def['priority'] === 'prio'): ?>
                                    <span class="priority-star" title="Prio-Feld (immer in Mini-Tabelle)">&#9733;</span>
                                <?php elseif ($field_def['priority'] === 'alt'): ?>
                                    <span class="alt-priority-star" title="Alt-Prio (Fallback wenn Prio leer)">&#9734;</span>
                                <?php endif; ?>
                            </div>
                            <div class="mrh-pa-field-input">
                                <?php if ($field_def['type'] === 'select' && isset($mrh_pa_select_options[$field_key])): ?>
                                    <select name="mrh_pa[<?php echo $lid; ?>][<?php echo $field_key; ?>]"
                                            id="mrh_pa_<?php echo $lid; ?>_<?php echo $field_key; ?>"
                                            class="mrh-pa-input" data-field="<?php echo $field_key; ?>">
                                        <?php foreach ($mrh_pa_select_options[$field_key] as $opt_val => $opt_label): ?>
                                            <option value="<?php echo htmlspecialchars($opt_val); ?>"
                                                <?php echo (isset($attrs[$field_key]) && $attrs[$field_key] == $opt_val) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text"
                                           name="mrh_pa[<?php echo $lid; ?>][<?php echo $field_key; ?>]"
                                           id="mrh_pa_<?php echo $lid; ?>_<?php echo $field_key; ?>"
                                           class="mrh-pa-input" data-field="<?php echo $field_key; ?>"
                                           value="<?php echo htmlspecialchars($attrs[$field_key] ?? ''); ?>"
                                           placeholder="<?php echo htmlspecialchars($field_def['label']); ?>">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="field-remove-btn" onclick="this.closest('.mrh-pa-field-row').style.display='none'; this.closest('.mrh-pa-field-row').querySelectorAll('input,select').forEach(function(e){e.disabled=true;})" title="Feld ausblenden">&times;</button>
                        </div>
                    <?php endforeach; ?>

                    <!-- Custom Fields (v1.6.0: rendered INLINE with standard fields, not in separate container) -->
                    <?php
                    $custom = [];
                    if (!empty($attrs['custom_fields'])) {
                        $custom = json_decode($attrs['custom_fields'], true) ?: [];
                    }
                    // Dedup: Build list of standard field labels
                    $mrh_pa_std_labels = [];
                    if (class_exists('MrhProductAttributes')) {
                        foreach (MrhProductAttributes::STANDARD_FIELDS as $sf_key => $sf_meta) {
                            $mrh_pa_std_labels[] = mb_strtolower(trim($sf_meta[0]));
                            $mrh_pa_std_labels[] = mb_strtolower(trim($sf_meta[1]));
                        }
                    }
                    $mrh_pa_std_labels = array_merge($mrh_pa_std_labels, [
                        'geschlecht', 'gender', 'sorte', 'type', 'indica/sativa', 'sorte (indica/sativa)',
                        'thc', 'thc-gehalt', 'thc gehalt', 'cbd', 'cbd-gehalt', 'cbd gehalt',
                        'kreuzung', 'genetik', 'kreuzung / genetik', 'cross', 'genetics', 'cross/genetics',
                        'bluetezeit', 'blütezeit', 'blütezeit indoor', 'flowering time', 'flowering',
                        'ertrag indoor', 'yield indoor', 'ertrag outdoor', 'yield outdoor',
                        'erntezeit', 'erntezeitpunkt', 'harvest time', 'harvest',
                        'hoehe indoor', 'höhe indoor', 'height indoor', 'hoehe outdoor', 'höhe outdoor', 'height outdoor',
                        'klima', 'climate', 'wirkung', 'effect', 'geschmack', 'taste', 'geschmack & aroma',
                        'anbau', 'growing', 'bluetentyp', 'blütentyp', 'flowering type',
                        'effekt', 'eigenschaften', 'aroma',
                    ]);
                    $mrh_pa_std_labels = array_unique($mrh_pa_std_labels);

                    $custom_filtered = [];
                    $ci_new = 0;
                    foreach ($custom as $cf) {
                        $cf_label_lower = mb_strtolower(trim($cf['label'] ?? ''));
                        if (in_array($cf_label_lower, $mrh_pa_std_labels)) continue;
                        $custom_filtered[$ci_new] = $cf;
                        $ci_new++;
                    }
                    $custom = $custom_filtered;
                    foreach ($custom as $ci => $cf): ?>
                        <div class="mrh-pa-field-row mrh-pa-custom-row" draggable="true" data-field-key="custom_<?php echo $ci; ?>" data-field-type="custom">
                            <span class="field-drag-handle" title="Drag zum Sortieren">&#9776;</span>
                            <div class="mrh-pa-field-input" style="width:180px;flex:none;">
                                <input type="text"
                                       name="mrh_pa[<?php echo $lid; ?>][custom][<?php echo $ci; ?>][label]"
                                       value="<?php echo htmlspecialchars($cf['label'] ?? ''); ?>"
                                       placeholder="Feldname">
                            </div>
                            <div class="mrh-pa-field-input">
                                <input type="text"
                                       name="mrh_pa[<?php echo $lid; ?>][custom][<?php echo $ci; ?>][value]"
                                       value="<?php echo htmlspecialchars($cf['value'] ?? ''); ?>"
                                       placeholder="Wert">
                            </div>
                            <button type="button" class="mrh-pa-btn mrh-pa-btn-secondary"
                                    onclick="this.closest('.mrh-pa-custom-row').remove(); mrhPaSyncAllFieldOrder();"
                                    title="Entfernen">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="mrh-pa-btn mrh-pa-btn-add"
                        onclick="mrhPaAddCustomField(<?php echo $lid; ?>)">
                    + <?php echo defined('MRH_PA_BUTTON_ADD_FIELD') ? MRH_PA_BUTTON_ADD_FIELD : 'Feld hinzufuegen'; ?>
                </button>
            </div>
        <?php endforeach; ?>

        <!-- ============================================================ -->
        <!-- ICON EDITOR v1.3.0 with DnD, SVG, numeric size              -->
        <!-- ============================================================ -->
        <div class="mrh-pa-icon-section">
            <h4><span class="fa fa-paint-brush"></span> Picto-Icons (Badges) — Editor</h4>

            <!-- Current icons list (editable + draggable) -->
            <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">
                Aktuelle Icons (Drag &amp; Drop zum Sortieren, Farbe/Groesse klicken):
            </div>
            <div class="mrh-pa-icon-list" id="mrh-pa-icon-list">
                <span style="color:#999;font-size:12px;" id="mrh-pa-icon-empty">Keine Icons. Waehlen Sie aus der Bibliothek unten.</span>
            </div>

            <!-- v1.6.0: Save current selection as preset -->
            <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px;">
                <button type="button" class="mrh-pa-save-preset-btn" onclick="mrhPaSaveCurrentAsPreset()">
                    <span class="fa-solid fa-bookmark"></span> Aktuelles als Preset speichern
                </button>
                <span id="mrh-pa-preset-save-status" style="font-size:11px;color:#666;"></span>
            </div>

            <!-- v1.6.0: Badge Presets (Mehrfachauswahl) -->
            <div class="mrh-pa-badge-presets">
                <div class="mrh-pa-badge-presets-title">
                    <span><span class="fa-solid fa-bookmark"></span> Eigene Badge-Presets (Speichern &amp; Wiederverwenden)</span>
                </div>
                <div class="mrh-pa-badge-presets-grid" id="mrh-pa-badge-presets-grid">
                    <?php if (empty($mrh_pa_icon_presets)): ?>
                        <span style="color:#999;font-size:12px;">Noch keine Presets. Erstellen Sie welche ueber die Admin-Seite oder speichern Sie die aktuelle Auswahl.</span>
                    <?php else: ?>
                        <?php foreach ($mrh_pa_icon_presets as $preset): ?>
                            <span class="mrh-pa-badge-preset-btn" data-preset-key="<?php echo htmlspecialchars($preset['key']); ?>"
                                  data-preset-icons='<?php echo htmlspecialchars(json_encode($preset['icons'] ?? [])); ?>'
                                  onclick="mrhPaToggleBadgePreset(this)">
                                <?php
                                $first_icon = !empty($preset['icons'][0]) ? $preset['icons'][0] : null;
                                if ($first_icon) {
                                    $fp = ($first_icon['style'] ?? 'solid') === 'brands' ? 'fa-brands' : (($first_icon['style'] ?? 'solid') === 'regular' ? 'fa-regular' : 'fa-solid');
                                    echo '<span class="preset-icon ' . $fp . ' ' . htmlspecialchars($first_icon['icon']) . '" style="color:' . htmlspecialchars($first_icon['color'] ?? '#333') . '"></span>';
                                }
                                ?>
                                <?php echo htmlspecialchars($preset['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick-Pick: Vordefinierte Cannabis-Icons -->
            <div class="mrh-pa-quickpick">
                <div class="mrh-pa-quickpick-title"><span class="fa-solid fa-star"></span> Schnellauswahl — Cannabis-Badges (Klick zum Hinzufuegen/Entfernen)</div>
                <div class="mrh-pa-quickpick-grid" id="mrh-pa-quickpick-grid">
                    <?php
                    $mrh_pa_qp_badges = [
                        ['icon'=>'fa-cannabis',    'color'=>'#27ae60', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-bong',        'color'=>'#8e44ad', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-joint',       'color'=>'#e67e22', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-pipe-smoking','color'=>'#795548', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-seedling',    'color'=>'#2ecc71', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-leaf',        'color'=>'#00b894', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-venus',       'color'=>'#e84393', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-mars',        'color'=>'#0984e3', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-kit-medical', 'color'=>'#ff6666', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-fire',        'color'=>'#d63031', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-bolt',        'color'=>'#e17055', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-star',        'color'=>'#f39c12', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-gem',         'color'=>'#00cec9', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-shield',      'color'=>'#636e72', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-sun',         'color'=>'#fdcb6e', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-house',       'color'=>'#6c5ce7', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-pagelines',   'color'=>'#27ae60', 'title'=>'', 'style'=>'brands'],
                        ['icon'=>'fa-snowflake',   'color'=>'#74b9ff', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-temperature-full','color'=>'#e74c3c','title'=>'','style'=>'solid'],
                        ['icon'=>'fa-trophy',      'color'=>'#f39c12', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-flask',       'color'=>'#6c5ce7', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-vial',        'color'=>'#00b894', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-mortar-pestle','color'=>'#636e72', 'title'=>'', 'style'=>'solid'],
                        ['icon'=>'fa-hand-holding-seedling','color'=>'#2ecc71','title'=>'','style'=>'solid'],
                    ];
                    // SVG badge for Regulaer
                    echo '<span class="mrh-pa-quickpick-btn" data-icon="svg:' . $mrh_pa_badge_base . 'male.svg" data-color="" data-title="" data-type="svg" data-style="" onclick="mrhPaQuickPick(this)">';
                    echo '<img class="qp-svg" src="/' . $mrh_pa_badge_base . 'male.svg" alt="M">';
                    echo '<span class="qp-edit" onclick="event.stopPropagation()">';
                    echo '<input type="text" class="qp-title-input" placeholder="Text (optional)" value="" onchange="mrhPaQpEditTitle(this)">';
                    echo '<input type="number" class="qp-size-input" value="16" min="10" max="48" onchange="mrhPaQpEditSize(this)"> px';
                    echo '</span></span>';

                    foreach ($mrh_pa_qp_badges as $qp) {
                        $faPrefix = $qp['style'] === 'brands' ? 'fa-brands' : ($qp['style'] === 'regular' ? 'fa-regular' : 'fa-solid');
                        echo '<span class="mrh-pa-quickpick-btn" data-icon="' . $qp['icon'] . '" data-color="' . $qp['color'] . '" data-title="' . $qp['title'] . '" data-type="fa" data-style="' . $qp['style'] . '" onclick="mrhPaQuickPick(this)">';
                        echo '<span class="' . $faPrefix . ' ' . $qp['icon'] . '" style="color:' . $qp['color'] . '"></span>';
                        echo '<span class="qp-edit" onclick="event.stopPropagation()">';
                        echo '<input type="text" class="qp-title-input" placeholder="Text (optional)" value="" onchange="mrhPaQpEditTitle(this)">';
                        echo '<input type="color" class="qp-color" value="' . $qp['color'] . '" onchange="mrhPaQpEditColor(this)">';
                        echo '<input type="number" class="qp-size-input" value="16" min="10" max="48" onchange="mrhPaQpEditSize(this)"> px';
                        echo '</span></span>';
                    }
                    ?>
                </div>
            </div>

            <!-- Icon Library with Search + Style Switcher (v1.6.0: ACCORDION - closed by default) -->
            <div class="mrh-pa-icon-library">
                <div class="mrh-pa-icon-library-header" onclick="mrhPaToggleLibrary()">
                    <span class="lib-toggle fa-solid fa-chevron-right" id="mrh-pa-lib-toggle"></span>
                    <span class="lib-title"><span class="fa-solid fa-magnifying-glass"></span> Icon-Bibliothek (FA 7)</span>
                    <div class="mrh-pa-style-switcher" onclick="event.stopPropagation()">
                        <button type="button" class="mrh-pa-style-btn active" data-style="solid" onclick="mrhPaSwitchStyle('solid')">Solid</button>
                        <button type="button" class="mrh-pa-style-btn" data-style="regular" onclick="mrhPaSwitchStyle('regular')">Regular</button>
                        <button type="button" class="mrh-pa-style-btn" data-style="brands" onclick="mrhPaSwitchStyle('brands')">Brands</button>
                        <button type="button" class="mrh-pa-style-btn" data-style="all" onclick="mrhPaSwitchStyle('all')">Alle</button>
                    </div>
                    <input type="text" id="mrh-pa-icon-search" placeholder="Icon suchen... (z.B. cannabis, leaf, bong, star)" oninput="mrhPaFilterLibrary(this.value)" onclick="event.stopPropagation()">
                    <span class="lib-count" id="mrh-pa-icon-lib-count">5300+ Icons</span>
                </div>
                <div class="mrh-pa-icon-library-body" id="mrh-pa-icon-library-body">
                    <div class="mrh-pa-icon-add-controls" id="mrh-pa-icon-add-controls">
                        <label>Titel:</label>
                        <input type="text" id="mrh-pa-add-title" placeholder="Anzeigename">
                        <span class="add-color-group"><span class="add-color-label">Icon:</span>
                        <input type="color" id="mrh-pa-add-color" value="#333333"></span>
                        <span class="add-color-group"><span class="add-color-label">BG:</span>
                        <input type="color" id="mrh-pa-add-bgcolor" value="#f1f5f9" title="Hintergrundfarbe"></span>
                        <span class="add-color-group"><span class="add-color-label">Rand:</span>
                        <input type="color" id="mrh-pa-add-bordercolor" value="#dddddd" title="Randfarbe"></span>
                        <label>Groesse:</label>
                        <input type="number" id="mrh-pa-add-size" value="16" min="10" max="48" style="width:55px"> px
                        <span style="font-size:11px;color:#666;">Klicke ein Icon unten zum Hinzufuegen</span>
                    </div>
                    <div class="mrh-pa-icon-library-grid" id="mrh-pa-icon-library-grid">
                        <!-- Icons werden per JS geladen -->
                    </div>
                </div>
            </div>

            <!-- Hidden input to store pictos JSON -->
            <input type="hidden" name="mrh_pa[pictos]" id="mrh-pa-pictos-json" value="<?php echo htmlspecialchars(json_encode($mrh_pa_pictos)); ?>">
        </div>

        <!-- ============================================================ -->
        <!-- CANNABIS CUP TROPHIES (max 3 + number)                       -->
        <!-- ============================================================ -->
        <div class="mrh-pa-cups-section">
            <h4><span class="fa fa-trophy" style="color:#f39c12"></span> Cannabis Cup Auszeichnungen</h4>
            <div class="mrh-pa-cups-row">
                <label style="font-size:13px;font-weight:600;">Anzahl Pokale:</label>
                <input type="number" name="mrh_pa[cannabis_cups]" id="mrh-pa-cups-input"
                       min="0" max="99" value="<?php echo $mrh_pa_cups; ?>"
                       onchange="mrhPaUpdateCupsPreview()" oninput="mrhPaUpdateCupsPreview()">
                <div class="mrh-pa-cups-preview" id="mrh-pa-cups-preview"></div>
            </div>
            <div style="font-size:11px;color:#999;margin-top:6px;">1-3 = Pokale einzeln, ab 4 = 3 Pokale + Zahl</div>
        </div>

        <!-- Action Buttons -->
        <div class="mrh-pa-actions">
            <button type="button" class="mrh-pa-btn-save" id="mrh-pa-save-btn" onclick="mrhPaSaveAll()">
                <span class="fa-solid fa-floppy-disk"></span> MRH Eigenschaften speichern
            </button>
            <span class="mrh-pa-save-status" id="mrh-pa-save-status"></span>
            <button type="button" class="mrh-pa-btn mrh-pa-btn-ai" onclick="mrhPaAiFill(<?php echo $mrh_pa_products_id; ?>)" style="margin-left:auto;">
                <span class="fa-solid fa-wand-magic-sparkles"></span> <?php echo defined('MRH_PA_BUTTON_AI_FILL') ? MRH_PA_BUTTON_AI_FILL : 'Mit KI befuellen'; ?>
            </button>
            <div id="mrh-pa-ai-status" style="display:none;font-size:12px;color:#666;align-self:center;"></div>
        </div>

        <!-- Hidden field for product ID -->
        <input type="hidden" name="mrh_pa[products_id]" value="<?php echo $mrh_pa_products_id; ?>">

        <?php endif; ?>
    </div>
</div>

<script>
// ============================================================
// FONTAWESOME 7 COMPLETE ICON LIST (5300+ icons with style info)
// ============================================================
var mrhPaAllIcons = <?php
$mrh_pa_fa7_json = DIR_FS_CATALOG . 'includes/external/mrh_product_attributes/fa7_icons.json';
if (file_exists($mrh_pa_fa7_json)) {
    echo file_get_contents($mrh_pa_fa7_json);
} else {
    echo json_encode([
        ['n'=>'fa-leaf','s'=>['solid']],['n'=>'fa-star','s'=>['solid','regular']],['n'=>'fa-heart','s'=>['solid','regular']],
        ['n'=>'fa-fire','s'=>['solid']],['n'=>'fa-bolt','s'=>['solid']],['n'=>'fa-trophy','s'=>['solid']],
        ['n'=>'fa-kit-medical','s'=>['solid']],['n'=>'fa-shield','s'=>['solid']],['n'=>'fa-gem','s'=>['solid','regular']],
        ['n'=>'fa-eye','s'=>['solid','regular']],['n'=>'fa-venus','s'=>['solid']],['n'=>'fa-mars','s'=>['solid']],
        ['n'=>'fa-house','s'=>['solid']],['n'=>'fa-sun','s'=>['solid','regular']],['n'=>'fa-cannabis','s'=>['solid']],
        ['n'=>'fa-bong','s'=>['solid']],['n'=>'fa-joint','s'=>['solid']],['n'=>'fa-seedling','s'=>['solid']],
        ['n'=>'fa-snowflake','s'=>['solid','regular']],['n'=>'fa-temperature-full','s'=>['solid']]
    ]);
}
?>;
var mrhPaCurrentStyle = 'solid';
var mrhPaBadgeBase = '<?php echo $mrh_pa_badge_base; ?>';
var mrhPaLibraryOpen = false; // v1.6.0: closed by default

// ============================================================
// v1.6.0: ICON LIBRARY ACCORDION
// ============================================================
function mrhPaToggleLibrary() {
    mrhPaLibraryOpen = !mrhPaLibraryOpen;
    var body = document.getElementById('mrh-pa-icon-library-body');
    var toggle = document.getElementById('mrh-pa-lib-toggle');
    if (mrhPaLibraryOpen) {
        body.classList.add('open');
        toggle.classList.add('open');
        if (!body.dataset.built) {
            mrhPaBuildLibrary();
            body.dataset.built = '1';
        }
    } else {
        body.classList.remove('open');
        toggle.classList.remove('open');
    }
}

// ============================================================
// PICTOS / ICON EDITOR v1.3.0
// ============================================================
var mrhPaCurrentPictos = <?php echo json_encode($mrh_pa_pictos); ?> || [];

function mrhPaIsSvg(icon) { return icon && icon.indexOf('svg:') === 0; }
function mrhPaSvgUrl(icon) { return '/' + icon.replace('svg:', ''); }

function mrhPaRenderIcons() {
    var list = document.getElementById('mrh-pa-icon-list');
    if (!list) return;
    list.innerHTML = '';

    if (mrhPaCurrentPictos.length === 0) {
        list.innerHTML = '<span style="color:#999;font-size:12px;" id="mrh-pa-icon-empty">Keine Icons. Waehlen Sie aus der Bibliothek unten.</span>';
    }

    for (var i = 0; i < mrhPaCurrentPictos.length; i++) {
        var p = mrhPaCurrentPictos[i];
        var iconVal = (p.icon || '').replace(/^fa\s+/, '');
        var sizeNum = parseInt(p.size) || 16;
        var item = document.createElement('div');
        item.className = 'mrh-pa-icon-item';
        item.setAttribute('data-idx', i);
        item.setAttribute('draggable', 'true');

        var previewHtml;
        if (mrhPaIsSvg(iconVal)) {
            previewHtml = '<img class="icon-preview-svg" src="' + mrhPaEsc(mrhPaSvgUrl(iconVal)) + '" style="width:' + sizeNum + 'px;height:' + sizeNum + 'px">';
        } else {
            var faStyle = (p.style || 'solid');
            var faPrefix = faStyle === 'brands' ? 'fa-brands' : (faStyle === 'regular' ? 'fa-regular' : 'fa-solid');
            previewHtml = '<span class="icon-preview ' + faPrefix + ' ' + mrhPaEsc(iconVal) + '" style="color:' + mrhPaEsc(p.color || '#333') + ';font-size:' + sizeNum + 'px"></span>';
        }

        var colorHtml = mrhPaIsSvg(iconVal) ? '' : '<input type="color" class="icon-edit-color" value="' + mrhPaEsc(p.color || '#333333') + '" onchange="mrhPaEditIconColor(' + i + ', this.value)" title="Icon-Farbe aendern">';
        var bgColor = p.bgcolor || '#f1f5f9';
        var bdColor = p.bordercolor || '#dddddd';
        var bgColorHtml = '<span class="icon-color-label">BG:</span><input type="color" class="icon-edit-bgcolor" value="' + mrhPaEsc(bgColor) + '" onchange="mrhPaEditIconBgColor(' + i + ', this.value)" title="Hintergrundfarbe">';
        var bdColorHtml = '<span class="icon-color-label">Rand:</span><input type="color" class="icon-edit-bordercolor" value="' + mrhPaEsc(bdColor) + '" onchange="mrhPaEditIconBorderColor(' + i + ', this.value)" title="Randfarbe">';
        var badgePreviewStyle = 'background:' + mrhPaEsc(bgColor) + ';border-color:' + mrhPaEsc(bdColor) + ';';
        var badgePreviewHtml = '<span class="icon-badge-preview" style="' + badgePreviewStyle + '">' + previewHtml + '</span>';

        item.innerHTML =
            '<span class="drag-handle" title="Ziehen zum Sortieren">&#9776;</span>' +
            badgePreviewHtml +
            '<span class="icon-title" title="' + mrhPaEsc(p.title || iconVal) + '">' + mrhPaEsc(p.title || iconVal) + '</span>' +
            colorHtml + bgColorHtml + bdColorHtml +
            '<input type="number" class="icon-edit-size" value="' + sizeNum + '" min="10" max="48" onchange="mrhPaEditIconSize(' + i + ', this.value)" title="Groesse (px)"> px' +
            '<span class="icon-remove" onclick="mrhPaRemoveIcon(' + i + ')" title="Entfernen">&times;</span>';
        list.appendChild(item);
    }

    var jsonField = document.getElementById('mrh-pa-pictos-json');
    if (jsonField) jsonField.value = JSON.stringify(mrhPaCurrentPictos);
    mrhPaUpdateLibrarySelection();
    mrhPaUpdateQuickPick();
    mrhPaInitDragDrop();
}

function mrhPaEditIconColor(idx, color) {
    if (mrhPaCurrentPictos[idx]) {
        mrhPaCurrentPictos[idx].color = color;
        var item = document.querySelector('.mrh-pa-icon-item[data-idx="' + idx + '"]');
        if (item) { var preview = item.querySelector('.icon-preview'); if (preview) preview.style.color = color; }
        document.getElementById('mrh-pa-pictos-json').value = JSON.stringify(mrhPaCurrentPictos);
    }
}
function mrhPaEditIconSize(idx, size) {
    if (mrhPaCurrentPictos[idx]) {
        mrhPaCurrentPictos[idx].size = size + 'px';
        var item = document.querySelector('.mrh-pa-icon-item[data-idx="' + idx + '"]');
        if (item) {
            var preview = item.querySelector('.icon-preview');
            var svgPreview = item.querySelector('.icon-preview-svg');
            if (preview) preview.style.fontSize = size + 'px';
            if (svgPreview) { svgPreview.style.width = size + 'px'; svgPreview.style.height = size + 'px'; }
        }
        document.getElementById('mrh-pa-pictos-json').value = JSON.stringify(mrhPaCurrentPictos);
    }
}
function mrhPaEditIconBgColor(idx, color) {
    if (mrhPaCurrentPictos[idx]) {
        mrhPaCurrentPictos[idx].bgcolor = color;
        var item = document.querySelector('.mrh-pa-icon-item[data-idx="' + idx + '"]');
        if (item) { var badge = item.querySelector('.icon-badge-preview'); if (badge) badge.style.background = color; }
        document.getElementById('mrh-pa-pictos-json').value = JSON.stringify(mrhPaCurrentPictos);
    }
}
function mrhPaEditIconBorderColor(idx, color) {
    if (mrhPaCurrentPictos[idx]) {
        mrhPaCurrentPictos[idx].bordercolor = color;
        var item = document.querySelector('.mrh-pa-icon-item[data-idx="' + idx + '"]');
        if (item) { var badge = item.querySelector('.icon-badge-preview'); if (badge) badge.style.borderColor = color; }
        document.getElementById('mrh-pa-pictos-json').value = JSON.stringify(mrhPaCurrentPictos);
    }
}
function mrhPaRemoveIcon(idx) { mrhPaCurrentPictos.splice(idx, 1); mrhPaRenderIcons(); }

// ============================================================
// DRAG & DROP for icon reordering
// ============================================================
var mrhPaDragIdx = null;
function mrhPaInitDragDrop() {
    var items = document.querySelectorAll('.mrh-pa-icon-item[draggable]');
    items.forEach(function(item) {
        item.addEventListener('dragstart', function(e) {
            mrhPaDragIdx = parseInt(this.getAttribute('data-idx'));
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            document.querySelectorAll('.mrh-pa-icon-item').forEach(function(el) { el.classList.remove('drag-over'); });
        });
        item.addEventListener('dragover', function(e) {
            e.preventDefault(); e.dataTransfer.dropEffect = 'move'; this.classList.add('drag-over');
        });
        item.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
        item.addEventListener('drop', function(e) {
            e.preventDefault(); this.classList.remove('drag-over');
            var dropIdx = parseInt(this.getAttribute('data-idx'));
            if (mrhPaDragIdx !== null && mrhPaDragIdx !== dropIdx) {
                var moved = mrhPaCurrentPictos.splice(mrhPaDragIdx, 1)[0];
                mrhPaCurrentPictos.splice(dropIdx, 0, moved);
                mrhPaRenderIcons();
            }
            mrhPaDragIdx = null;
        });
    });
}

function mrhPaAddIconFromLibrary(iconClass, style) {
    style = style || mrhPaCurrentStyle || 'solid';
    var existIdx = -1;
    for (var i = 0; i < mrhPaCurrentPictos.length; i++) {
        var existing = (mrhPaCurrentPictos[i].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
        if (existing === iconClass || existing === iconClass.replace('fa-', '')) { existIdx = i; break; }
    }
    if (existIdx >= 0) {
        mrhPaCurrentPictos.splice(existIdx, 1);
    } else {
        var title = document.getElementById('mrh-pa-add-title').value.trim() || iconClass.replace('fa-', '').replace(/-/g, ' ');
        var color = document.getElementById('mrh-pa-add-color').value || '#333333';
        var sizeVal = parseInt(document.getElementById('mrh-pa-add-size').value) || 16;
        var bgColorEl = document.getElementById('mrh-pa-add-bgcolor');
        var bdColorEl = document.getElementById('mrh-pa-add-bordercolor');
        var bgcolor = bgColorEl ? bgColorEl.value : '#f1f5f9';
        var bordercolor = bdColorEl ? bdColorEl.value : '#dddddd';
        mrhPaCurrentPictos.push({ icon: iconClass, color: color, size: sizeVal + 'px', title: title, style: style, bgcolor: bgcolor, bordercolor: bordercolor });
        document.getElementById('mrh-pa-add-title').value = '';
    }
    mrhPaRenderIcons();
}

// ============================================================
// ICON LIBRARY - Build & Search
// ============================================================
function mrhPaBuildLibrary() {
    var grid = document.getElementById('mrh-pa-icon-library-grid');
    if (!grid) return;
    var html = '';
    var visibleCount = 0;
    for (var i = 0; i < mrhPaAllIcons.length; i++) {
        var iconObj = mrhPaAllIcons[i];
        var iconName = iconObj.n;
        var styles = iconObj.s || ['solid'];
        var name = iconName.replace('fa-', '');
        var hasStyle = styles.indexOf(mrhPaCurrentStyle) !== -1;
        if (!hasStyle && mrhPaCurrentStyle !== 'all') continue;
        var faPrefix = mrhPaCurrentStyle === 'brands' ? 'fa-brands' : (mrhPaCurrentStyle === 'regular' ? 'fa-regular' : 'fa-solid');
        if (mrhPaCurrentStyle === 'all') faPrefix = styles.indexOf('solid') !== -1 ? 'fa-solid' : (styles.indexOf('regular') !== -1 ? 'fa-regular' : 'fa-brands');
        var styleTag = styles.join(',');
        html += '<div class="mrh-pa-icon-lib-btn" data-icon="' + iconName + '" data-name="' + name + '" data-styles="' + styleTag + '" onclick="mrhPaAddIconFromLibrary(\'' + iconName + '\', \'' + (mrhPaCurrentStyle === 'all' ? 'solid' : mrhPaCurrentStyle) + '\')">' +
            '<span class="' + faPrefix + ' ' + iconName + '"></span>' +
            '<span class="icon-name">' + name + '</span></div>';
        visibleCount++;
    }
    grid.innerHTML = html;
    var countEl = document.getElementById('mrh-pa-icon-lib-count');
    if (countEl) countEl.textContent = visibleCount + ' Icons';
    mrhPaUpdateLibrarySelection();
}

function mrhPaSwitchStyle(style) {
    mrhPaCurrentStyle = style;
    var btns = document.querySelectorAll('.mrh-pa-style-btn');
    for (var i = 0; i < btns.length; i++) { btns[i].classList.toggle('active', btns[i].getAttribute('data-style') === style); }
    mrhPaBuildLibrary();
    var searchEl = document.getElementById('mrh-pa-icon-search');
    if (searchEl && searchEl.value.trim()) mrhPaFilterLibrary(searchEl.value);
}

function mrhPaFilterLibrary(query) {
    // Auto-open library when searching
    if (query && !mrhPaLibraryOpen) mrhPaToggleLibrary();
    var grid = document.getElementById('mrh-pa-icon-library-grid');
    if (!grid) return;
    var buttons = grid.querySelectorAll('.mrh-pa-icon-lib-btn');
    var q = query.toLowerCase().trim();
    var visible = 0;
    for (var i = 0; i < buttons.length; i++) {
        var name = buttons[i].getAttribute('data-name');
        var show = !q || name.indexOf(q) !== -1;
        buttons[i].style.display = show ? '' : 'none';
        if (show) visible++;
    }
    var countEl = document.getElementById('mrh-pa-icon-lib-count');
    if (countEl) countEl.textContent = visible + ' Icons' + (q ? ' gefunden' : '');
}

function mrhPaUpdateLibrarySelection() {
    var grid = document.getElementById('mrh-pa-icon-library-grid');
    if (!grid) return;
    var buttons = grid.querySelectorAll('.mrh-pa-icon-lib-btn');
    for (var i = 0; i < buttons.length; i++) {
        var icon = buttons[i].getAttribute('data-icon');
        var isSelected = mrhPaCurrentPictos.some(function(p) {
            var existing = (p.icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
            return existing === icon || existing === icon.replace('fa-', '');
        });
        buttons[i].classList.toggle('selected', isSelected);
    }
}

// ============================================================
// QUICK-PICK (predefined cannabis icons)
// ============================================================
function mrhPaQuickPick(el) {
    var iconVal = el.getAttribute('data-icon');
    var style = el.getAttribute('data-style') || 'solid';
    var titleInput = el.querySelector('.qp-title-input');
    var colorInput = el.querySelector('.qp-color');
    var sizeInput = el.querySelector('.qp-size-input');
    var title = titleInput ? titleInput.value.trim() : (el.getAttribute('data-title') || '');
    var color = colorInput ? colorInput.value : (el.getAttribute('data-color') || '#333333');
    var sizeNum = sizeInput ? parseInt(sizeInput.value) || 16 : 16;

    var existIdx = -1;
    for (var i = 0; i < mrhPaCurrentPictos.length; i++) {
        var existing = (mrhPaCurrentPictos[i].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
        if (existing === iconVal) { existIdx = i; break; }
    }
    if (existIdx >= 0) {
        mrhPaCurrentPictos.splice(existIdx, 1);
    } else {
        var bgColorEl = document.getElementById('mrh-pa-add-bgcolor');
        var bdColorEl = document.getElementById('mrh-pa-add-bordercolor');
        var bgcolor = bgColorEl ? bgColorEl.value : '#f1f5f9';
        var bordercolor = bdColorEl ? bdColorEl.value : '#dddddd';
        mrhPaCurrentPictos.push({ icon: iconVal, color: color, size: sizeNum + 'px', title: title, style: style, bgcolor: bgcolor, bordercolor: bordercolor });
    }
    mrhPaRenderIcons();
}

function mrhPaQpEditTitle(input) {
    var btn = input.closest('.mrh-pa-quickpick-btn'); if (!btn) return;
    var iconVal = btn.getAttribute('data-icon');
    btn.setAttribute('data-title', input.value.trim());
    for (var i = 0; i < mrhPaCurrentPictos.length; i++) {
        var existing = (mrhPaCurrentPictos[i].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
        if (existing === iconVal) { mrhPaCurrentPictos[i].title = input.value.trim(); mrhPaRenderIcons(); return; }
    }
}
function mrhPaQpEditColor(input) {
    var btn = input.closest('.mrh-pa-quickpick-btn'); if (!btn) return;
    var iconVal = btn.getAttribute('data-icon');
    var iconEl = btn.querySelector('[class*="fa-"]'); if (iconEl) iconEl.style.color = input.value;
    btn.setAttribute('data-color', input.value);
    for (var i = 0; i < mrhPaCurrentPictos.length; i++) {
        var existing = (mrhPaCurrentPictos[i].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
        if (existing === iconVal) { mrhPaCurrentPictos[i].color = input.value; mrhPaRenderIcons(); return; }
    }
}
function mrhPaQpEditSize(input) {
    var btn = input.closest('.mrh-pa-quickpick-btn'); if (!btn) return;
    var iconVal = btn.getAttribute('data-icon');
    var newSize = (parseInt(input.value) || 16) + 'px';
    for (var i = 0; i < mrhPaCurrentPictos.length; i++) {
        var existing = (mrhPaCurrentPictos[i].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
        if (existing === iconVal) { mrhPaCurrentPictos[i].size = newSize; mrhPaRenderIcons(); return; }
    }
}
function mrhPaUpdateQuickPick() {
    var btns = document.querySelectorAll('.mrh-pa-quickpick-btn');
    for (var i = 0; i < btns.length; i++) {
        var icon = btns[i].getAttribute('data-icon');
        var matchedPicto = null;
        for (var j = 0; j < mrhPaCurrentPictos.length; j++) {
            var existing = (mrhPaCurrentPictos[j].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
            if (existing === icon) { matchedPicto = mrhPaCurrentPictos[j]; break; }
        }
        btns[i].classList.toggle('active', !!matchedPicto);
        if (matchedPicto) {
            var titleInput = btns[i].querySelector('.qp-title-input');
            var colorInput = btns[i].querySelector('.qp-color');
            var sizeInput = btns[i].querySelector('.qp-size-input');
            if (titleInput) titleInput.value = matchedPicto.title || '';
            if (colorInput) colorInput.value = matchedPicto.color || '#333333';
            if (sizeInput) sizeInput.value = parseInt(matchedPicto.size) || 16;
            var iconEl = btns[i].querySelector('[class*="fa-"]');
            if (iconEl) iconEl.style.color = matchedPicto.color || '#333333';
        }
    }
}

// ============================================================
// v1.6.0: BADGE PRESETS - Multiple selection (additive)
// ============================================================
function mrhPaToggleBadgePreset(btn) {
    var icons = JSON.parse(btn.getAttribute('data-preset-icons') || '[]');
    var isActive = btn.classList.contains('active');

    if (isActive) {
        // Remove all icons from this preset
        icons.forEach(function(presetIcon) {
            var iconName = presetIcon.icon;
            for (var i = mrhPaCurrentPictos.length - 1; i >= 0; i--) {
                var existing = (mrhPaCurrentPictos[i].icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
                if (existing === iconName || existing === iconName.replace('fa-', '')) {
                    mrhPaCurrentPictos.splice(i, 1);
                    break;
                }
            }
        });
        btn.classList.remove('active');
    } else {
        // Add all icons from this preset (skip duplicates)
        icons.forEach(function(presetIcon) {
            var iconName = presetIcon.icon;
            var exists = mrhPaCurrentPictos.some(function(p) {
                var existing = (p.icon || '').replace(/^fa[- ](?:solid|regular|brands|fw)\s*/g, '').replace(/^fa\s+/, '');
                return existing === iconName || existing === iconName.replace('fa-', '');
            });
            if (!exists) {
                mrhPaCurrentPictos.push({
                    icon: presetIcon.icon,
                    color: presetIcon.color || '#333333',
                    size: (presetIcon.size || '16') + (String(presetIcon.size || '16').indexOf('px') === -1 ? 'px' : ''),
                    title: presetIcon.title || '',
                    style: presetIcon.style || 'solid',
                    bgcolor: presetIcon.bgcolor || '#f1f5f9',
                    bordercolor: presetIcon.bordercolor || '#dddddd'
                });
            }
        });
        btn.classList.add('active');
    }
    mrhPaRenderIcons();
}

// v1.6.0: Save current icon selection as a new preset
function mrhPaSaveCurrentAsPreset() {
    if (mrhPaCurrentPictos.length === 0) {
        alert('Keine Icons vorhanden. Fuegen Sie zuerst Icons hinzu.');
        return;
    }
    var name = prompt('Preset-Name eingeben:', '');
    if (!name || !name.trim()) return;

    var payload = { name: name.trim(), icons: mrhPaCurrentPictos };
    var statusEl = document.getElementById('mrh-pa-preset-save-status');

    fetch('mrh_product_attributes.php?action=save_icon_preset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            statusEl.innerHTML = '<span style="color:green"><span class="fa fa-check"></span> Preset "' + mrhPaEsc(name) + '" gespeichert!</span>';
            setTimeout(function() { statusEl.innerHTML = ''; }, 4000);
            // Reload preset buttons
            mrhPaReloadPresetButtons(data.presets);
        } else {
            statusEl.innerHTML = '<span style="color:red">' + (data.message || 'Fehler') + '</span>';
        }
    })
    .catch(function(e) { statusEl.innerHTML = '<span style="color:red">Fehler: ' + e.message + '</span>'; });
}

function mrhPaReloadPresetButtons(presets) {
    var grid = document.getElementById('mrh-pa-badge-presets-grid');
    if (!grid || !presets) return;
    grid.innerHTML = '';
    presets.forEach(function(preset) {
        var btn = document.createElement('span');
        btn.className = 'mrh-pa-badge-preset-btn';
        btn.setAttribute('data-preset-key', preset.key);
        btn.setAttribute('data-preset-icons', JSON.stringify(preset.icons || []));
        btn.setAttribute('onclick', 'mrhPaToggleBadgePreset(this)');
        var firstIcon = (preset.icons && preset.icons[0]) ? preset.icons[0] : null;
        var iconHtml = '';
        if (firstIcon) {
            var fp = (firstIcon.style === 'brands') ? 'fa-brands' : (firstIcon.style === 'regular' ? 'fa-regular' : 'fa-solid');
            iconHtml = '<span class="preset-icon ' + fp + ' ' + mrhPaEsc(firstIcon.icon) + '" style="color:' + mrhPaEsc(firstIcon.color || '#333') + '"></span> ';
        }
        btn.innerHTML = iconHtml + mrhPaEsc(preset.name);
        grid.appendChild(btn);
    });
}

// ============================================================
// CANNABIS CUP PREVIEW (max 3 trophies + number)
// ============================================================
function mrhPaUpdateCupsPreview() {
    var count = parseInt(document.getElementById('mrh-pa-cups-input').value) || 0;
    var preview = document.getElementById('mrh-pa-cups-preview');
    if (!preview) return;
    preview.innerHTML = '';
    if (count <= 0) return;
    var trophyCount = Math.min(count, 3);
    for (var i = 0; i < trophyCount; i++) {
        var span = document.createElement('span');
        span.className = 'fa-solid fa-trophy';
        preview.appendChild(span);
    }
    if (count > 3) {
        var numSpan = document.createElement('span');
        numSpan.className = 'cup-number';
        numSpan.textContent = count;
        preview.appendChild(numSpan);
    }
}

// ============================================================
// PRESET APPLICATION + AUTO-DETECT
// ============================================================
function mrhPaApplyPreset(preset) {
    document.querySelectorAll('.mrh-pa-preset-tab').forEach(function(t) { t.classList.remove('active'); });
    var tab = document.querySelector('.mrh-pa-preset-tab[data-preset="'+preset+'"]');
    if (tab) tab.classList.add('active');

    var panels = document.querySelectorAll('.mrh-pa-lang-panel');
    panels.forEach(function(panel) {
        var genderSel = panel.querySelector('select[data-field="gender"]');
        var flowerSel = panel.querySelector('select[data-field="flowering_type"]');
        if (preset === 'feminized') { if (genderSel) genderSel.value = 'feminized'; if (flowerSel) flowerSel.value = 'photoperiod'; }
        else if (preset === 'autoflower') { if (genderSel) genderSel.value = 'feminized'; if (flowerSel) flowerSel.value = 'autoflower'; }
        else if (preset === 'regular') { if (genderSel) genderSel.value = 'regular'; if (flowerSel) flowerSel.value = 'photoperiod'; }
        else if (preset === 'auto_regular') { if (genderSel) genderSel.value = 'regular'; if (flowerSel) flowerSel.value = 'autoflower'; }
    });
}

// ============================================================
// LANGUAGE TAB SWITCHING
// ============================================================
function mrhPaSwitchLang(langId, el) {
    document.querySelectorAll('.mrh-pa-lang-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.mrh-pa-lang-panel').forEach(function(p) { p.classList.remove('active'); });
    el.classList.add('active');
    var panel = document.getElementById('mrh-pa-lang-' + langId);
    if (panel) panel.classList.add('active');
}

// ============================================================
// CUSTOM FIELDS (v1.6.0: added to unified container, not separate)
// ============================================================
var mrhPaCustomCounter = <?php echo max(count($custom ?? []), 0) + 10; ?>;
function mrhPaAddCustomField(langId) {
    var container = document.getElementById('mrh-pa-fields-' + langId);
    if (!container) return;
    var idx = mrhPaCustomCounter++;
    var row = document.createElement('div');
    row.className = 'mrh-pa-field-row mrh-pa-custom-row';
    row.setAttribute('draggable', 'true');
    row.setAttribute('data-field-key', 'custom_' + idx);
    row.setAttribute('data-field-type', 'custom');
    row.innerHTML = '<span class="field-drag-handle" title="Drag zum Sortieren">&#9776;</span>' +
        '<div class="mrh-pa-field-input" style="width:180px;flex:none;">' +
        '<input type="text" name="mrh_pa['+langId+'][custom]['+idx+'][label]" placeholder="Feldname">' +
        '</div><div class="mrh-pa-field-input">' +
        '<input type="text" name="mrh_pa['+langId+'][custom]['+idx+'][value]" placeholder="Wert">' +
        '</div><button type="button" class="mrh-pa-btn mrh-pa-btn-secondary" onclick="this.closest(\'.mrh-pa-custom-row\').remove(); mrhPaSyncAllFieldOrder();" title="Entfernen">&times;</button>';
    container.appendChild(row);
    mrhPaInitUnifiedFieldDnD(row);
}

// ============================================================
// AI FILL (SINGLE PRODUCT)
// ============================================================
function mrhPaAiFill(productsId) {
    var statusEl = document.getElementById('mrh-pa-ai-status');
    if (!statusEl) return;
    statusEl.style.display = 'inline';
    statusEl.innerHTML = '<span class="fa fa-spinner fa-spin"></span> KI analysiert Beschreibung...';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'mrh_product_attributes.php?action=ai_fill&products_id=' + productsId, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        statusEl.innerHTML = '<span style="color:green"><span class="fa fa-check"></span> ' + (data.message || 'Erfolgreich!') + '</span>';
                        if (data.attributes) mrhPaFillFields(data.attributes);
                    } else {
                        statusEl.innerHTML = '<span style="color:red"><span class="fa fa-times"></span> ' + (data.message || 'Fehler') + '</span>';
                    }
                } catch(e) { statusEl.innerHTML = '<span style="color:red">Antwort-Fehler: ' + e.message + '</span>'; }
            } else { statusEl.innerHTML = '<span style="color:red">Verbindungsfehler (HTTP ' + xhr.status + ')</span>'; }
        }
    };
    xhr.send('csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]')?.value || ''));
}

function mrhPaFillFields(attrs) {
    for (var langId in attrs) {
        var langAttrs = attrs[langId];
        if (langAttrs.is_seed !== undefined) {
            var seedEl = document.getElementById('mrh_pa_is_seed');
            if (seedEl) { seedEl.value = langAttrs.is_seed ? '1' : '0'; mrhPaHighlight(seedEl); }
        }
        for (var field in langAttrs) {
            if (field === 'custom_fields' || field === 'is_seed' || field === 'ai_confidence' || field === 'pictos' || field === 'cannabis_cups') continue;
            var el = document.getElementById('mrh_pa_' + langId + '_' + field);
            if (el) { el.value = langAttrs[field]; mrhPaHighlight(el); }
        }
        // v1.6.0: Custom fields from AI are added to unified container
        if (langAttrs.custom_fields && Array.isArray(langAttrs.custom_fields)) {
            var container = document.getElementById('mrh-pa-fields-' + langId);
            if (container) {
                // Remove existing custom rows first
                container.querySelectorAll('.mrh-pa-custom-row').forEach(function(r) { r.remove(); });
                for (var i = 0; i < langAttrs.custom_fields.length; i++) {
                    var cf = langAttrs.custom_fields[i];
                    mrhPaCustomCounter++;
                    var row = document.createElement('div');
                    row.className = 'mrh-pa-field-row mrh-pa-custom-row';
                    row.setAttribute('draggable', 'true');
                    row.setAttribute('data-field-key', 'custom_' + mrhPaCustomCounter);
                    row.setAttribute('data-field-type', 'custom');
                    row.innerHTML = '<span class="field-drag-handle" title="Drag zum Sortieren">&#9776;</span>' +
                        '<div class="mrh-pa-field-input" style="width:180px;flex:none;">' +
                        '<input type="text" name="mrh_pa['+langId+'][custom]['+i+'][label]" value="' + mrhPaEsc(cf.label || '') + '" placeholder="Feldname">' +
                        '</div><div class="mrh-pa-field-input">' +
                        '<input type="text" name="mrh_pa['+langId+'][custom]['+i+'][value]" value="' + mrhPaEsc(cf.value || '') + '" placeholder="Wert">' +
                        '</div><button type="button" class="mrh-pa-btn mrh-pa-btn-secondary" onclick="this.closest(\'.mrh-pa-custom-row\').remove(); mrhPaSyncAllFieldOrder();" title="Entfernen">&times;</button>';
                    container.appendChild(row);
                    mrhPaInitUnifiedFieldDnD(row);
                    row.querySelectorAll('input').forEach(function(inp) { mrhPaHighlight(inp); });
                }
            }
        }
    }
    mrhPaAutoDetectPreset();
    // Sync field order across languages after AI fill
    mrhPaSyncAllFieldOrder();
}

function mrhPaAutoDetectPreset() {
    var firstPanel = document.querySelector('.mrh-pa-lang-panel');
    if (!firstPanel) return;
    var genderSel = firstPanel.querySelector('select[data-field="gender"]');
    var flowerSel = firstPanel.querySelector('select[data-field="flowering_type"]');
    var gender = genderSel ? genderSel.value : '';
    var flower = flowerSel ? flowerSel.value : '';
    var preset = '';
    if (gender === 'feminized' && flower === 'autoflower') preset = 'autoflower';
    else if (gender === 'feminized' && flower === 'photoperiod') preset = 'feminized';
    else if (gender === 'regular' && flower === 'autoflower') preset = 'auto_regular';
    else if (gender === 'regular' && flower === 'photoperiod') preset = 'regular';
    else if (gender === 'regular') preset = 'regular';
    else if (gender === 'feminized') preset = 'feminized';
    document.querySelectorAll('.mrh-pa-preset-tab').forEach(function(t) { t.classList.remove('active'); });
    if (preset) { var tab = document.querySelector('.mrh-pa-preset-tab[data-preset="'+preset+'"]'); if (tab) tab.classList.add('active'); }
}

function mrhPaHighlight(el) { el.style.backgroundColor = '#ffffcc'; setTimeout(function() { el.style.backgroundColor = ''; }, 5000); }
function mrhPaEsc(str) { var div = document.createElement('div'); div.appendChild(document.createTextNode(str)); return div.innerHTML; }

// ============================================================
// AJAX SAVE - All attributes via mrh_product_attributes.php
// ============================================================
var mrhPaProductsId = <?php echo (int)$mrh_pa_products_id; ?>;

function mrhPaSaveAll() {
    var btn = document.getElementById('mrh-pa-save-btn');
    var status = document.getElementById('mrh-pa-save-status');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="fa-solid fa-spinner fa-spin"></span> Speichern...';
    status.innerHTML = '';

    var payload = {
        products_id: mrhPaProductsId,
        is_seed: parseInt(document.getElementById('mrh_pa_is_seed')?.value || '1'),
        pictos: mrhPaCurrentPictos,
        cannabis_cups: parseInt(document.getElementById('mrh-pa-cups-input')?.value || '0'),
        field_order: mrhPaGetFieldOrder(),
        languages: {}
    };

    var panels = document.querySelectorAll('.mrh-pa-lang-panel');
    panels.forEach(function(panel) {
        var langId = panel.id.replace('mrh-pa-lang-', '');
        if (!langId) return;
        var langData = {};

        // Standard fields
        panel.querySelectorAll('.mrh-pa-input').forEach(function(input) {
            var field = input.getAttribute('data-field');
            if (field && !input.disabled) langData[field] = input.value;
        });

        // Custom fields (v1.6.0: collected from unified container in order)
        var customFields = [];
        var container = document.getElementById('mrh-pa-fields-' + langId);
        if (container) {
            container.querySelectorAll('.mrh-pa-field-row[data-field-type="custom"]').forEach(function(row) {
                var inputs = row.querySelectorAll('input[type="text"]');
                if (inputs.length >= 2) {
                    var label = inputs[0].value.trim();
                    var value = inputs[1].value.trim();
                    if (label || value) customFields.push({ label: label, value: value });
                }
            });
        }
        langData.custom = customFields;
        payload.languages[langId] = langData;
    });

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'mrh_product_attributes.php?action=save_product', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            btn.disabled = false;
            btn.innerHTML = '<span class="fa-solid fa-floppy-disk"></span> MRH Eigenschaften speichern';
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        status.style.color = '#27ae60';
                        status.innerHTML = '<span class="fa fa-check"></span> ' + (data.message || 'Gespeichert!');
                        setTimeout(function() { status.innerHTML = ''; }, 5000);
                    } else {
                        status.style.color = '#e74c3c';
                        status.innerHTML = '<span class="fa fa-times"></span> ' + (data.message || 'Fehler beim Speichern');
                    }
                } catch(e) { status.style.color = '#e74c3c'; status.innerHTML = '<span class="fa fa-times"></span> Antwort-Fehler: ' + e.message; }
            } else { status.style.color = '#e74c3c'; status.innerHTML = '<span class="fa fa-times"></span> HTTP ' + xhr.status; }
        }
    };
    xhr.send(JSON.stringify(payload));
}

// ============================================================
// v1.6.0: UNIFIED DRAG & DROP for ALL field rows (standard + custom)
// ============================================================
var mrhPaFieldDragEl = null;

function mrhPaInitUnifiedFieldDnD(row) {
    row.addEventListener('dragstart', function(e) {
        mrhPaFieldDragEl = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.getAttribute('data-field-key') || '');
    });
    row.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        var container = this.closest('.mrh-pa-fields-container');
        if (container) container.querySelectorAll('.mrh-pa-field-row').forEach(function(r) { r.classList.remove('drag-over-field'); });
    });
    row.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (mrhPaFieldDragEl && mrhPaFieldDragEl !== this && this.classList.contains('mrh-pa-field-row')) {
            this.classList.add('drag-over-field');
        }
    });
    row.addEventListener('dragleave', function() { this.classList.remove('drag-over-field'); });
    row.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over-field');
        if (mrhPaFieldDragEl && mrhPaFieldDragEl !== this && this.classList.contains('mrh-pa-field-row')) {
            this.parentNode.insertBefore(mrhPaFieldDragEl, this);
            // v1.6.0: Sync order to ALL language panels
            mrhPaSyncAllFieldOrder();
        }
        mrhPaFieldDragEl = null;
    });
}

function mrhPaInitAllFieldDnD() {
    document.querySelectorAll('.mrh-pa-fields-container .mrh-pa-field-row[draggable]').forEach(function(row) {
        mrhPaInitUnifiedFieldDnD(row);
    });
}

// v1.6.0: Sync field order across ALL language panels
function mrhPaSyncAllFieldOrder() {
    var sourcePanel = document.querySelector('.mrh-pa-lang-panel.active .mrh-pa-fields-container') ||
                      document.querySelector('.mrh-pa-fields-container');
    if (!sourcePanel) return;

    // Get the order from the active panel (standard + custom keys in sequence)
    var order = [];
    sourcePanel.querySelectorAll('.mrh-pa-field-row[data-field-key]').forEach(function(row) {
        if (row.style.display !== 'none') {
            order.push(row.getAttribute('data-field-key'));
        }
    });

    // Apply to all other panels
    document.querySelectorAll('.mrh-pa-fields-container').forEach(function(container) {
        if (container === sourcePanel) return;

        // Reorder standard fields to match
        order.forEach(function(fieldKey) {
            if (fieldKey.indexOf('custom_') === 0) return; // Custom fields are per-language
            var row = container.querySelector('.mrh-pa-field-row[data-field-key="' + fieldKey + '"]');
            if (row) container.appendChild(row);
        });

        // Move custom fields to the end (they keep their per-language content but follow after standard fields)
        container.querySelectorAll('.mrh-pa-field-row[data-field-type="custom"]').forEach(function(row) {
            container.appendChild(row);
        });
    });
}

function mrhPaGetFieldOrder() {
    var order = [];
    var firstPanel = document.querySelector('.mrh-pa-lang-panel.active .mrh-pa-fields-container') ||
                     document.querySelector('.mrh-pa-fields-container');
    if (firstPanel) {
        firstPanel.querySelectorAll('.mrh-pa-field-row[data-field-key]').forEach(function(row) {
            if (row.style.display !== 'none') {
                var key = row.getAttribute('data-field-key');
                // Only save standard field keys (custom fields are saved per-language in order)
                if (key && key.indexOf('custom_') !== 0) {
                    order.push(key);
                }
            }
        });
    }
    return order;
}

// ============================================================
// INIT ON PAGE LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    mrhPaRenderIcons();
    // Library NOT built on load (accordion closed) - built on first open
    mrhPaAutoDetectPreset();
    mrhPaUpdateCupsPreview();
    mrhPaInitAllFieldDnD();
});
</script>
