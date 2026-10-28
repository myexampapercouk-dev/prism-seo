<?php
/**
 * Plugin Name:       Prism Works Site Builder
 * Description:       Builds the complete Prism Works Studio website in one click: Home, About us, Services, Sectors and Contact as editable Elementor pages, with a shared header, footer, enquiry form and enquiry inbox.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       prism-works-site-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PWS_SB_VERSION', '1.1.0' );
define( 'PWS_SB_DIR', plugin_dir_path( __FILE__ ) );
define( 'PWS_SB_URL', plugin_dir_url( __FILE__ ) );

/*
 * Safe loading: an incompatible server or an incomplete upload shows a notice instead of breaking the site.
 */
global $wp_version;
if ( version_compare( PHP_VERSION, '7.4', '<' ) || version_compare( $wp_version, '6.0', '<' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Prism Works Site Builder</strong> needs PHP 7.4+ and WordPress 6.0+. The plugin has been paused; your site is unchanged.</p></div>';
	} );
	return;
}
$pws_files = array( 'enquiries', 'contact-form-7', 'setup-gate', 'popups', 'chat', 'seo' );
foreach ( $pws_files as $pws_file ) {
	if ( ! file_exists( PWS_SB_DIR . 'includes/' . $pws_file . '.php' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Prism Works Site Builder</strong> was not uploaded completely, so it has been paused and your site is unchanged. Please upload the zip again.</p></div>';
		} );
		return;
	}
}
foreach ( $pws_files as $pws_file ) {
	require_once PWS_SB_DIR . 'includes/' . $pws_file . '.php';
}

/* -------------------------------------------------------------------------
 * Activation: enable Elementor containers, then create anything missing.
 * Existing pages are never overwritten on activation.
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, 'pws_sb_activate' );
function pws_sb_activate() {
	pws_enquiry_register_type();
	if ( pws_sb_deps_ok() ) {
		pws_sb_finish_setup();
		return;
	}
	// Required plugins are missing: build nothing yet. The popup asks for them first.
	update_option( 'pws_sb_ready', 0 );
	set_transient( 'pws_sb_redirect', 1, 60 );
}

function pws_sb_enable_containers() {
	if ( 'active' !== get_option( 'elementor_experiment-container' ) ) {
		update_option( 'elementor_experiment-container', 'active' );
	}
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */
function pws_sb_json( $file ) {
	$path = PWS_SB_DIR . $file;
	if ( ! file_exists( $path ) ) {
		return array();
	}
	$data = json_decode( file_get_contents( $path ), true );
	return is_array( $data ) ? $data : array();
}

function pws_sb_manifest() {
	return pws_sb_json( 'manifest.json' );
}

function pws_sb_template_defs() {
	return array(
		'header' => array( 'title' => 'Prism Works Header', 'type' => 'type_header' ),
		'footer' => array( 'title' => 'Prism Works Footer', 'type' => 'type_footer' ),
	);
}

