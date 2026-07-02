<?php
/*
   -----------------------------------------------------------------------------------------------

	Global Special Price v1.2

	Copyright (c) 2017 by Hetfield - MerZ IT-SerVice (https://www.merz-it-service.de)

   -----------------------------------------------------------------------------------------
    Released under the GNU General Public License
   -----------------------------------------------------------------------------------------------
    based on:
    (c) Sergej Stroh (Change Global Product Price v1.4); www.southbridge.de
    (c) 2012	 Michael H. Bosch, E-Mail: michael.bosch@web.de (Sonderpreis Editor V1.02);
    (c) 2017	 oneQ (Sonderpreis Editor V1.02);

	Released under the GNU General Public License

    Bitte beachten Sie, das Teile des Quellcodes von Sergej Stroh Programm 'Change Global Product Price v1.4' kommen.
	Vielen Dank für die Erlaubnis den Code von www.southbridge.de nutzen zu dürfen.
   -----------------------------------------------------------------------------------------------
    v1.2 - Fix: specials_old_products_price wird jetzt korrekt gesetzt
         - Neu: Spalte zeigt vorhandene Sonderpreise pro Kategorie an
    v1.3 - Spalte zeigt jetzt auch Rabatt-% und Gueltigkeitsdaten (ab/bis)
         - Sortierung: Kategorien mit aktiven Specials werden nach vorne gereiht
    v1.4 - Fix: Loeschen entfernt Sonderpreise NUR bei Lagerstand = 0
         - Produkte mit Lager behalten ihren Sonderpreis
   -----------------------------------------------------------------------------------------------
 */

require('includes/application_top.php');

// include needed functions
require_once (DIR_FS_INC.'xtc_get_tax_rate.inc.php');
require_once (DIR_FS_INC.'xtc_count_products_in_category.inc.php');

// include needed classes
require_once (DIR_WS_CLASSES.'categories.php');
require_once (DIR_FS_CATALOG.DIR_WS_CLASSES . 'xtcPrice.php');

$xtPrice = new xtcPrice(DEFAULT_CURRENCY,$_SESSION['customers_status']['customers_status_id']);
$catfunc = new categories();

