<?php
/*
Plugin Name: Woocommerce HRK to EUR converter
Plugin URI: https://github.com/ikovacic/woocommerce-hrk-eur-converter
Description: Plugin for change the currency in woocommerce from HRK to EUR
Version: 1.0
WC tested up to: 7.2
Author URI: https://github.com/ikovacic/woocommerce-hrk-eur-converter
*/

if ( !defined('ABSPATH') ) { 
    die;
}

if ( !is_admin() ) return;

class Hrk2eur {

    protected $plugin_name;
    private $notices;
    protected $config;

    public function __construct() {

		$this->plugin_name = 'hrk2eur';
        $this->notices = array();
        $this->config = array(
            'fixed_rate' => 7.53450,
            'precision' => 2,
        );

        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_hrk2eur_tab' ), 150 );
        add_action( 'woocommerce_settings_hrk2eur_admin_tab', array( $this, 'hrk2eur_output_settings' ));
        add_action( 'woocommerce_settings_save_hrk2eur_admin_tab', array( $this, 'hrk2eur_save' ));
        add_action( 'admin_notices', array( $this, 'hrk2eur_notices' ));
	}

    public function get_plugin_name() {
		return $this->plugin_name;
	}

    public static function add_hrk2eur_tab( $tabs ) {
        $tabs['hrk2eur_admin_tab'] = __( 'HRK=>EUR', 'woocommerce' );
        return $tabs;
    }

    public function hrk2eur_output_settings() {
        $settings = array(
            'section_title' => array(
                'name'  => __( 'Promjena valute trgovine iz HRK u EUR', 'woocommerce' ),
                'type'  => 'title',
                'desc'  => 'Klikom na gumb napravit ćete sljedeće promjene: Zadana valuta trgovine bit će EUR, a sve cijene proizvoda preračunat će se u EUR po fiksnom tečaju 7,53450. Također će se napraviti konverzija u dostavnim tarifama i kuponima, povijesti promjene cijena, te će se regenerirati lookup tablice. Ove promjene su trajne i NE MOGU se poništiti, pa se iz tog razloga strogo preporuča napraviti backup baze podataka prije pokretanja procesa! Nakon odrađene konverzije deaktivirajte i deinstalirajte plugin Hrk2Eur.',
                'class' => 'hrk2eur-options-title',
                'id'    => 'hrk2eur_admin_tab_section_general_settings_title'
            ), 
            'hrk2eur_conversion' => array(
                'name'    => __( 'Napravi konverziju', 'woocommerce' ),
                'type'    => 'checkbox',
                'default' => 'no',
                'desc'    => __( 'Označite box i spremite promjene da biste pokrenuli konverziju', 'woocommerce' ),
                'id'      => 'hrk2eur_conversion',
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id'   => 'hrk2eur_admin_tab_general_settings_end',
            ),
        );
        WC_Admin_Settings::output_fields( $settings );
    }

    function hrk2eur_save() {
        if (isset($_POST['hrk2eur_conversion'])) {
            $hrk2eur_conversion = sanitize_text_field($_POST['hrk2eur_conversion']);
            if($hrk2eur_conversion) {
                $this->applause_convert_currency();
            }
        }   
    }

    function applause_hrk_to_eur( $value ) {

        $config = $this->config;
    
        return number_format( $value / $config['fixed_rate'], $config['precision'] );
    
    }

    // 01. Convert WooCommerce Settings ( HRK > EUR )

    function applause_convert_options() {

        global $wpdb;

        if( $wpdb->query( 
            $wpdb->prepare( 
                "UPDATE {$wpdb->prefix}options SET option_value = %s WHERE option_name = %s",
                'EUR',
                'woocommerce_currency'
            )
        )) {
            $this->notices[] = array( 'message' => __( 'Uspješno ažurirana valuta shopa', 'woocommerce' ), 'type' => 'success' );
        } else {
            $this->notices[] = array( 'message' => __( 'Nije uspjelo ažuriranje zadane valute shopa!', 'woocommerce' ), 'type' => 'error' );
        }
    }

    // 02. Convert WooCommerce Shipping

    function applause_convert_delivery() {

        global $wpdb;

        $keys = array();

        // get all shipping methods
        $results = $wpdb->get_results( "SELECT instance_id, method_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods", ARRAY_A );

        // prepare option_names for shipping methods
        foreach( $results as $result ) {
            $keys[] = 'woocommerce_' . $result['method_id'] . '_' . $result['instance_id'] . '_settings';
        }

        // get settings for shipment methods
        $results = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_name IN ('" . implode("','", $keys) . "')", ARRAY_A );

        $success = false;
        foreach( $results as $result ) {

            $shipping_settings = maybe_unserialize($result['option_value']);

            // free_shipping
            if( !empty( $shipping_settings['min_amount'] ) ) {
                $shipping_settings['min_amount'] = $this->applause_hrk_to_eur( $shipping_settings['min_amount'] );
            }

            // local_pickup, flat_rate
            if( !empty( $shipping_settings['cost'] ) ) {
                $shipping_settings['cost'] = $this->applause_hrk_to_eur( $shipping_settings['cost'] ); 
            }

            if( update_option( $result['option_name'], $shipping_settings ) ) $success = true;
        }

        if($success){
            $this->notices[] = array( 'message' => __( 'Uspješno ažurirane cijene dostave (prag za besplatnu dostavu i cijene dostave za svaku opciju)', 'woocommerce' ), 'type' => 'success' ); 
        } else {
            $this->notices[] = array( 'message' => __( 'Nisu sve opcije dostave uspješno ažurirane!', 'woocommerce' ), 'type' => 'warning' );
        }

    }

    // 03. Convert WooCommerce Coupons

    function applause_convert_coupons() {

        global $wpdb;
        $config = $this->config;

        // convert min / max amounts on all coupons
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}postmeta SET meta_value = ROUND((meta_value/%f), %d) WHERE meta_key IN ('minimum_amount', 'maximum_amount')",
                $config['fixed_rate'],
                $config['precision']
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}postmeta m1
                LEFT JOIN {$wpdb->prefix}postmeta m2
                ON m1.post_id = m2.post_id
                AND m2.meta_key = 'coupon_amount'
                SET m2.meta_value = ROUND((m2.meta_value/%f), %d)
                WHERE m1.meta_key = 'discount_type'
                AND m1.meta_value IN ('fixed_cart', 'fixed_product')",
                $config['fixed_rate'],
                $config['precision']
            )
        );
        
        $this->notices[] = array( 'message' => __( 'Uspješno ažurirani kuponi', 'woocommerce' ), 'type' => 'success' ); 
        
    }

    // 04. Convert WooCommerce product prices (including price history)

    function applause_convert_products() {

        global $wpdb;
        $config = $this->config;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}postmeta
                SET meta_value = ROUND((meta_value/%f), %d)
                WHERE meta_key IN ('_lowest_price_30_days', '_price', '_sale_price', '_regular_price', '_min_variation_price', '_max_variation_price', '_min_variation_regular_price', '_max_variation_regular_price', '_min_variation_sale_price', '_max_variation_sale_price')
                AND meta_value != ''",
                $config['fixed_rate'],
                $config['precision']
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}price_history
                SET price = ROUND((price/%f), %d)",
                $config['fixed_rate'],
                $config['precision']
            )
        );

        $this->notices[] = array( 'message' => __( 'Cijene proizvoda uspješno ažurirane', 'woocommerce' ), 'type' => 'success' ); 

    }

    // 05. Recreate WooCommerce product lookup tables

    function applause_lookup_tables() {

        if ( function_exists( 'wc_update_product_lookup_tables_column' ) ) {
            wc_update_product_lookup_tables_column( 'min_max_price' );
        }

        $this->notices[] = array( 'message' => __( 'Rekreiranje lookup tablica dovršeno', 'woocommerce' ), 'type' => 'success' );

    }

    // 06. Delete cached prices

    function applause_delete_transients() {

        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_wc_var_prices_%' OR option_name LIKE '_transient_timeout_wc_var_prices_%'"
        );

        $this->notices[] = array( 'message' => __( 'Obrisani transienti', 'woocommerce' ), 'type' => 'success' ); 

    }

    function applause_convert_currency() {

        $try = 'applause_currency';
        $status = 'completed';
    
        if ( get_option( $try ) != $status ) {
    
            $this->applause_convert_options();
            $this->applause_convert_delivery();
            $this->applause_convert_coupons();
            $this->applause_convert_products();
            $this->applause_lookup_tables();
            $this->applause_delete_transients();
    
            // Clear cache (WP Super Cache only)
            if ( function_exists( 'wp_cache_clean_cache' ) ) {
                global $file_prefix;
                wp_cache_clean_cache( $file_prefix, true );
            }
    
            update_option( $try, $status );
        }
    }

    function hrk2eur_notices(){        
        foreach ($this->notices as $notice) {
            echo '<div class="notice notice-' .esc_html($notice['type']). '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }

}

$hrk2eur_plugin = new Hrk2eur();