<?php
// no direct access allowed
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_DIR', WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "woocommerce-lucky-wheel" . DIRECTORY_SEPARATOR );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "includes" . DIRECTORY_SEPARATOR );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_ADMIN', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "admin" . DIRECTORY_SEPARATOR );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_FRONTEND', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "frontend" . DIRECTORY_SEPARATOR );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_PLUGINS', VI_WOOCOMMERCE_LUCKY_WHEEL_DIR . "plugins" . DIRECTORY_SEPARATOR );
$plugin_url = plugins_url( 'woocommerce-lucky-wheel' );
$plugin_url = str_replace( '/includes', '', $plugin_url );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_CSS', $plugin_url . "/css/" );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_JS', $plugin_url . "/js/" );
define( 'VI_WOOCOMMERCE_LUCKY_WHEEL_IMAGES', $plugin_url . "/images/" );

require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "data.php";
require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "functions.php";
require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "check_update.php";
require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "update.php";
require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "mobile_detect.php";
require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "support.php";
require_once VI_WOOCOMMERCE_LUCKY_WHEEL_INCLUDES . "elementor/elementor.php";

vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_ADMIN, 'VI_WOOCOMMERCE_LUCKY_WHEEL_Admin_' );
vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_FRONTEND, 'VI_WOOCOMMERCE_LUCKY_WHEEL_Frontend_' );
vi_include_folder( VI_WOOCOMMERCE_LUCKY_WHEEL_PLUGINS, 'VI_WOOCOMMERCE_LUCKY_WHEEL_Plugins_' );