function pws_sb_find_template( $key ) {
	$ids = get_posts( array(
		'post_type'      => 'elementor-hf',
		'post_status'    => 'any',
		'meta_key'       => '_pws_template',
		'meta_value'     => $key,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Read a page/template JSON file and fill in the site specific values.
 */
function pws_sb_content( $file, $tpl_ids ) {
	$path = PWS_SB_DIR . $file;
	if ( ! file_exists( $path ) ) {
		return array();
	}
	$raw = file_get_contents( $path );
	$map = array(
		'{{HOME}}'   => untrailingslashit( home_url() ),
		'{{ASSETS}}' => untrailingslashit( PWS_SB_URL . 'assets' ),
	);
	foreach ( $tpl_ids as $key => $id ) {
		$map[ '{{TPL:' . $key . '}}' ] = (string) (int) $id;
	}
	// Values are inserted inside JSON strings; escape them the JSON way.
	foreach ( $map as $token => $value ) {
		$raw = str_replace( $token, substr( wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 1, -1 ), $raw );
	}
	$doc = json_decode( $raw, true );
	return ( is_array( $doc ) && isset( $doc['content'] ) ) ? $doc['content'] : array();
}

function pws_sb_save_elementor( $post_id, $content, $type ) {
	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_template_type', $type );
	update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.25.0' );
	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
	delete_post_meta( $post_id, '_elementor_css' );
	delete_post_meta( $post_id, '_elementor_element_cache' );
}

/** Keep a copy of a page's or template's Elementor content so a reset can be undone. */
function pws_sb_backup( $post_id ) {
	$d = get_post_meta( $post_id, '_elementor_data', true );
	if ( $d ) {
		update_post_meta( $post_id, '_pws_backup_data', wp_slash( $d ) );
		update_post_meta( $post_id, '_pws_backup_time', time() );
	}
}

function pws_sb_backup_posts() {
	return get_posts( array(
		'post_type'      => array( 'page', 'elementor-hf' ),
		'post_status'    => 'any',
		'meta_key'       => '_pws_backup_data',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
}

/** Put every backed-up page and template back the way it was before the last reset. */
function pws_sb_restore_backup() {
	$n = 0;
	foreach ( pws_sb_backup_posts() as $id ) {
		$d = get_post_meta( $id, '_pws_backup_data', true );
		if ( $d ) {
			update_post_meta( $id, '_elementor_data', wp_slash( $d ) );
			delete_post_meta( $id, '_elementor_css' );
			delete_post_meta( $id, '_elementor_element_cache' );
			delete_post_meta( $id, '_pws_backup_data' );
			delete_post_meta( $id, '_pws_backup_time' );
			$n++;
		}
	}
	pws_sb_clear_elementor_cache();
	return $n;
}

function pws_sb_clear_elementor_cache() {
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}

/* -------------------------------------------------------------------------
 * Import: header/footer templates, then the pages.
 * ---------------------------------------------------------------------- */
function pws_sb_import_templates( $overwrite ) {
	$ids = array();
	foreach ( pws_sb_template_defs() as $key => $def ) {
		$id = pws_sb_find_template( $key );
		if ( $id && ! $overwrite ) {
			$ids[ $key ] = $id;
			continue;
		}
		// Header and footer are Ultimate Addons for Elementor (UAE) templates shown on every page.
		$postarr = array(
			'post_type'   => 'elementor-hf',
			'post_status' => 'publish',
			'post_title'  => $def['title'],
		);
		if ( $id ) {
			pws_sb_backup( $id );
			$postarr['ID'] = $id;
			wp_update_post( $postarr );
		} else {
			$id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
		}
		$ids[ $key ] = (int) $id;
		update_post_meta( $id, '_pws_template', $key );
		update_post_meta( $id, 'ehf_template_type', $def['type'] );
		update_post_meta( $id, 'ehf_target_include_locations', array( 'rule' => array( 0 => 'basic-global' ), 'specific' => array() ) );
		update_post_meta( $id, 'ehf_target_exclude_locations', '' );
		update_post_meta( $id, 'ehf_target_user_roles', array( 'all' ) );
		update_post_meta( $id, 'display-on-canvas-template', '1' );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		pws_sb_save_elementor( $id, pws_sb_content( 'templates/' . $key . '.json', array() ), 'wp-post' );
	}
	return $ids;
}

/**
 * Create (or, with $overwrite, reset) every page in the manifest.
 *
 * @return array { created:int, updated:int, skipped:int }
 */
function pws_sb_run_import( $overwrite = false ) {
	$out     = array( 'created' => 0, 'updated' => 0, 'skipped' => 0 );
	$tpl_ids = pws_sb_import_templates( $overwrite );

	if ( ! get_option( 'site_icon' ) ) {
		$icon = pws_sb_sideload_icon();
		if ( $icon ) {
			update_option( 'site_icon', $icon );
		}
	}

	$front_id = 0;
	foreach ( pws_sb_manifest() as $item ) {
		$existing = get_page_by_path( $item['slug'], OBJECT, 'page' );
		if ( $existing && ! $overwrite ) {
			$out['skipped']++;
			if ( ! empty( $item['front'] ) ) {
				$front_id = (int) $existing->ID;
			}
			continue;
		}
		$postarr = array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => $item['title'],
			'post_name'      => $item['slug'],
			'post_content'   => '',
			'comment_status' => 'closed',
		);
		if ( $existing ) {
			pws_sb_backup( $existing->ID );
			// WordPress rejects Elementor's page templates on update while they are not registered yet.
			delete_post_meta( $existing->ID, '_wp_page_template' );
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( $postarr, true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$post_id = $existing->ID;
			}
			$out['updated']++;
		} else {
			$post_id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			$out['created']++;
		}

		update_post_meta( $post_id, '_pws_page', $item['slug'] );
		update_post_meta( $post_id, '_pws_doc_title', isset( $item['doc_title'] ) ? $item['doc_title'] : '' );
		update_post_meta( $post_id, '_pws_description', isset( $item['description'] ) ? $item['description'] : '' );
		update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
		pws_sb_save_elementor( $post_id, pws_sb_content( 'pages/' . $item['file'], $tpl_ids ), 'wp-page' );

		if ( ! empty( $item['front'] ) ) {
			$front_id = (int) $post_id;
		}
	}

	// "Page not found" is an ordinary Elementor page, shown for 404s by the 404page plugin.
	$nf = get_page_by_path( 'page-not-found', OBJECT, 'page' );
	if ( $nf ) {
		$o            = get_option( '404page_settings' );
		$o            = is_array( $o ) ? $o : array();
		$o['page_id'] = (int) $nf->ID;
		update_option( '404page_settings', $o );
	}

	if ( $front_id && ( $overwrite || 'page' !== get_option( 'show_on_front' ) ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );
	}
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
	}
	flush_rewrite_rules();
	pws_sb_clear_elementor_cache();
	pws_cf7_ensure_form( $overwrite );
	pws_cf7_ensure_popup_form( $overwrite );
	pws_cf7_ensure_chat_form( $overwrite );
	pws_popups_ensure( $overwrite );

	if ( $overwrite ) {
		update_option( 'pws_sb_content_hash', pws_sb_content_hash() );
	}
	update_option( 'pws_sb_last_import', time() );
	update_option( 'pws_sb_db_version', PWS_SB_VERSION );
	return $out;
}

/** Upload the bundled icon to the media library so it can be used as the site icon (favicon). */
function pws_sb_sideload_icon() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$src = PWS_SB_DIR . 'assets/img/site-icon.png';
	$tmp = wp_tempnam( 'site-icon.png' );
	if ( ! file_exists( $src ) || ! $tmp || ! copy( $src, $tmp ) ) {
		return 0;
	}
	$id = media_handle_sideload( array( 'name' => 'prism-works-site-icon.png', 'tmp_name' => $tmp ), 0, 'Prism Works' );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	return (int) $id;
}

/* -------------------------------------------------------------------------
 * [pws_template id="123"]: prints a saved Elementor template (header/footer).
 * ---------------------------------------------------------------------- */
add_shortcode( 'pws_template', function ( $atts ) {
	$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'pws_template' );
	$id = absint( $a['id'] );
	if ( ! $id || ! class_exists( '\Elementor\Plugin' ) || 'publish' !== get_post_status( $id ) ) {
		return '';
	}
	static $depth = 0;
	if ( $depth > 2 ) {
		return '';
	}
	$depth++;
	$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, true );
	$depth--;
	return $html;
} );