// prepare vars
$action = (!empty($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'update':

        $subcat_list = xtc_db_input((int)$_POST['categories_id']);
        if ($_POST['withsubcats'] == 1) {
            require_once(DIR_FS_INC . 'xtc_get_subcategories.inc.php');
            $subcategories_array = array();
            xtc_get_subcategories($subcategories_array, $subcat_list);
            $subcategories_array[] = $subcat_list;
            $subcat_list = implode("', '", $subcategories_array);
        }
        $cat_products_query = xtc_db_query("SELECT p.products_id, p.products_price, p.products_quantity, p.products_tax_class_id  
			                                  FROM " . TABLE_PRODUCTS . " p,
						                           " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c
					                         WHERE p.products_id = p2c.products_id
					                           AND p2c.categories_id IN ('".$subcat_list."')");

        while ($cat_products = xtc_db_fetch_array($cat_products_query)) {
            if ($_POST['special_delete'] == 1) {
                // Nur Sonderpreise loeschen bei Lagerstand = 0
                if ((int)$cat_products['products_quantity'] <= 0) {
                    xtc_db_query("DELETE FROM " . TABLE_SPECIALS . " WHERE products_id = '" . xtc_db_input((int)$cat_products['products_id']) . "'");
                }
            } elseif ($_POST['special'] == 1) {
                $specials_query = xtc_db_query("SELECT * FROM " . TABLE_SPECIALS . " WHERE products_id = '" . (int)$cat_products['products_id'] . "'");
                if (empty($_POST['specials_quantity'])) {
                    $quantity = $cat_products['products_quantity'];
                } else {
                    $quantity = xtc_db_input((int)$_POST['specials_quantity']);
                }
                if(xtc_db_num_rows($specials_query) > 0) {
                    $specials = xtc_db_fetch_array($specials_query);
                    $products_specials_array = array(
                        'specials_id' => $specials['specials_id'],
                        'products_id' => $cat_products['products_id'],
                        'specials_quantity' => $quantity,
                        'specials_price' => $_POST['specials_price'],
                        'products_price' => $cat_products['products_price'],
                        'specials_old_products_price' => $cat_products['products_price'],
                        'tax_rate' => xtc_get_tax_rate($cat_products['products_tax_class_id']),
                        'specials_start' => $_POST['specials_start'],
                        'specials_expires' => $_POST['specials_expires'],
                        'sorting' => '0',
                        'status' => $_POST['specials_status'],
                        'specials_action' => 'update'
                    );
                } else {
                    $products_specials_array = array(
                        'products_id' => $cat_products['products_id'],
                        'specials_quantity' => $quantity,
                        'specials_price' => $_POST['specials_price'],
                        'products_price' => $cat_products['products_price'],
                        'specials_old_products_price' => $cat_products['products_price'],
                        'tax_rate' => xtc_get_tax_rate($cat_products['products_tax_class_id']),
                        'specials_start' => $_POST['specials_start'],
                        'specials_expires' => $_POST['specials_expires'],
                        'sorting' => '0',
                        'specials_status' => $_POST['specials_status'],
                        'specials_action' => 'insert'
                    );
                }
                $specials_id = $catfunc->saveSpecialsData($products_specials_array);
            }
        } // WHILE
        xtc_redirect(xtc_href_link(FILENAME_SPECIAL_PRICE , 'category=' . (int)$_POST['edit_categories_id']));
    //	break;

    default:
        $parent_id = isset($_GET['category']) ? $_GET['category'] : 0;
        $categories_query = xtc_db_query("SELECT c.categories_id, c.categories_status, cd.categories_name,
                                            (SELECT COUNT(*) FROM " . TABLE_SPECIALS . " s 
                                             JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c ON s.products_id = p2c.products_id 
                                             WHERE p2c.categories_id = c.categories_id AND s.status = 1) as active_specials_count
			                                FROM " . TABLE_CATEGORIES . " c,
				                                 " . TABLE_CATEGORIES_DESCRIPTION . " cd
			                               WHERE c.categories_id = cd.categories_id
			                                 AND cd.language_id = '" . xtc_db_input((int)$_SESSION['languages_id']) . "' 
			                                 AND c.parent_id = '" . xtc_db_input((int)$parent_id) . "'
			                            ORDER BY (active_specials_count > 0) DESC, cd.categories_name ASC");
        $num_selected_rows = xtc_db_num_rows($categories_query);

}
require(DIR_WS_INCLUDES . 'head.php');
?>
<script type="text/javascript" src="includes/general.js"></script>
<?php
require_once (DIR_WS_INCLUDES.'javascript/jQueryDateTimePicker/datepicker.js.php');
?>
<style>
.specials-info { font-size: 11px; color: #666; }
.specials-info .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin: 1px 0; }
.specials-info .badge-active { background: #d4edda; color: #155724; }
.specials-info .badge-expired { background: #f8d7da; color: #721c24; }
.specials-info .badge-none { background: #e2e3e5; color: #383d41; }
</style>
</head>
<body>
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->
<!-- body //-->
<table class="tableBody">
    <tr>
        <?php //left_navigation
        if (USE_ADMIN_TOP_MENU == 'false') {
            echo '<td class="columnLeft2">' . PHP_EOL;
            echo '<!-- left_navigation //-->' . PHP_EOL;
            require_once(DIR_WS_INCLUDES . 'column_left.php');
            echo '<!-- left_navigation eof //-->' . PHP_EOL;
            echo '</td>' . PHP_EOL;
        }
        ?>
        <!-- body_text //-->
        <td class="boxCenter">
            <!-- begin of module_conent //-->

            <div class="pageHeadingImage"><?php echo xtc_image(DIR_WS_ICONS . 'heading/icon_configuration.png'); ?></div>
            <div class="pageHeading"><?php echo HEADING_TITLE; ?></div>
            <div class="main pdg2 flt-l"><?php echo 'Global Special Price v1.4'; ?><br /></div>
                <table class="main important_info" width="100%" border="0" cellspacing="1" cellpadding="2">
                    <tr>
                        <td class="main"><?php echo CONTENT_NOTE; ?></td>
                    </tr>
                </table>
                <br />
                <?php if ($num_selected_rows >= 1) { ?>
                    <table border="0" cellspacing="1" cellpadding="3">
                        <tr>
                            <td class="main">&nbsp;<a href="<?php echo xtc_href_link(FILENAME_SPECIAL_PRICE); ?>"><strong><?php echo NAVIGATION_OVERVIEW; ?> &raquo;</strong></a></td>
                        </tr>
                    </table>

                    <table class="tableBoxCenter collapse">
                        <tr class="dataTableHeadingRow">
                            <td class="dataTableHeadingContent txta-c" width="5%"><?php echo CATEGORIES_ID; ?></td>
                            <td class="dataTableHeadingContent" width="12%"><?php echo CATEGORIES_NAME; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="8%"><?php echo PRODUCTS_COUNT; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="10%">Aktive Specials</td>
                            <td class="dataTableHeadingContent txta-c" width="9%"><?php echo TEXT_SPECIALS_SPECIAL_PRICE; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="8%"><?php echo TEXT_SPECIALS_SPECIAL_QUANTITY; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="9%"><?php echo TEXT_SPECIALS_START_DATE; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="9%"><?php echo TEXT_SPECIALS_EXPIRES_DATE; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="7%"><?php echo TEXT_SPECIAL_STATUS;?></td>
                            <td class="dataTableHeadingContent txta-c" width="7%"><?php echo TEXT_WITH_SUBCATS;?></td>
                            <td class="dataTableHeadingContent txta-c" width="6%"><?php echo PRODUCTS_SPECIALS_TAB; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="6%"><?php echo PRODUCTS_SPECIALS_DELETE_TAB; ?></td>
                            <td class="dataTableHeadingContent txta-c" width="4%"><?php echo CATEGORIES_ACTION; ?></td>
                        </tr>
                        <?php
                        $i = 0;
                        while ($categories = xtc_db_fetch_array($categories_query)) {
                            $products_anzahl = xtc_count_products_in_category($categories['categories_id'],true);
                            
                            // Neue Spalte: Vorhandene Specials in dieser Kategorie zaehlen inkl. Rabatt-% und Daten
                            $specials_info_query = xtc_db_query("SELECT 
                                COUNT(CASE WHEN s.status = 1 THEN 1 END) as active_count,
                                COUNT(CASE WHEN s.status = 0 THEN 1 END) as inactive_count,
                                MIN(CASE WHEN s.status = 1 THEN s.specials_new_products_price END) as min_price,
                                MAX(CASE WHEN s.status = 1 THEN s.expires_date END) as max_expires,
                                MIN(CASE WHEN s.status = 1 THEN s.specials_date_added END) as min_start,
                                AVG(CASE WHEN s.status = 1 AND s.specials_old_products_price > 0 
                                    THEN ROUND((1 - s.specials_new_products_price / s.specials_old_products_price) * 100, 0) END) as avg_discount
                                FROM " . TABLE_SPECIALS . " s
                                JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c ON s.products_id = p2c.products_id
                                WHERE p2c.categories_id = '" . (int)$categories['categories_id'] . "'");
                            $specials_info = xtc_db_fetch_array($specials_info_query);
                            
                            echo xtc_draw_form('products_price', FILENAME_SPECIAL_PRICE, 'action=update', 'post', '');
                            ?>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $("#DatepickerSpecials_<?php echo $categories['categories_id'];?>").datetimepicker({
                                        dayOfWeekStart:1,
                                        timepicker:false,
                                        format:'Y-m-d'
                                    });
                                    $("#DatepickerSpecialsStart_<?php echo $categories['categories_id'];?>").datetimepicker({
                                        dayOfWeekStart:1,
                                        timepicker:false,
                                        format:'Y-m-d'
                                    });
                                });
                            </script>
                            <tr class="dataTableRow" style="background:<?php echo($i % 2 ? '#eeeeee' : '#f1f1f1'); ?>">
                                <td class="dataTableContent txta-c"><?php echo $categories['categories_id']; ?></td>
                                <?php
                                $unterkategoriequery = "SELECT * FROM " . TABLE_CATEGORIES . " WHERE parent_id = '" . (int)$categories['categories_id'] . "'";
                                $unterkategorie = xtc_db_query($unterkategoriequery);
                                if (xtc_db_num_rows($unterkategorie) == 0) {
                                    echo '<td class="dataTableContent">' . $categories['categories_name'] . xtc_draw_hidden_field('categories_id', $categories['categories_id']) . '</td>';
                                } elseif (xtc_db_num_rows($unterkategorie) > 0) {
                                    echo '<td class="dataTableContent"><a href="' . xtc_href_link(FILENAME_SPECIAL_PRICE, 'category=' . $categories['categories_id']) . '">' . $categories['categories_name'] . ' &raquo;</a>' . xtc_draw_hidden_field('categories_id', $categories['categories_id']) . '</td>';
                                }
                                ?>
                                <td class="dataTableContent txta-c"><?php echo $products_anzahl . xtc_draw_hidden_field('products_count', $products_anzahl); ?></td>
                                <td class="dataTableContent txta-c specials-info">
                                    <?php 
                                    if ($specials_info['active_count'] > 0) {
                                        echo '<span class="badge badge-active">' . (int)$specials_info['active_count'] . ' aktiv</span>';
                                        // Rabatt-% anzeigen
                                        if (!empty($specials_info['avg_discount'])) {
                                            echo ' <span class="badge badge-active">-' . (int)$specials_info['avg_discount'] . '%</span>';
                                        }
                                        // Gueltig ab
                                        if (!empty($specials_info['min_start']) && $specials_info['min_start'] != '0000-00-00 00:00:00') {
                                            echo '<br><small>ab ' . date('Y-m-d', strtotime($specials_info['min_start'])) . '</small>';
                                        }
                                        // Gueltig bis
                                        if (!empty($specials_info['max_expires']) && $specials_info['max_expires'] != '0000-00-00 00:00:00') {
                                            echo '<br><small>bis ' . date('Y-m-d', strtotime($specials_info['max_expires'])) . '</small>';
                                        } else {
                                            echo '<br><small>bis: unbegrenzt</small>';
                                        }
                                    } else {
                                        echo '<span class="badge badge-none">keine</span>';
                                    }
                                    if ($specials_info['inactive_count'] > 0) {
                                        echo '<br><span class="badge badge-expired">' . (int)$specials_info['inactive_count'] . ' inaktiv</span>';
                                    }
                                    ?>
                                </td>
                                <td class="dataTableContent txta-c">
                                    <?php echo xtc_draw_input_field('specials_price', '', 'style="width: 135px"') . draw_tooltip(TEXT_CATSPECIALS_SPECIAL_PRICE_TT); ?>
                                </td>
                                <td class="dataTableContent txta-c">
                                    <?php echo xtc_draw_input_field('specials_quantity', '', 'style="width: 135px"') . draw_tooltip(TEXT_CATSPECIALS_SPECIAL_QUANTITY_TT); ?>
                                </td>
                                <td class="dataTableContent txta-c">
                                    <?php echo xtc_draw_input_field('specials_start', '' ,'id="DatepickerSpecialsStart_'.$categories['categories_id'].'" style="width: 135px"') . draw_tooltip(TEXT_CATSPECIALS_START_DATE_TT.SPECIALS_DATE_START_TT); ?>
                                </td>
                                <td class="dataTableContent txta-c">
                                    <?php echo xtc_draw_input_field('specials_expires', '' ,'id="DatepickerSpecials_'.$categories['categories_id'].'" style="width: 135px"') . draw_tooltip(TEXT_CATSPECIALS_EXPIRES_DATE_TT.SPECIALS_DATE_END_TT); ?>
                                </td>
                                <td class="dataTableContent txta-c"><?php echo xtc_draw_selection_field('specials_status', 'checkbox', '1', true); ?></td>
                                <td class="dataTableContent txta-c"><?php echo xtc_draw_selection_field('withsubcats', 'checkbox', '1', false); ?></td>
                                <td class="dataTableContent txta-c"><?php echo xtc_draw_selection_field('special', 'checkbox', '1', true); ?></td>
                                <td class="dataTableContent txta-c"><?php echo xtc_draw_selection_field('special_delete', 'checkbox', '1', false); ?></td>
                                <td class="dataTableContent txta-c"><?php echo '<input type="image" style="border:0;" src="'.DIR_WS_IMAGES.'icon_save.png" title="' . PRODUCTS_PRICE_UPDATE . '" value="' . PRODUCTS_PRICE_UPDATE . '" onClick="return confirm(\'' . UPDATE_ENTRY . '\')">'; ?></td>
                            </tr>
                            <?php
                            echo xtc_draw_hidden_field('edit_categories_id', $parent_id);
                            $i++;
                            ?>
                            </form>
                            <?php
                        }  // WHILE
                        ?>
                    </table>
                    <br /><br />
                    <table class="tableBoxCenter collapse">
                        <tr class="dataTableHeadingRow">
                            <td class="dataTableHeadingContent" colspan="2" style="border-bottom:1px solid #ddd;"><?php echo LEGENDE; ?></td>
                        </tr>
                        <tr class="dataTableRow">
                            <td class="dataTableContent" width="20%" style="background:#eee;"><strong><?php echo TEXT_SPECIAL_STATUS; ?></strong></td>
                            <td class="dataTableContent"><?php echo TEXT_SPECIAL_STATUS_INFO; ?></td>
                        </tr>
                        <tr class="dataTableRow">
                            <td class="dataTableContent" width="20%" style="background:#eee;"><strong><?php echo TEXT_WITH_SUBCATS; ?></strong></td>
                            <td class="dataTableContent"><?php echo TEXT_WITH_SUBCATS_INFO; ?></td>
                        </tr>
                        <tr class="dataTableRow">
                            <td class="dataTableContent" width="20%" style="background:#eee;"><strong><?php echo PRODUCTS_SPECIALS_TAB; ?></strong></td>
                            <td class="dataTableContent"><?php echo PRODUCTS_SPECIALS_TAB_TEXT; ?></td>
                        </tr>
                        <tr class="dataTableRow">
                            <td class="dataTableContent" width="20%" style="background:#eee;"><strong><?php echo PRODUCTS_SPECIALS_DELETE_TAB; ?></strong></td>
                            <td class="dataTableContent"><?php echo PRODUCTS_SPECIALS_DELETE_TAB_TEXT; ?></td>
                        </tr>
                    </table>
                    <?php
                } //IF

                if (isset($_GET['category'])) {
                    $akt_kategorie_query = xtc_db_query("SELECT categories_id, categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = '" . xtc_db_input((int)$_GET['category']) . "' AND language_id = '" . xtc_db_input((int)$_SESSION['languages_id']) . "'");
                    $akt_kategorie = xtc_db_fetch_array($akt_kategorie_query);
                    ?>
                    <table border="0" cellspacing="1" cellpadding="3">
                        <tr>
                            <td class="main"><i><?php echo CATEGORIES_NAME.': '.$akt_kategorie['categories_name'] . xtc_draw_hidden_field('categories_id', $akt_kategorie['categories_id']); ?></i></td>
                        </tr>
                        <tr>
                            <td class="main"><a href="<?php echo xtc_href_link(FILENAME_SPECIAL_PRICE); ?>"><strong><?php echo NAVIGATION_OVERVIEW; ?> &raquo;</strong></a></td>
                        </tr>
                    </table>
                <?php
                }
                ?>
        </td>
        <!-- body_text_eof //-->
    </tr>
</table>
<!-- body_eof //-->
<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br />
</body>
    </html>
