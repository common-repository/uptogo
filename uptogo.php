<?php
/**
 * Plugin Name:       Uptogo
 * Plugin URI:        https://gitlab.com/uptogo/woocommerce
 * Description:       E-commerce delivery with real-time tracking.
 * Version:           1.0.0
 * Requires at least: 5.0.0
 * Requires PHP:      7.2.0
 * Author:            Uptogo
 * Author URI:        https://www.uptogo.com.br
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       uptogo
 * Domain Path:       /languages
 * 
 * @package           Uptogo
 */

/**
 * Check if WooCommerce is active.
 */
if (in_array("woocommerce/woocommerce.php", apply_filters("active_plugins", get_option("active_plugins")))) {

  /**
   * Function that returns the shipping method from an order.
   * 
   * @since           1.0.0
   * 
   * @param           WC_Order                $order        WooCommerce order to filter.
   * 
   * @return          WC_Order_Item_Shipping                WooCommerce order item shipping finded.
   */
  function get_shipping_method (
    WC_Order          $order
  ) : ?WC_Order_Item_Shipping {
    foreach ($order->get_shipping_methods() as $shipping_method) {
      if ($shipping_method->get_data()["method_id"] === "uptogo") {
        return $shipping_method;
      }
    }
    return null;
  }

  /**
   * Function that handle shipping methods initialization and inform a new shipping method class.
   * 
   * @since           1.0.0
   * 
   * @link            https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
   * 
   * @param           array                   $methods      Collection of shipping methods.
   * 
   * @return          array                                 Collection of shipping methods, including this extension.
   */
  function handle_woocommerce_shipping_methods (
    array             $methods
  ) : array {
    $methods["uptogo"] = "Uptogo_Shipping_Method";
    return $methods;
  }

  /**
   * Function that handle the shipping initialization and include a new shipping method class.
   * 
   * @since           1.0.0
   * 
   * @link            https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
   *  
   * @return          void                                  Nothing to return.
   */
  function handle_woocommerce_shipping_init () : void {

    /**
     * Check if shipping method class has been declared.
     */
    if (class_exists("Uptogo_Shipping_Method") === false) {
      include_once dirname(__FILE__) . "/includes/class-uptogo-shipping-method.php";
    }
  }

  /**
   * Function that handle the order actions initialization and inform new delivery actions.
   * 
   * @since           1.0.0
   * 
   * @link            https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
   * 
   * @global          WC_Order                $theorder
   * 
   * @param           array                   $actions      Collection of order actions.
   * 
   * @return          array                                 Collection of order actions, including new delivery actions.
   */
  function handle_woocommerce_order_actions (
    array             $actions
  ) : array {
    global $theorder;
    $shipping_method = get_shipping_method($theorder);
    if ($shipping_method !== null) {
      if ($shipping_method->meta_exists(__("Request", "uptogo"))) {
        $actions["uptogo_delivery_cancel"] = __("Cancel delivery", "uptogo");
      } else {
        $actions["uptogo_delivery_create"] = __("Request delivery", "uptogo");
      }
    }
    return $actions;
  }

  /**
   * Function that handle plugins loaded and load translated strings.
   * 
   * @since           1.0.0
   * 
   * @link            https://docs.woocommerce.com/document/introduction-to-hooks-actions-and-filters
   * @link            https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin
   * 
   * @return          void                                  Nothing to return.
   */
  function handle_plugins_loaded () : void {
    load_plugin_textdomain("uptogo", false, basename(dirname(__FILE__)) . "/languages/");
  }

  add_filter("woocommerce_shipping_methods", "handle_woocommerce_shipping_methods");
  add_action("woocommerce_shipping_init", "handle_woocommerce_shipping_init");
  add_action("woocommerce_order_actions", "handle_woocommerce_order_actions");
  add_action("plugins_loaded", "handle_plugins_loaded");
}