/* -------------------------------------------------------------------------
 * Front end: is the current page one of ours?
 * ---------------------------------------------------------------------- */
function pws_sb_is_ours() {
	static $ours = null;
	if ( null !== $ours ) {
		return $ours;
	}
	$id = 0;
	if ( is_singular() ) {
		$id = get_queried_object_id();
	} elseif ( is_404() ) {
		// The 404page plugin shows our "Page not found" page, which is not a singular request.
		$o  = get_option( '404page_settings' );
		$id = is_array( $o ) && ! empty( $o['page_id'] ) ? absint( $o['page_id'] ) : 0;
	}
	// Elementor editor preview of a page or template.
	if ( isset( $_GET['elementor-preview'] ) ) {
		$id = absint( $_GET['elementor-preview'] );
	}
	$ours = $id && '' !== (string) get_post_meta( $id, '_pws_page', true );
	return $ours;
}

add_filter( 'body_class', function ( $classes ) {
	if ( pws_sb_is_ours() ) {
		$classes[] = 'pws';
	}
	return $classes;
} );

add_action( 'template_redirect', function () {
	if ( ! pws_sb_is_ours() ) {
		return;
	}
	// Text exactly as written (no curly quotes) and no emoji images.
	add_filter( 'run_wptexturize', '__return_false' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
} );

add_filter( 'pre_get_document_title', function ( $title ) {
	if ( pws_sb_is_ours() && is_singular() ) {
		$t = get_post_meta( get_queried_object_id(), '_pws_doc_title', true );
		if ( $t ) {
			return $t;
		}
	}
	return $title;
}, 20 );

add_action( 'wp_head', function () {
	if ( ! pws_sb_is_ours() || ! is_singular() ) {
		return;
	}
	$d = get_post_meta( get_queried_object_id(), '_pws_description', true );
	if ( $d ) {
		echo '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
	}
}, 1 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! pws_sb_is_ours() ) {
		return;
	}

	// The theme's own styles would change the design; these pages bring their own.
	$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );
	foreach ( wp_styles()->queue as $handle ) {
		$src = isset( wp_styles()->registered[ $handle ] ) ? (string) wp_styles()->registered[ $handle ]->src : '';
		foreach ( $theme_urls as $u ) {
			if ( $src && 0 === strpos( $src, $u ) ) {
				wp_dequeue_style( $handle );
			}
		}
	}
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );

	$ver = function ( $rel ) {
		$f = PWS_SB_DIR . $rel;
		return file_exists( $f ) ? (string) filemtime( $f ) : PWS_SB_VERSION;
	};

	wp_enqueue_style( 'pws-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Readex+Pro:wght@400;600&family=Lato:wght@400;700&family=Poppins:wght@400;500;600&display=swap', array(), null );
	wp_enqueue_style( 'pws', PWS_SB_URL . 'assets/css/prism-works.css', array(), $ver( 'assets/css/prism-works.css' ) );
	wp_enqueue_script( 'pws', PWS_SB_URL . 'assets/js/prism-works.js', array(), $ver( 'assets/js/prism-works.js' ), true );
	wp_localize_script( 'pws', 'PWS', array(
		'home' => home_url( '/' ),
		'ajax' => admin_url( 'admin-ajax.php' ),
	) );
}, 999 );

