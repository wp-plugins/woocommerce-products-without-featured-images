<?php
/*
  Plugin Name: WooCommerce Products without featured images
  Plugin URI: http://www.softagon.com.br
  Description: A very simple product list for WooCommerce products, without featured images.
  Version: 0.1
  Author: Hermes Alves
  Author URI: http://www.softagon.com.br
  Copyright: © 2009-2015 Softagon.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 

=== Plugin Name ===
Contributors: SOFTAGON, zerutreck
Donate link: http://www.softagon.com.br
Tags: woocommerce, products, image, featured
Requires at least: 3.4
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A very simple product list for WooCommerce products, without featured images.

== Description ==

Sometimes you need to know which products are not with featured image, it is essential for the success of your e-commerce using the WooCommerce technology.

This plugin will list all WooCommerce products that do not have highlighted image, and you can edit to put a picture of your product. 
  
== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `softagon_product_without_image` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit the Sub-menu WooCommerce -> Missing images

== Frequently Asked Questions ==

= Where is the option on the menu? =
This plugin will create an entry in the menu WooCommerce will be a submenu called Missing Images.


== Screenshots ==

1. Menu option;
2. The simple product list without featured images;

== Changelog ==

= 0.1 =
The first version, it' a simple product list.


*/


if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Softagon_without_images extends WP_List_Table {

    function __construct() {
        global $status, $page;

        //Set parent defaults
        parent::__construct(array(
            'singular' => 'softagon',
            'plural' => 'softagons',
            'ajax' => false
        ));
    }

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'ID':
                return $item[$column_name];
            case 'stock':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function column_title($item) {

        $actions['edit'] = "<a class='' href='" . admin_url("post.php?post=" . $item['ID'] . "&action=edit") . "'>" . __('Edit', 'cgc_ub') . "</a>";
        $actions['view'] = "<a class='' href='" . $item['guid'] . "' target='_blank'>" . __('View', 'cgc_ub') . "</a>";

        //Return the title contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
                /* $1%s */ $item['title'],
                /* $2%s */ $item['ID'],
                /* $3%s */ $this->row_actions($actions)
        );
    }

    function column_cb($item) {
        return sprintf(
                '<input type="checkbox" name="%1$s[]" value="%2$s" />',
                /* $1%s */ $this->_args['singular'], //Let's simply repurpose the table's singular label ("softagon")
                /* $2%s */ $item['ID']                //The value of the checkbox should be the record's id
        );
    }

    function get_columns() {
        $columns = array(
            'ID' => 'ID',
            'title' => 'Title',
            'stock' => 'Stock'
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'ID' => array('ID', false),
            'stock' => array('stock', false),
            'title' => array('title', false) //true means it's already sorted
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb; //This is used only if making any database queries
        $images_result = $wpdb->get_results("SELECT p.ID AS ID, p.post_title AS title,
            post_name AS name,guid, meta_key,
            (SELECT ROUND(meta_value,0) FROM wp_postmeta AS pta WHERE p.ID = pta.post_id AND meta_key = '_stock' ) AS stock 
            FROM wp_posts p LEFT OUTER JOIN wp_postmeta pt ON (p.ID=pt.post_id AND pt.meta_key = '_thumbnail_id') 
            WHERE p.post_type = 'product' AND meta_key IS NULL
            ORDER BY p.ID DESC", 'ARRAY_A');

        $per_page = 5;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $data = $images_result;

        function usort_reorder($a, $b) {
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'title'; //If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
            return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
        }

        usort($data, 'usort_reorder');

        $current_page = $this->get_pagenum();

        $total_items = count($data);

        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);

        $this->items = $data;


        $this->set_pagination_args(array(
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page, //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //WE have to calculate the total number of pages
        ));
    }

}

/** * *********************** REGISTER THE TEST PAGE ****************************
 * ******************************************************************************
 * Now we just need to define an admin page. For this example, we'll add a top-level
 * menu item to the bottom of the admin menus.
 */
function softagon_product_add_menu_items() {
    add_submenu_page('woocommerce', 'Softagon WooCommerce products without featured images', 'Missing images', 'activate_plugins', 'softagon_product_without_image', 'softagon_product_without_image_render');
}

add_action('admin_menu', 'softagon_product_add_menu_items');

/** * ************************** RENDER TEST PAGE ********************************
 * ******************************************************************************
 * This function renders the admin page and the example list table. Although it's
 * possible to call prepare_items() and display() from the constructor, there
 * are often times where you may need to include logic here between those steps,
 * so we've instead called those methods explicitly. It keeps things flexible, and
 * it's the way the list tables are used in the WordPress core.
 */
function softagon_product_without_image_render() {

    //Create an instance of our package class...
    $products_null = new Softagon_without_images();
    //Fetch, prepare, sort, and filter our data...
    $products_null->prepare_items();
    ?>
    <div class="wrap">

        <div id="icon-users" class="icon32"><br/></div>
        <h2>WooCommerce Products without featured images</h2>
        <small>A simple list with products and total</small>
        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
        <form id="softagons-filter" method="get">
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
            <!-- Now we can render the completed list table -->
    <?php $products_null->display() ?>
        </form>

    </div>
    <?php
}
