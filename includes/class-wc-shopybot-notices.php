<?php
/**
 * Shopybot WC integration notices
 *
 * @package  WC_Chatbot_Integration
 * @category Integration
 * @author   WooThemes
 */
if(!defined('ABSPATH')) {
	exit;
}

function shopybot_shop_connect_success() {
	?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Congratulations! Your shop is now connected to Bot on shopybot.com!', 'shopybot-woocommerce'); ?></p>
    </div>
	<?php
}

function shopybot_shop_connect_error() {
	?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('Error! Cannot connect your store to Bot on shopybot. Please contact us at support@shopybot.com if you want to connect your store.', 'shopybot-woocommerce'); ?></p>
    </div>
	<?php
}

function shopybot_shop_disconnect_success() {
	?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Congratulations! You disconnected your store and deleted your Bot successfully!', 'shopybot-woocommerce'); ?></p>
    </div>
	<?php
}

function shopybot_shop_disconnect_error() {
	?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('Error occurred during the disconnection process. Please contact us at support@shopybot.com if you want to disconnect your store.', 'shopybot-woocommerce'); ?></p>
    </div>
	<?php
}

function shopybot_check_woocommerce_installation() {
	?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('Please check whether you have WooCommerce installed', 'shopybot-woocommerce'); ?></p>
    </div>
	<?php
}

?>
