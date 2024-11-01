<?php

/**
 * ShopyBot - E-Commerce Chatbot
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link                 https://www.shopybot.com
 * @since                1.0.18
 * @package              Shopybot_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:          ShopyBot - E-Commerce Chatbot
 * Plugin URI:           https://www.shopybot.com/connect-bot/woocommerce
 * Description:          Few clicks to create a Facebook Messenger Chatbot for your WooCommerce shop! Automatic products import, 12 languages supported, add product to WooCommerce cart from Messenger, fast checkout!
 * Version:              1.0.18
 * Author:               ShopyBot
 * Text Domain:          shopybot-woocommerce
 * Domain Path:          /languages
 * Author URI:           https://www.shopybot.com
 * License:              GPL-2.0+
 * License URI:          http://www.gnu.org/licenses/gpl-2.0.txt
 * Woo:                  2127297:0ea4fe4c2d7ca6338f8a322fb3e4e187
 * Requires at least:    4.9
 * Tested up to:         5.4
 * WC requires at least: 3.0.0
 * WC tested up to:      4.2.0
 */


if(!class_exists('Shopybot_Woocommerce')) :

	class Shopybot_Woocommerce {
		/**
		 * Construct the plugin.
		 */
		public function __construct() {
			$this->id = 'shopybot-woocommerce';
			add_action('plugins_loaded', array($this, 'init'));
		}

		/**
		 * Initialize the plugin.
		 */
		public function init() {
			include_once 'includes/class-wc-shopybot-notices.php';
			include_once 'includes/class-wc-shopybot-functions.php';

			load_plugin_textdomain( 'shopybot-woocommerce', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );

			// Checks if WooCommerce is installed.
			if(class_exists('WC_Integration')) {
				// Include our integration class.
				include_once 'includes/class-wc-shopybot-integration.php';
				include_once 'includes/class-wc-shopybot-export.php';

				// Register the integration.
				add_filter('woocommerce_integrations', array($this, 'add_integration'));

				$this->shopybot_export = new WC_Shopybot_Export($this->id, array());
				$this->shopybot_export->numberOffers();

				add_action('admin_enqueue_scripts', array($this, 'shopybot_enqueue_scripts'));
			} else {
				add_action('admin_notices', 'shopybot_check_woocommerce_installation');
			}

			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
		}

		public function shopybot_woocommerce_load_plugin_textdomain() {
		  load_plugin_textdomain( 'shopybot-woocommerce', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
		}

		function add_action_links($links) {
			$mylinks = array(
				'<a href="' . admin_url('/admin.php?page=wc-settings&tab=integration&section=shopybot-woocommerce') . '">'.
				__( 'Settings', 'shopybot-woocommerce' )
				.'</a>',
			);
			return array_merge($links, $mylinks);
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_integration($integrations) {
			array_unshift($integrations, 'WC_Shopybot_Integration');

			return $integrations;
		}

		public function shopybot_enqueue_scripts() {
			if(is_admin()) {
				wp_register_script('shopybot-script', plugins_url('/assets/js/script.js', __FILE__), array('jquery'), '1.0', true);
				wp_enqueue_script('shopybot-script');

				wp_register_style('shopybot-style', plugins_url('/assets/css/style.css', __FILE__), array('woocommerce_admin_styles'), '1.0', 'all');
				wp_enqueue_style('shopybot-style');
			}
		}
	}

	$Shopybot_Woocommerce = new Shopybot_Woocommerce();

endif;

register_uninstall_hook( __FILE__, 'shopybot_woocommerce_uninstall' );
function shopybot_woocommerce_uninstall() {
    global $wpdb;

    // cleanup shopybot options
    delete_option('shopybot_api_key');
    delete_option('shopybot_shop_token');
    delete_option('shopybot_connect_fb_page_url');
    delete_option('shopybot_connect_shop_url');
    delete_option('shopybot_disconnect_shop_url');
    delete_option('shopybot_disconnect_fb_page_url');
    delete_option('shopybot_fb_page_id');
    delete_option('shopybot_fb_page_name');

    delete_option('shopybot-woocommerce_in_process');
    delete_option('shopybot-woocommerce_page');
    delete_option('shopybot-woocommerce_pages');
    delete_option('shopybot-woocommerce_lock');
    delete_option('shopybot-woocommerce_get_ids');

    // delete all cached products
    $table_name = $wpdb->prefix . 'postmeta';
    $sql = "DELETE FROM $table_name WHERE meta_key = 'shopybot-woocommerce_yml_offer'";
    $wpdb->query($sql);
}
