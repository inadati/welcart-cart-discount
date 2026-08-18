<?php
/**
 * Plugin Name:       Welcart Cart Discount
 * Plugin URI:         https://github.com/inadati/welcart-cart-discount
 * Description:        Welcart のカート合計金額に応じて自動割引を適用する。
 * Version:            1.0.0
 * Requires at least:  5.6
 * Requires PHP:       7.4
 * Author:             Itadani Hiroaki
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        welcart-cart-discount
 * Domain Path:        /languages
 *
 * @package Welcart_Cart_Discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCD_VERSION', '1.0.0' );
define( 'WCD_PLUGIN_FILE', __FILE__ );
define( 'WCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WCD_PLUGIN_DIR . 'includes/class-wcd-rule.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-calculator.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-settings.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-integration.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-admin.php';
require_once WCD_PLUGIN_DIR . 'includes/class-wcd-plugin.php';

add_action( 'plugins_loaded', array( 'WCD_Plugin', 'init' ) );
