<?php
/*
Plugin Name: CDNTR
Description: Simple (CDN) integration plugin.
Author: cdn.com.tr
Author URI: https://cdn.com.tr
License: GPLv2 or later
Version: 1.2.2
*/



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// constants
define( 'CDNTR_VERSION', '1.0' );
define( 'CDNTR_MIN_PHP', '5.6' );
define( 'CDNTR_MIN_WP', '5.1' );
define( 'CDNTR_FILE', __FILE__ );
define( 'CDNTR_BASE', plugin_basename( __FILE__ ) );
define( 'CDNTR_DIR', __DIR__ );

// hooks
add_action( 'plugins_loaded', array( 'CDNTR', 'init' ) );
register_activation_hook( __FILE__, array( 'CDNTR', 'on_activation' ) );
register_uninstall_hook( __FILE__, array( 'CDNTR', 'on_uninstall' ) );

// register autoload
spl_autoload_register( 'cdn_tr_autoload' );

// load required classes
function cdn_tr_autoload( $class_name ) {
    if ( in_array( $class_name, array( 'CDNTR', 'CDNTR_Engine' ) ) ) {
        require_once sprintf(
            '%s/inc/%s.class.php',
            CDNTR_DIR,
            strtolower( $class_name )
        );
    }
}

// load WP-CLI command
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
    require_once CDNTR_DIR . '/inc/cdntr_cli.class.php';
}
