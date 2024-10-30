<?php
/**
 * CDNTR base
 *
 * @since  0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CDNTR {

    /**
     * initialize plugin
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    public static function init() {

        new self();
    }


    /**
     * constructor
     *
     * @since   0.0.1
     * @change  2.0.0
     */

    public function __construct() {

        // engine hook
        add_action( 'setup_theme', array( 'CDNTR_Engine', 'start' ) );

        // init hooks
        add_action( 'init', array( __CLASS__, 'process_purge_cache_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );
        add_action( 'init', array( __CLASS__, 'check_meta_valid' ) );

        // multisite hook
        add_action( 'wp_initialize_site', array( __CLASS__, 'install_later' ) );

        // admin interface hooks
        if ( is_admin() ) {
            add_action( 'init', array( __CLASS__, 'cdntr_api_check' ) );
            // admin bar hook
            add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_items' ), 90 );
            // settings
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
            add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_admin_resources' ) );
            // dashboard
            add_filter( 'plugin_action_links_' . CDNTR_BASE, array( __CLASS__, 'add_plugin_action_links' ) );
            //row meta plugin
            //add_filter( 'plugin_row_meta', array( __CLASS__, 'add_plugin_row_meta' ), 10, 2 );
            // notices
            add_action( 'admin_notices', array( __CLASS__, 'requirements_check' ) );
            add_action( 'admin_notices', array( __CLASS__, 'cache_purged_notice' ) );
            add_action( 'admin_notices', array( __CLASS__, 'config_validated_notice' ) );
        }
    }

    public static function enqueue_custom_admin_script() {
        wp_enqueue_script('custom-admin-script', plugin_dir_url(__FILE__) . 'js/custom-admin-script.js', array('jquery'), CDNTR_VERSION, true);
    }


    /**
     * activation hook
     *
     * @since   2.0.0
     * @change  2.0.8
     *
     * @param   boolean  $network_wide  network activated
     */

    public static function on_activation( $network_wide ) {

        // add backend requirements
        self::each_site( $network_wide, self::class . '::update_backend' );
    }


    /**
     * uninstall hook
     *
     * @since   2.0.0
     * @change  2.0.8
     */

    public static function on_uninstall() {

        // uninstall backend requirements
        self::each_site( is_multisite(), self::class . '::uninstall_backend' );
    }


    /**
     * install on new site in multisite network
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @param   WP_Site  $new_site  new site instance
     */

    public static function install_later( $new_site ) {

        // check if network activated
        if ( ! is_plugin_active_for_network( CDNTR_BASE ) ) {
            return;
        }

        // switch to new site
        switch_to_blog( (int) $new_site->blog_id );

        // add backend requirements
        self::update_backend();

        // restore current blog from before new site
        restore_current_blog();
    }


    /**
     * add or update backend requirements
     *
     * @since   2.0.0
     * @change  2.0.4
     *
     * @return  array  $new_option_value  new or current database option value
     */

    public static function update_backend() {

        // get defined settings, fall back to empty array if not found
        $old_option_value = get_option( 'cdntr', array() );

        // maybe convert old settings to new settings
        $new_option_value = self::convert_settings( $old_option_value );

        // update default system settings
        $new_option_value = wp_parse_args( self::get_default_settings( 'system' ), $new_option_value );

        // merge defined settings into default settings
        $new_option_value = wp_parse_args( $new_option_value, self::get_default_settings() );

        // validate settings
        $new_option_value = self::validate_settings( $new_option_value );

        // add or update database option
        update_option( 'cdntr', $new_option_value );

        return $new_option_value;
    }


    /**
     * uninstall backend requirements
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    private static function uninstall_backend() {

        // delete database option
        delete_option( 'cdntr' );
    }


    /**
     * enter each site
     *
     * @since   2.0.0
     * @change  2.0.4
     *
     * @param   boolean  $network          whether or not each site in network
     * @param   string   $callback         callback function
     * @param   array    $callback_params  callback function parameters
     * @return  array    $callback_return  returned value(s) from callback function
     */

    private static function each_site( $network, $callback, $callback_params = array() ) {

        $callback_return = array();

        if ( $network ) {
            $blog_ids = self::get_blog_ids();

            // switch to each site in network
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                $callback_return[ $blog_id ] = call_user_func_array( $callback, $callback_params );
                restore_current_blog();
            }
        } else {
            $blog_id = 1;
            $callback_return[ $blog_id ] = call_user_func_array( $callback, $callback_params );
        }

        return $callback_return;
    }


    /**
     * get settings from database
     *
     * @since       0.0.1
     * @deprecated  2.0.0
     */

    public static function get_options() {

        return self::get_settings();
    }


    /**
     * get settings from database
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @return  array  $settings  current settings from database
     */

    public static function get_settings() {

        // get database option value
        $settings = get_option( 'cdntr' );

        // if database option does not exist or settings are outdated
        if ( $settings === false || ! isset( $settings['version'] ) || $settings['version'] !== CDNTR_VERSION ) {
            $settings = self::update_backend();
        }

        return $settings;
    }


    /**
     * get blog IDs
     *
     * @since   2.0.0
     * @change  2.0.4
     *
     * @return  array  $blog_ids  blog IDs
     */

    private static function get_blog_ids() {

        $cache_key = 'cdntr_blog_ids';
        $blog_ids = wp_cache_get( $cache_key );

        if ( false === $blog_ids ) {
            $blog_ids = array( 1 );

            if ( is_multisite() ) {
                //global $wpdb;
                //$blog_ids = array_map( 'absint', $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) );
                $sites = get_sites();
                $blog_ids = array_map( function( $site ) {
                    return $site->blog_id;
                }, $sites );
            }

            wp_cache_set( $cache_key, $blog_ids );
        }

        return $blog_ids;
    }


    /**
     * get the cache purged transient name used for the purge notice
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @return  string  $transient_name  transient name
     */

    private static function get_cache_purged_transient_name() {

        $transient_name = 'cdntr_cache_purged_' . get_current_user_id();

        return $transient_name;
    }


    /**
     * get the configuration validated transient name used for the validation notice
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @return  string  $transient_name  transient name
     */

    private static function get_config_validated_transient_name() {

        $transient_name = 'cdntr_config_validated_' . get_current_user_id();

        return $transient_name;
    }


    /**
     * get default settings
     *
     * @since   2.0.0
     * @change  2.0.3
     *
     * @param   string  $settings_type                              default `system` settings
     * @return  array   $system_default_settings|$default_settings  only default system settings or all default settings
     */

    private static function get_default_settings( $settings_type = null ) {

        $system_default_settings = array( 'version' => (string) CDNTR_VERSION );

        if ( $settings_type === 'system' ) {
            return $system_default_settings;
        }

        $user_default_settings = array(
            'cdn_hostname'             => '',
            'cdn_hostname_arr'         => array(
                1 => 'xxx.com',
                2 => 'yyy.com',
                3 => 'zzz.com',
            ),
            'included_file_extensions' => implode( PHP_EOL, array(
                                              '.avif',
                                              '.css',
                                              '.gif',
                                              '.jpeg',
                                              '.jpg',
                                              '.js',
                                              '.json',
                                              '.mp3',
                                              '.mp4',
                                              '.pdf',
                                              '.png',
                                              '.svg',
                                              '.webp',
                                          ) ),
            'excluded_strings'           => '',
            'cdntr_api_user'             => '',
            'cdntr_account_expires'      => '',
            'cdntr_is_purge_all_button'  => '',
            'cdntr_api_password'         => '',
        );
        // cdntr_account_id cdntr_api_user cdntr_api_password

        // merge default settings
        $default_settings = wp_parse_args( $user_default_settings, $system_default_settings );

        return $default_settings;
    }


    /**
     * convert settings to new structure
     *
     * @since   2.0.0
     * @change  2.0.1
     *
     * @param   array  $settings  settings
     * @return  array  $settings  converted settings if applicable, unchanged otherwise
     */

    private static function convert_settings( $settings ) {

        // check if there are any settings to convert
        if ( empty( $settings ) ) {
            return $settings;
        }

        // updated settings
        if ( isset( $settings['url'] ) && is_string( $settings['url'] ) && substr_count( $settings['url'], '/' ) > 2 ) {
            $settings['url'] = '';
        }

        // reformatted settings
        if ( isset( $settings['excludes'] ) && is_string( $settings['excludes'] ) ) {
            $settings['excludes'] = str_replace( ',', PHP_EOL, $settings['excludes'] );
            $settings['excludes'] = str_replace( '.php', '', $settings['excludes'] );
        }

        // renamed or removed settings
        $settings_names = array(
            // 2.0.0
            'url'      => 'cdn_hostname',
            'dirs'     => '', // deprecated
            'excludes' => 'excluded_strings',
            'relative' => '', // deprecated
            'https'    => '', // deprecated
        );

        foreach ( $settings_names as $old_name => $new_name ) {
            if ( array_key_exists( $old_name, $settings ) ) {
                if ( ! empty( $new_name ) ) {
                    $settings[ $new_name ] = $settings[ $old_name ];
                }

                unset( $settings[ $old_name ] );
            }
        }

        return $settings;
    }


    /**
     * add plugin action links in the plugins list table
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @param   array  $action_links  action links
     * @return  array  $action_links  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_action_links( $action_links ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $action_links;
        }

        // prepend action link
        array_unshift( $action_links, sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=cdntr' ),
            esc_html__( 'Settings', 'cdntr' )
        ) );

        return $action_links;
    }


    /**
     * add plugin metadata in the plugins list table
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @param   array   $plugin_meta  plugin metadata, including the version, author, author URI, and plugin URI
     * @param   string  $plugin_file  path to the plugin file relative to the plugins directory
     * @return  array   $plugin_meta  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_row_meta( $plugin_meta, $plugin_file ) {

        // check if CDNTR row
        if ( $plugin_file !== CDNTR_BASE ) {
            return $plugin_meta;
        }

        // append metadata
        $plugin_meta = wp_parse_args(
            array(
                '<a href="https://www.cdn.com.tr/support/wordpress-cdntr-plugin" target="_blank" rel="nofollow noopener">Documentation</a>',
            ),
            $plugin_meta
        );

        return $plugin_meta;
    }


    /**
     * add admin bar items
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @param   object  $wp_admin_bar  menu properties
     */

    public static function add_admin_bar_items( $wp_admin_bar ) {

        // check user role
        if ( ! self::user_can_purge_cache() ) {
            return;
        }
        // check if CDNTR API key is set
        if ( empty( CDNTR_Engine::$settings['cdntr_api_password'] ) ) {
            return;
        }

        // check if CDNTR username is set
        if ( empty( CDNTR_Engine::$settings['cdntr_api_user'] ) ) {
            return;
        }

        $is_status = get_option( 'cdntr_api_check_status', 0 );

        if ($is_status != 1 ){
            return;
        }


        // add admin purge link
        if ( ! is_network_admin() ) {
            $wp_admin_bar->add_menu(
                array(
                    'id'     => 'cdntr-purge-cache',
                    'href'   => wp_nonce_url( add_query_arg( array(
                                    '_cache' => 'cdn',
                                    '_action' => 'purge',
                                ) ), 'cdntr_purge_cache_nonce' ),
                    'parent' => 'top-secondary',
                    'title'  => '<span class="ab-item">' . esc_html__( 'Purge All CDNTR Cache', 'cdntr' ) . '</span>',
                    'meta'   => array( 'title' => esc_html__('Purge All CDNTR Cache', 'cdntr') ),
                )
            );
        }
    }


    /**
     * enqueue styles and scripts
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    public static function add_admin_resources( $hook ) {
        // settings page
        if ( $hook === 'settings_page_cdntr' ) {
            wp_enqueue_style( 'cdntr-settings', plugins_url( 'css/settings.min.css', CDNTR_FILE ), array(), CDNTR_VERSION );
        }
        wp_enqueue_script('custom-admin-script', plugin_dir_url(__FILE__) . 'js/custom-admin-script.js', array('jquery'), CDNTR_VERSION, true);
    }


    /**
     * add settings page
     *
     * @since   0.0.1
     * @change  2.0.0
     */

    public static function add_settings_page() {

        add_options_page(
            'CDNTR',
            'CDNTR',
            'manage_options',
            'cdntr',
            array( __CLASS__, 'settings_page' )
        );
    }


    /**
     * check if user can purge cache
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @return  boolean  true if user can purge cache, false otherwise
     */

    private static function user_can_purge_cache() {

        if ( apply_filters( 'cdntr_user_can_purge_cache', current_user_can( 'manage_options' ) ) ) {
            return true;
        }

        if ( apply_filters_deprecated( 'user_can_clear_cache', array( current_user_can( 'manage_options' ) ), '2.0.0', 'cdntr_user_can_purge_cache' ) ) {
            return true;
        }

        return false;
    }


    /**
     * process purge cache request
     *
     * @since   2.0.0
     * @change  2.0.3
     */

    public static function process_purge_cache_request() {

        // check if purge cache request
        if ( empty( $_GET['_cache'] ) || empty( $_GET['_action'] ) || $_GET['_cache'] !== 'cdn' || ( $_GET['_action'] !== 'purge' ) ) {
            return;
        }

        // validate nonce
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cdntr_purge_cache_nonce' ) ) {
            return;
        }

        // check user role
        if ( ! self::user_can_purge_cache() ) {
            return;
        }

        // purge CDN cache
        $response = self::purge_cdn_cache();
        // redirect to same page
        wp_safe_redirect( remove_query_arg( array( '_cache', '_action', '_wpnonce' ) ) );

        // set transient for purge notice
        if ( is_admin() ) {
            set_transient( self::get_cache_purged_transient_name(), $response );
        }

        // purge cache request completed
        exit;
    }


    /**
     * admin notice after cache has been purged
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    public static function cache_purged_notice() {

        // check user role
        if ( ! self::user_can_purge_cache() ) {
            return;
        }

        $response = get_transient( self::get_cache_purged_transient_name() );

        if ( is_array( $response ) ) {
            $allowed_html = array(
                'div' => array(
                    'class' => array(),
                    'id' => array(),
                ),
                'p' => array(),
                'strong' => array(),
            );

            if ( ! empty( $response['subject'] ) ) {
                printf(
                    wp_kses( $response['wrapper'], $allowed_html ),
                    esc_html( $response['subject'] ),
                    esc_html( $response['message'] )
                );
            } else {
                printf(
                    wp_kses( $response['wrapper'], $allowed_html ),
                    esc_html( $response['message'] )
                );
            }

            delete_transient( self::get_cache_purged_transient_name() );
        }
    }


    /**
     * admin notice after configuration has been validated
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    public static function config_validated_notice() {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $response = get_transient( self::get_config_validated_transient_name() );

        if ( is_array( $response ) ) {
            $allowed_html = array(
                'div' => array(
                    'class' => array(),
                    'id' => array(),
                ),
                'p' => array(),
                'strong' => array(),
            );


            printf(
                wp_kses( $response['wrapper'], $allowed_html ),
                esc_html( $response['subject'] ),
                esc_html( $response['message'] )
            );

            delete_transient( self::get_config_validated_transient_name() );
        }
    }


    /**
     * purge CDN cache
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @return  array  $response  API call response
     */

    public static function purge_cdn_cache() {

        // purge CDN cache API call
        $auth = base64_encode( CDNTR_Engine::$settings['cdntr_api_user'] . ':' . CDNTR_Engine::$settings['cdntr_api_password'] );

        $response = wp_remote_post(
            'https://cdn.com.tr/api/purgeAll',
            array(
                'timeout'     => 15,
                'httpversion' => '1.1',
                'headers'     => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type'  => 'application/json'
                ),
            )
        );

        // check if API call failed
        if ( is_wp_error( $response ) ) {
            $response = array(
                'wrapper' => '<div class="notice notice-error is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
                'subject' => esc_html__( 'Purge All CDNTR Cache failed:', 'cdntr' ),
                'message' => $response->get_error_message(),
            );
        // check API call response otherwise
        } else {
            $response_status_code = wp_remote_retrieve_response_code( $response );

            if ( $response_status_code === 200 ) {
                $response = array(
                    'wrapper' => '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
                    'message' => esc_html__( 'CDN cache purged.', 'cdntr' ),
                );
            } elseif ( $response_status_code >= 400 && $response_status_code <= 499 ) {
                $error_messages = array(
                    401 => esc_html__( 'Invalid API key.', 'cdntr' ),
                    403 => esc_html__( 'Invalid Account ID.', 'cdntr' ),
                    429 => esc_html__( 'API rate limit exceeded.', 'cdntr' ),
                    451 => esc_html__( 'Too many failed attempts.', 'cdntr' ),
                );

                if ( array_key_exists( $response_status_code, $error_messages ) ) {
                    $message = $error_messages[ $response_status_code ];
                } else {
                    $message = sprintf(
                        // translators: %s: HTTP status code (e.g. 200)
                        esc_html__( '%s status code returned.', 'cdntr' ),
                        '<code>' . $response_status_code . '</code>'
                    );
                }

                $response = array(
                    'wrapper' => '<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
                    'subject' => esc_html__( 'Purge All CDNTR Cache failed:', 'cdntr' ),
                    'message' => $message,
                );
            } elseif ( $response_status_code >= 500 && $response_status_code <= 599 ) {
                $response = array(
                    'wrapper' => '<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
                    'subject' => esc_html__( 'Purge All CDNTR Cache failed:', 'cdntr' ),
                    'message' => esc_html__( 'API temporarily unavailable.', 'cdntr' ),
                );
            }
        }

        return $response;
    }


    /**
     * check plugin requirements
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    public static function requirements_check() {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // check PHP version
        if ( version_compare( PHP_VERSION, CDNTR_MIN_PHP, '<' ) ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. CDN TR 2. PHP version (e.g. 5.6)
                    esc_html__( '%1$s requires PHP %2$s or higher to function properly. Please update PHP or disable the plugin.', 'cdntr' ),
                    esc_html( '<strong>CDNTR</strong>' ),
                    esc_html( CDNTR_MIN_PHP )
                )
            );
        }

        // check WordPress version
        if ( version_compare( $GLOBALS['wp_version'], CDNTR_MIN_WP . 'alpha', '<' ) ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. CDNTR 2. WordPress version (e.g. 5.1)
                    esc_html__( '%1$s requires WordPress %2$s or higher to function properly. Please update WordPress or disable the plugin.', 'cdntr' ),
                    esc_html( '<strong>CDNTR</strong>' ),
                    esc_html( CDNTR_MIN_PHP )
                )
            );
        }
    }


    /**
     * register textdomain
     *
     * @since   1.0.3
     * @change  1.0.3
     */

    public static function register_textdomain() {

        // load translated strings
        load_plugin_textdomain( 'cdntr', false, 'cdntr/lang' );
    }

    /**
     * @return void
     */
    public static function cdntr_api_check()
    {
        if (!empty(CDNTR_Engine::$settings['cdntr_is_purge_all_button'])) {
            update_option( 'cdntr_api_check_status', 1 );
        }

    }


    public static function check_meta_valid()
    {
        $is_status = get_option( 'cdntr_api_check_status', 0 );
        if ($is_status == 1  && !empty(CDNTR_Engine::$settings['cdntr_api_user'])){
            add_action( 'wp_head', array( __CLASS__, 'add_custom_meta_tags' ) );
        }
    }

    /**
     * @return void
     */
    public static function add_custom_meta_tags() {
        ?>
             <meta name="cdn-site-verification" content="<?php echo esc_attr( CDNTR_Engine::$settings['cdntr_api_user'] ); ?>">';
        <?php
    }


    /**
     * register settings
     *
     * @since   0.0.1
     * @change  0.0.1
     */

    public static function register_settings() {

        register_setting( 'cdntr', 'cdntr', array( __CLASS__, 'validate_settings', ) );
    }


    /**
     * validate CDN hostname
     *
     * @since   2.0.0
     * @change  2.0.4
     *
     * @param   string  $cdn_hostname            CDN hostname
     * @return  string  $validated_cdn_hostname  validated CDN hostname
     */

    private static function validate_cdn_hostname( $cdn_hostname ) {

        $cdn_url = esc_url_raw( trim( $cdn_hostname ), array( 'http', 'https' ) );
        $parsed_url = wp_parse_url( $cdn_url, PHP_URL_HOST );
        $validated_cdn_hostname = strtolower( (string) $parsed_url );

        return $validated_cdn_hostname;
    }


    /**
     * validate textarea
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @param   string   $textarea            textarea
     * @param   boolean  $file_extension      whether or not textarea requires file extension validation
     * @return  string   $validated_textarea  validated textarea
     */

    private static function validate_textarea( $textarea, $file_extension = false ) {

        $textarea = sanitize_textarea_field( $textarea );
        $lines = explode( PHP_EOL, trim( $textarea ) );
        $validated_textarea = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( $line !== '' ) {
                if ( ! $file_extension ) {
                    $validated_textarea[] = $line;
                } elseif ( preg_match( '/^\.\w{1,10}$/', $line ) ) {
                    $validated_textarea[] = $line;
                }
            }
        }

        $validated_textarea = implode( PHP_EOL, $validated_textarea );

        return $validated_textarea;
    }


    /**
     * validate CDNTR Zone ID
     *
     * @since   2.0.0
     * @change  2.0.4
     *
     * @param   string   $zone_id            CDNTR Zone ID
     * @return  integer  $validated_zone_id  validated CDNTR Zone ID
     */

    private static function validate_zone_id( $zone_id ) {

        $zone_id = sanitize_text_field( $zone_id );
        $zone_id = absint( $zone_id );
        $validated_zone_id = ( $zone_id === 0 ) ? '' : $zone_id;

        return $validated_zone_id;
    }


    /**
     * validate configuration
     *
     * @since   2.0.0
     * @change  2.0.4
     *
     * @param   array  $validated_settings  validated settings
     * @return  array  $validated_settings  validated settings
     */

    private static function validate_config( $validated_settings ) {

        if ( empty( $validated_settings['cdn_hostname'] ) ) {
            return $validated_settings;
        }

        // get validation request URL
        CDNTR_Engine::$settings['cdn_hostname'] = $validated_settings['cdn_hostname'];
        CDNTR_Engine::$settings['included_file_extensions'] = '.css';
        $validation_request_url = CDNTR_Engine::rewriter( plugins_url( 'css/settings.min.css', CDNTR_FILE ) );

        // validation request
        $response = wp_remote_get(
            $validation_request_url,
            array(
                'method'      => 'HEAD',
                'timeout'     => 15,
                'httpversion' => '1.1',
                'headers'     => array( 'Referer' => home_url() ),
            )
        );

        // check if validation failed
        if ( is_wp_error( $response ) ) {
            $response = array(
                'wrapper' => '<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
                'subject' => esc_html__( 'Invalid CDN Hostname:', 'cdntr' ),
                'message' => $response->get_error_message(),
            );
        // check validation response otherwise
        } else {
            $response_status_code = wp_remote_retrieve_response_code( $response );

            if ( $response_status_code === 200 ) {
                $response = array(
                    'wrapper' => '<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
                    'subject' => esc_html__( 'Valid CDN Hostname:', 'cdntr' ),
                    'message' => sprintf(
                                     // translators: 1. CDN Hostname (e.g. cdn.example.com) 2. HTTP status code (e.g. 200)
                                     esc_html__( '%1$s returned a %2$s status code.', 'cdntr' ),
                                     '<code>' . $validated_settings['cdn_hostname'] . '</code>',
                                     '<code>' . $response_status_code . '</code>'
                                 ),
                );
            } else {
                $response = array(
                    'wrapper' => '<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
                    'subject' => esc_html__( 'Invalid CDN Hostname:', 'cdntr' ),
                    'message' => sprintf(
                                     // translators: 1. CDN Hostname (e.g. cdn.example.com) 2. HTTP status code (e.g. 200)
                                     esc_html__( '%1$s returned a %2$s status code.', 'cdntr' ),
                                     '<code>' . $validated_settings['cdn_hostname'] . '</code>',
                                     '<code>' . $response_status_code . '</code>'
                                 ),
                );
            }
        }

        // set transient for config validation notice
        set_transient( self::get_config_validated_transient_name(), $response );

        // validate config
        if ( strpos( $response['wrapper'], 'success' ) === false ) {
            $validated_settings['cdn_hostname'] = '';
        }

        return $validated_settings;
    }


    /**
     * validate settings
     *
     * @since   2.0.0
     * @change  2.0.0
     *
     * @param   array  $settings            user defined settings
     * @return  array  $validated_settings  validated settings
     */

    public static function validate_settings( $settings ) {

        $validated_settings = array(
            'cdn_hostname'             => self::validate_cdn_hostname( $settings['cdn_hostname'] ),
            'included_file_extensions' => self::validate_textarea( $settings['included_file_extensions'], true ),
            'excluded_strings'         => self::validate_textarea( $settings['excluded_strings'] ),
            'cdntr_api_password'       => (string) sanitize_text_field( $settings['cdntr_api_password'] ),
            'cdntr_api_user'           => (string) sanitize_text_field( $settings['cdntr_api_user'] ),
            'cdntr_account_expires'    => (string) sanitize_text_field( $settings['cdntr_account_expires'] ),
            'cdntr_is_purge_all_button'=> (string) sanitize_text_field( $settings['cdntr_is_purge_all_button'] ),
            'cdn_hostname_arr'         => (string) sanitize_text_field( $settings['cdn_hostname_arr'] ) ,
        );

        // add default system settings
        $validated_settings = wp_parse_args( $validated_settings, self::get_default_settings( 'system' ) );

        // check if configuration should be validated
        if ( ! empty( $settings['validate_config'] ) ) {
            $validated_settings = self::validate_config( $validated_settings );
        }

        return $validated_settings;
    }

    public static function account_expires_check()
    {
        $expire_date_str = CDNTR_Engine::$settings['cdntr_account_expires'];
        $expire_date = new DateTime($expire_date_str);
        $current_date = new DateTime();
        $date_diff = $current_date->diff($expire_date);
        $days_remaining = $date_diff->days;
        if ($days_remaining < 0) {
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                sprintf(
                // translators: %s: CDNTR
                    esc_html__( 'Paketinizin bitmiştir. %s', 'cdntr' ),
                    '<strong><a href="https://cdn.com.tr/en/management/packages" target="_blank" rel="nofollow noopener">CDNTR</a></strong>'
                )
            );
        } elseif ($days_remaining < 7) {
            // 7 günden az kaldı
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                sprintf(
                // translators: %s: CDNTR
                    esc_html__( 'Paketinizin bitimine 7 günden az süre kalmıştır. %s', 'cdntr' ),
                    '<strong><a href="https://cdn.com.tr/en/management/packages" target="_blank" rel="nofollow noopener">CDNTR</a></strong>'
                )
            );
        }
    }


    /**
     * settings page
     *
     * @since   2.0.0
     * @change  2.0.0
     */

    public static function settings_page() {
        ?>

        <div class="wrap">
            <h1><?php esc_html_e( 'CDNTR Settings', 'cdntr' ); ?></h1>

            <?php

            if ( !empty(CDNTR_Engine::$settings['cdntr_account_expires']) ) {
                self::account_expires_check();
            }

            ?>

            <form method="post" id="cdntr-apiform" action="options.php">
                <?php settings_fields('cdntr') ?>
                <input name="cdntr[cdntr_is_purge_all_button]" type="hidden" id="cdntr_is_purge_all_button" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdntr_is_purge_all_button'] ); ?>"  />
                <input name="cdntr[cdntr_account_expires]" type="hidden" id="cdntr_account_expires" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdntr_account_expires'] ); ?>"  />
                <input name="cdntr[cdn_hostname_arr]" type="hidden" id="cdntr_hostname_arr" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdn_hostname_arr'] ); ?>"  />
                <h2 class="title">CDNTR API</h2>
                <p>
                    <?php
                    printf(
                        '<div><p>%s</p></div>',
                        sprintf(
                        // translators: %s: CDNTR
                            esc_html__( 'To configure CDNTR on your WordPress site, please register at %s. After registration, input your API credentials below to complete the setup.', 'cdntr' ),
                            '<strong><a href="https://cdn.com.tr/en/management/cdn" target="_blank" title="you can click for API info" rel="nofollow noopener">CDNTR</a></strong>'
                        )
                    );
                    //esc_html_e( 'To set up CDNTR on your WordPress website, you need to register at cdn.com.tr. After registering, enter your API details below and proceed with the automatic setup.', 'cdntr' )

                    ?>
                    <div>
                        <a href="<?php echo esc_url( plugin_dir_url(__FILE__) . 'images/cdntr_api_tutorial.jpg' ); ?>" title="more details" class="lightbox" target="_blank">
                            <img src="<?php echo esc_url( plugin_dir_url(__FILE__) . 'images/cdntr_api_tutorial.jpg' ); ?>" alt="My Image" style="width: 200px; height: auto;" />
                        </a>
                    </div>
                </p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'CDNTR API Username', 'cdntr' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cdntr_api_key">
                                    <input name="cdntr[cdntr_api_user]" type="text" id="cdntr_api_user" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdntr_api_user'] ); ?>" class="regular-text code" />
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'CDNTR API Password', 'cdntr' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cdntr_api_password">
                                    <input name="cdntr[cdntr_api_password]" type="password" id="cdntr_api_password" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdntr_api_password'] ); ?>" class="regular-text code" />
                                    <button type="button" id="show-hide-password" class="btn btn-secondary">
                                        <span id="password-text">Show</span>
                                    </button>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <!--
                    <input type="submit" class="button-secondary submit_button" value="<?php esc_html_e( 'Save Changes', 'cdntr' ); ?>" />
                    -->
                    <input name="cdntr[validate_config]" type="submit" class="button-primary" id="apiSendButton" value="<?php esc_html_e( 'Save Changes and Validate Configuration', 'cdntr' ); ?>" />
                </p>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'CDN Hostname', 'cdntr' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <select name="cdntr[cdn_hostname]" id="cdn_hostname_el" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdn_hostname'] ); ?>" >
                                    <option value="0">Select domain</option>
                                    <?php
                                    $is_status = get_option( 'cdntr_api_check_status', 0 );
                                    if($is_status == 1){
                                        if (!empty(CDNTR_Engine::$settings["cdn_hostname_arr"])){
                                            $dataa = json_decode(CDNTR_Engine::$settings["cdn_hostname_arr"],true);
                                            if (!is_array($dataa)) {
                                                $dataa = array();
                                            }
                                            foreach ($dataa as $key => $value) {
                                                $selected = ($value === CDNTR_Engine::$settings['cdn_hostname']) ? 'selected' : '';
                                                echo '<option value="' . esc_attr($value) . '" ' . esc_attr($selected) . '>' . esc_html($value) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <!--
                                <label for="cdntr_cdn_hostname">
                                    <input name="cdntr[cdn_hostname]" type="text" id="cdntr_cdn_hostname" value="<?php echo esc_attr( CDNTR_Engine::$settings['cdn_hostname'] ); ?>" class="regular-text code" />
                                </label>
                                -->
                                <p class="description"><?php esc_html_e( 'This field will be automatically populated after saving if the username and password are provided.', 'cdntr' ) ?></p>

                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'CDN Inclusions', 'cdntr' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <p class="subheading"><?php esc_html_e( 'File Extensions', 'cdntr' ); ?></p>
                                <label for="cdntr_included_file_extensions">
                                    <textarea name="cdntr[included_file_extensions]" type="text" id="cdntr_included_file_extensions" class="regular-text code" rows="5" cols="40"><?php echo esc_textarea( CDNTR_Engine::$settings['included_file_extensions'] ) ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Specify the file extensions to be served via CDN. Enter one extension per line (e.g., .jpg, .png).', 'cdntr' ); ?></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'CDN Exclusions', 'cdntr' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <p class="subheading"><?php esc_html_e( 'Strings', 'cdntr' ); ?></p>
                                <label for="cdntr_excluded_strings">
                                    <textarea name="cdntr[excluded_strings]" type="text" id="cdntr_excluded_strings" class="regular-text code" rows="5" cols="40"><?php echo esc_textarea( CDNTR_Engine::$settings['excluded_strings'] ) ?></textarea>
                                    <p class="description"><?php esc_html_e( 'URLs containing the specified strings will be excluded from CDN delivery. Enter one string per line.', 'cdntr' ) ?></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <!--
                    <input type="submit" class="button-secondary submit_button" value="<?php esc_html_e( 'Save Changes', 'cdntr' ); ?>" />
                    -->
                    <?php
                    $is_status = get_option('cdntr_api_check_status', 0);
                    if ($is_status == 1) {
                        ?>
                        <input name="cdntr[validate_config]" type="submit" class="button-primary" id="serviceDomainSave" value="<?php esc_html_e( 'Save Changes and Validate Configuration', 'cdntr' ); ?>" />
                        <?php
                    }
                    ?>

                </p>
            </form>
        </div>

        <?php

    }
}
