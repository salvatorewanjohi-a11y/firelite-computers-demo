<?php
/**
 * Builds the Firelite Computers store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Products come from products.json (next to this file), photos from /wordpress/flc-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized (900px webp), so skip making thumbnails of each one.
// The theme reads this option and serves the photos as they are. (Real hosting can regenerate thumbnails later.)
update_option( 'flc_fast_images', 'yes' );
add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
add_filter( 'big_image_size_threshold', '__return_false' );
add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
add_filter( 'woocommerce_resize_images', '__return_false' );

// Store settings.
foreach ( [
	'blogname'                               => 'Firelite Computers',
	'blogdescription'                        => 'Laptops, desktops & UPS at wholesale prices, Moi Avenue, Nairobi',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'Central Building, 1st Floor, Shop No. 10, Moi Avenue',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '2',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_enable_coupons'             => 'yes',
	// Spec filters query the attribute terms directly, so they work the moment a product is saved.
	'woocommerce_attribute_lookup_enabled'   => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Categories (menu order = order in the header and on the home page).
$cat = [];
$i   = 0;
foreach ( [
	'business-laptops'     => [ 'Business Laptops', 'Ex-UK and boxed HP EliteBook, ProBook, Lenovo ThinkPad, Dell Latitude, Surface and MacBook laptops. Every Ex-UK machine is tested in our shop, and most take a RAM or SSD upgrade.' ],
	'everyday-laptops'     => [ 'Student & Everyday Laptops', 'Brand-new boxed HP 14, HP 15, Pavilion and Asus laptops, plus Chromebooks and budget machines for school, home and office.' ],
	'gaming-workstations'  => [ 'Gaming & Workstations', 'HP Omen and Victus gaming laptops, ZBook mobile workstations, MacBook Pro and Envy creator machines with dedicated graphics.' ],
	'desktop-pcs'          => [ 'Desktop PCs & All-in-Ones', 'Business desktops, towers and all-in-one PCs for offices, cyber cafés, school labs and gamers. Boxed and Ex-UK.' ],
	'ups-power'            => [ 'UPS & Power Backup', 'APC and Cursor UPS units to ride out power cuts, plus laptop chargers and power banks.' ],
	'monitors-accessories' => [ 'Monitors & Accessories', 'Edge-to-edge and gaming monitors, keyboards, mice, bags, hubs, adapters, storage and networking.' ],
	'printers-pos'         => [ 'Printers, POS & Projectors', 'HP printers, receipt printers, point-of-sale terminals, projectors and laminators for shops and offices.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Spec filters on the shop pages: processor, RAM, storage, screen size, condition and use case.
function flc_filter_attribute( $label, $slug ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
		wc_create_attribute( [ 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'name', 'has_archives' => false ] );
	}
	$tax = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy( $tax, [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ] );
	}
	return $tax;
}
$filters = [
	'cpu'       => flc_filter_attribute( 'Processor', 'cpu' ),
	'ram'       => flc_filter_attribute( 'RAM', 'ram' ),
	'storage'   => flc_filter_attribute( 'Storage', 'storage' ),
	'screen'    => flc_filter_attribute( 'Screen size', 'screen' ),
	'condition' => flc_filter_attribute( 'Condition', 'condition' ),
	'use'       => flc_filter_attribute( 'Best for', 'use' ),
];
delete_transient( 'wc_attribute_taxonomies' );

// Delivery. PLACEHOLDER prices: to confirm with Firelite.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
// Free delivery first, so it is picked by default once the cart qualifies.
$id = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$id}_settings", [ 'title' => 'Free Nairobi CBD express delivery', 'requires' => 'min_amount', 'min_amount' => '20000' ] );
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Express same-day Nairobi dispatch', 'cost' => '300', 'tax_status' => 'none' ] );

$rest = new WC_Shipping_Zone();
$rest->set_zone_name( 'Rest of Kenya' );
$rest->add_location( 'KE', 'country' );
$rest->save();
$id = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Upcountry parcel courier (1–2 days)', 'cost' => '500', 'tax_status' => 'none' ] );

update_option( 'woocommerce_pickup_location_settings', [ 'enabled' => 'yes', 'title' => 'In-store pickup & testing at Moi Avenue (free)', 'tax_status' => 'none', 'cost' => '' ] );
update_option( 'pickup_location_pickup_locations', [ [
	'name'    => 'Firelite Computers – Central Building, Moi Avenue',
	'address' => [ 'address_1' => 'Central Building, 1st Floor, Shop No. 10, Moi Avenue (opposite Sidian Bank, next to Digital Shopping Mall)', 'city' => 'Nairobi CBD', 'state' => 'KE30', 'postcode' => '', 'country' => 'KE' ],
	'details' => 'Monday–Saturday, 8:00 AM – 7:30 PM. We will call you when your order is ready, and you can switch it on and test it before you pay.',
	'enabled' => true,
] ] );

// Payment: pay on delivery or at pickup until an M-Pesa till is connected.
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay on delivery or pickup (M-Pesa or cash)',
	'description'        => 'Pay by M-Pesa or cash when your order arrives, or when you collect and test it at Central Building, Moi Avenue.',
	'instructions'       => 'We will call you to confirm your order, the delivery arrangement and the time.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );

// Products: picked from the Firelite WhatsApp Business catalogue by catalogue/build.mjs.
$products = json_decode( file_get_contents( __DIR__ . '/products.json' ), true );

// The photos are web-sized webp files made by catalogue/build.mjs, so they are copied straight into
// uploads and registered with their size. (media_handle_sideload processes every image, which made
// the demo slow to build.)
function flc_attach_images( $slug, $name ) {
	$files = glob( "/wordpress/flc-images/$slug*.webp" ) ?: [];
	$files = array_values( array_filter( $files, fn( $f ) => preg_match( '#/' . preg_quote( $slug, '#' ) . '(-\d+)?\.webp$#', $f ) ) );
	// slug.webp is the main photo, then slug-2.webp, slug-3.webp…
	$num = fn( $f ) => preg_match( '#-(\d+)\.webp$#', substr( $f, strlen( $slug ) ), $m ) ? (int) $m[1] : 1;
	usort( $files, fn( $a, $b ) => $num( basename( $a ) ) <=> $num( basename( $b ) ) );
	$up  = wp_upload_dir();
	$ids = [];
	foreach ( $files as $i => $file ) {
		$base = basename( $file );
		$dest = trailingslashit( $up['path'] ) . $base;
		if ( ! copy( $file, $dest ) ) {
			continue;
		}
		$size = @getimagesize( $dest ) ?: [ 900, 900 ];
		$att  = wp_insert_attachment( [
			'post_mime_type' => 'image/webp',
			'post_title'     => $name . ( $i ? ' – photo ' . ( $i + 1 ) : '' ),
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $up['url'] ) . $base,
		], $dest, 0, false, false );
		if ( ! $att ) {
			continue;
		}
		wp_update_attachment_metadata( $att, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
		$ids[] = $att;
	}
	return $ids;
}

// Count terms once at the end, and run the import as one transaction: SQLite otherwise commits
// (and syncs to disk) after every query.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );
$started = microtime( true );

$by_slug = [];
foreach ( $products as $order => $d ) {
	$variable = ! empty( $d['variations'] );
	$imgs     = flc_attach_images( $d['slug'], $d['name'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( wp_kses_post( $d['desc'] ) );
	$p->set_short_description( wp_kses_post( $d['short'] ) );
	$p->set_category_ids( array_map( fn( $s ) => $cat[ $s ], $d['cat'] ) );
	$p->set_featured( ! empty( $d['featured'] ) );
	$p->update_meta_data( '_flc_spec', $d['badge'] );
	$p->update_meta_data( '_flc_specs', $d['specs'] );
	$p->update_meta_data( '_flc_kind', $d['kind'] );
	$p->update_meta_data( '_flc_cond', $d['cond'] );
	$p->update_meta_data( '_flc_ram_steps', $d['ram'] ? implode( ',', $d['ram'] ) : '' );
	$p->update_meta_data( '_flc_ssd_steps', $d['ssd'] ? implode( ',', $d['ssd'] ) : '' );

	$attributes = [];
	// Options the customer picks (configuration, condition…).
	foreach ( $d['attributes'] ?? [] as $pos => $a ) {
		$attr = new WC_Product_Attribute();
		$attr->set_name( $a['name'] );
		$attr->set_options( $a['options'] );
		$attr->set_position( $pos );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$attributes[] = $attr;
	}
	// Spec filter values. A machine sold in several configurations is listed under each of them.
	foreach ( $d['f'] as $key => $values ) {
		$tax      = $filters[ $key ];
		$term_ids = [];
		foreach ( (array) $values as $value ) {
			$t = term_exists( $value, $tax ) ?: wp_insert_term( $value, $tax );
			if ( ! is_wp_error( $t ) ) $term_ids[] = (int) $t['term_id'];
		}
		$attr = new WC_Product_Attribute();
		$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
		$attr->set_name( $tax );
		$attr->set_options( $term_ids );
		$attr->set_visible( false );
		$attr->set_variation( false );
		$attributes[] = $attr;
	}
	$p->set_attributes( $attributes );

	if ( ! $variable ) {
		$p->set_manage_stock( true );
		$p->set_stock_quantity( 5 ); // PLACEHOLDER stock.
		$p->set_regular_price( $d['regular'] );
		if ( ! empty( $d['sale'] ) ) $p->set_sale_price( $d['sale'] );
	}
	if ( $imgs ) {
		$p->set_image_id( $imgs[0] );
		$p->set_gallery_image_ids( array_slice( $imgs, 1 ) );
	}
	$pid = $p->save();

	if ( $variable ) {
		foreach ( $d['variations'] as $n => $v ) {
			$var = new WC_Product_Variation();
			$var->set_parent_id( $pid );
			$var->set_menu_order( $n );
			$var->set_attributes( array_combine( array_map( 'sanitize_title', array_keys( $v['attrs'] ) ), array_values( $v['attrs'] ) ) );
			$var->set_regular_price( $v['regular'] );
			if ( $v['price'] < $v['regular'] ) $var->set_sale_price( $v['price'] );
			$var->set_stock_status( 'instock' );
			if ( $v['img'] && isset( $imgs[ $v['img'] ] ) ) $var->set_image_id( $imgs[ $v['img'] ] );
			$var->save();
		}
		WC_Product_Variable::sync( $pid );
	}

	if ( $d['brand'] && taxonomy_exists( 'product_brand' ) ) {
		wp_set_object_terms( $pid, $d['brand'], 'product_brand' );
	}

	$by_slug[ $d['slug'] ] = $pid;
}

// "Complete your setup" suggestions.
foreach ( $products as $d ) {
	if ( empty( $d['upsells'] ) ) continue;
	// Written as meta directly: a full product save per product here doubled the import time.
	update_post_meta( $by_slug[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $by_slug[ $s ] ?? 0, $d['upsells'] ) ) ) );
}

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
update_option( 'flc_setup_seconds', round( microtime( true ) - $started, 1 ) );

// Pages.
function flc_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
flc_page( 'wishlist', 'Your wishlist', "<!-- wp:shortcode -->\n[flc_wishlist]\n<!-- /wp:shortcode -->" );
flc_page( 'wholesale', 'Wholesale & bulk orders', "<!-- wp:shortcode -->\n[flc_wholesale]\n<!-- /wp:shortcode -->" );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );
