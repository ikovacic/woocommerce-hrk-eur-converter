<?php

require_once('../wp-load.php');

$config = array(
    'fixed_rate' => 7.53450,
    'precision' => 2,
);


function applause_hrk_to_eur( $value ) {

    global $config;

    return number_format( $value / $config['fixed_rate'], $config['precision'] );

}

// 01. Convert WooCommerce Settings ( HRK > EUR )

function applause_convert_options() {

    global $wpdb;

    $wpdb->query( 
        $wpdb->prepare( 
            "UPDATE {$wpdb->prefix}options SET option_value = %s WHERE option_name = %s",
            'EUR',
            'woocommerce_currency'
        )
    );

    echo "<div>Uspješno ažurirana valuta shopa</div>";

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

    foreach( $results as $result ) {

        $shipping_settings = maybe_unserialize($result['option_value']);

        // free_shipping
        if( !empty( $shipping_settings['min_amount'] ) ) {
            $shipping_settings['min_amount'] = applause_hrk_to_eur( $shipping_settings['min_amount'] );
        }

        // local_pickup, flat_rate
        if( !empty( $shipping_settings['cost'] ) ) {
            $shipping_settings['cost'] = applause_hrk_to_eur( $shipping_settings['cost'] );
        }

        update_option( $result['option_name'], $shipping_settings );
    }

    echo "<div>Uspješno ažurirane cijene dostave (prag za besplatnu dostavu i cijene dostave za svaku opciju)</div>";
}

// 03. Convert WooCommerce Coupons

function applause_convert_coupons() {

    global $wpdb;
    global $config;

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


    echo "<div>Uspješno ažurirani kuponi</div>";
}

// 04. Convert WooCommerce product prices (including price history)

function applause_convert_products() {

    global $wpdb;
    global $config;

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}postmeta
            SET meta_value = ROUND((meta_value/%f), %d)
            WHERE meta_key IN ('_lowest_price_30_days', '_price', '_sale_price', '_regular_price', '_cost')
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

    echo "<div>Cijene proizvoda uspješno ažurirane</div>";
}

// 05. Recreate WooCommerce product lookup tables

function applause_lookup_tables() {

    if ( function_exists( 'wc_update_product_lookup_tables_column' ) ) {
        wc_update_product_lookup_tables_column( 'min_max_price' );
    }
    echo "<div>Rekreiranje lookup tablica dovršeno</div>";
}

function applause_convert_currency() {

    $try = 'applause_currency';
    $status = 'completed';

    if ( get_option( $try ) != $status ) {

        applause_convert_options();
        applause_convert_delivery();
        applause_convert_coupons();
        applause_convert_products();
        applause_lookup_tables();

        // Clear cache (WP Super Cache only)
        if ( function_exists( 'wp_cache_clean_cache' ) ) {
            global $file_prefix;
            wp_cache_clean_cache( $file_prefix, true );
        }

        update_option( $try, $status );
    }
}

applause_convert_currency();