/* -------------------------------------------------------------------------
 * Upgrades and admin screen
 * ---------------------------------------------------------------------- */
/**
 * A fingerprint of everything the zip builds on the site: pages, header, footer, forms and popups.
 * Design files (CSS/JS) are not part of it, so they update on their own without touching the site content.
 */
function pws_sb_content_hash() {
	$files = array_merge(
		(array) glob( PWS_SB_DIR . 'pages/*.json' ),
		(array) glob( PWS_SB_DIR . 'templates/*.json' ),
		array( PWS_SB_DIR . 'manifest.json', PWS_SB_DIR . 'includes/contact-form-7.php', PWS_SB_DIR . 'includes/popups.php' )
	);
	sort( $files );
	$h = PWS_SB_VERSION;
	foreach ( $files as $f ) {
		$h .= file_exists( $f ) ? md5_file( $f ) : '';
	}
	return md5( $h );
}

/**
 * Uploading a newer zip never changes your pages. If the zip's content differs from what the site was built
 * with, a notice says so; nothing is reset unless someone presses "Reset all pages to original design"
 * (which backs up first, and can be undone).
 */
add_action( 'admin_notices', function () {
	if ( ! pws_sb_is_ready() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( get_option( 'pws_sb_content_hash' ) === pws_sb_content_hash() ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>Prism Works Site Builder</strong> has newer page content than your site. <strong>Nothing has been changed.</strong> '
		. 'To use the new content, press <em>Reset all pages to original design</em> on <a href="' . esc_url( admin_url( 'admin.php?page=pws-site' ) ) . '">the Prism Works screen</a> (your current pages are backed up first and can be restored). ';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	wp_nonce_field( 'pws_sb_action' );
	echo '<input type="hidden" name="action" value="pws_sb_keep"><button class="button-link">Keep my pages and hide this</button></form></p></div>';
} );

add_action( 'admin_post_pws_sb_keep', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_sb_action' );
	update_option( 'pws_sb_content_hash', pws_sb_content_hash() );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=pws-site' ) );
	exit;
} );

add_action( 'admin_menu', function () {
	add_menu_page( 'Prism Works', 'Prism Works', 'manage_options', 'pws-site', 'pws_sb_admin_page', 'dashicons-art', 58 );
	add_submenu_page( 'pws-site', 'Pages', 'Pages', 'manage_options', 'pws-site', 'pws_sb_admin_page' );
} );

add_action( 'admin_post_pws_sb_import', function () {
	pws_sb_handle_import( false );
} );
add_action( 'admin_post_pws_sb_reset', function () {
	pws_sb_handle_import( true );
} );
add_action( 'admin_post_pws_sb_undo', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_sb_action' );
	$n = pws_sb_restore_backup();
	wp_safe_redirect( add_query_arg( array( 'page' => 'pws-site', 'pws_undone' => $n ), admin_url( 'admin.php' ) ) );
	exit;
} );
function pws_sb_handle_import( $overwrite ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_sb_action' );
	pws_sb_enable_containers();
	$r = pws_sb_run_import( $overwrite );
	wp_safe_redirect( add_query_arg( array(
		'page' => 'pws-site',
		'c'    => $r['created'],
		'u'    => $r['updated'],
		's'    => $r['skipped'],
	), admin_url( 'admin.php' ) ) );
	exit;
}

add_action( 'admin_post_pws_sb_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_sb_settings' );
	$email = isset( $_POST['pws_email'] ) ? sanitize_email( wp_unslash( $_POST['pws_email'] ) ) : '';
	update_option( 'pws_enquiry_email', $email );
	wp_safe_redirect( add_query_arg( array( 'page' => 'pws-site', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function pws_sb_admin_page() {
	$elementor = defined( 'ELEMENTOR_VERSION' );
	echo '<div class="wrap"><h1>Prism Works Site Builder</h1>';
	if ( ! pws_sb_is_ready() ) {
		echo '<div class="notice notice-warning"><p><strong>Setup is not finished.</strong> Activate Elementor and Contact Form 7 in the popup, then click <em>Activate Prism Works Site Builder</em>. No pages are created until then.</p></div>';
	}
	if ( isset( $_GET['pws_undone'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Undone: ' . (int) $_GET['pws_undone'] . ' pages and templates were put back the way they were before the last reset.</p></div>';
	}
	if ( isset( $_GET['pws_done'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>All set. The pages, header, footer and Contact Form 7 form have been created.</p></div>';
	}

	if ( isset( $_GET['c'] ) ) {
		printf( '<div class="notice notice-success is-dismissible"><p>Done: %d created, %d reset, %d left untouched.</p></div>', (int) $_GET['c'], (int) $_GET['u'], (int) $_GET['s'] );
	}
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
	}
	if ( ! $elementor ) {
		echo '<div class="notice notice-warning"><p><strong>Elementor is not active.</strong> The pages are saved but stay empty until you install and activate the free <em>Elementor</em> plugin (Plugins &rarr; Add New). The <em>Hello Elementor</em> theme is recommended.</p></div>';
	}

	echo '<p>Every page is an Elementor page. Activating the plugin already created the pages that did not exist yet and set <em>Home</em> as the front page.</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:12px">';
	wp_nonce_field( 'pws_sb_action' );
	echo '<input type="hidden" name="action" value="pws_sb_import"><button class="button button-primary">Create missing pages</button></form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block" onsubmit="return confirm(\'This puts ALL Prism Works pages, the header and the footer back to the original design. Your Elementor edits are backed up first, and you can use Undo last reset. Continue?\');">';
	wp_nonce_field( 'pws_sb_action' );
	echo '<input type="hidden" name="action" value="pws_sb_reset"><button class="button">Reset all pages to original design</button></form>';

	if ( pws_sb_backup_posts() ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-left:12px">';
		wp_nonce_field( 'pws_sb_action' );
		echo '<input type="hidden" name="action" value="pws_sb_undo"><button class="button">Undo last reset</button></form>';
	}

	echo '<table class="widefat striped" style="margin-top:20px;max-width:920px"><thead><tr><th>Page</th><th>URL</th><th></th></tr></thead><tbody>';
	foreach ( pws_sb_manifest() as $r ) {
		$p = get_page_by_path( $r['slug'], OBJECT, 'page' );
		echo '<tr><td>' . esc_html( $r['title'] ) . '</td><td><code>/' . ( 'home' === $r['slug'] ? '' : esc_html( $r['slug'] ) . '/' ) . '</code></td><td>';
		if ( $p ) {
			echo '<a href="' . esc_url( get_permalink( $p ) ) . '" target="_blank">View</a>';
			if ( $elementor ) {
				echo ' &middot; <a href="' . esc_url( admin_url( 'post.php?post=' . $p->ID . '&action=elementor' ) ) . '">Edit with Elementor</a>';
			}
		} else {
			echo 'Missing';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2 style="margin-top:28px">Header and footer</h2><p>Shared by every page: edit once, every page updates.</p><table class="widefat striped" style="max-width:920px"><tbody>';
	foreach ( pws_sb_template_defs() as $key => $d ) {
		$tid = pws_sb_find_template( $key );
		echo '<tr><td>' . esc_html( $d['title'] ) . '</td><td>';
		if ( $tid && $elementor ) {
			echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $tid . '&action=elementor' ) ) . '">Edit with Elementor</a>';
		} else {
			echo $tid ? 'Ready' : 'Missing';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	pws_cf7_admin_box();
	pws_chat_admin_box();
	echo '<h2 style="margin-top:28px">Popup (Popup Maker)</h2>';
	echo '<p>The &ldquo;Book a Free Discovery Call&rdquo; popup is made with <strong>Popup Maker</strong>' . ( post_type_exists( 'popup' ) ? ' &mdash; <a href="' . esc_url( admin_url( 'edit.php?post_type=popup' ) ) . '">edit the popups</a>' : '' ) . '. It opens by itself a few seconds after the home page loads and from every button that points to the Contact page.</p>';

	$email = get_option( 'pws_enquiry_email', '' );
	echo '<h2 style="margin-top:28px">Enquiries</h2>';
	echo '<p>Messages from the Contact page are saved under <a href="' . esc_url( admin_url( 'edit.php?post_type=pws_enquiry' ) ) . '">Prism Works &rarr; Enquiries</a> and emailed to the address below.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'pws_sb_settings' );
	echo '<input type="hidden" name="action" value="pws_sb_settings"><input type="email" name="pws_email" class="regular-text" placeholder="' . esc_attr( pws_enquiry_default_email() ) . '" value="' . esc_attr( $email ) . '"> <button class="button">Save</button>';
	echo '<p class="description">Leave empty to use the site admin email (' . esc_html( pws_enquiry_default_email() ) . ').</p></form></div>';
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=pws-site' ) ) . '">Pages</a>' );
	return $links;
} );
