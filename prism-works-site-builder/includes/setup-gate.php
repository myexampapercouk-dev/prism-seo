<?php
/**
 * Setup gate.
 *
 * The site is only built once the required plugins (Elementor, Contact Form 7) are active.
 * Until then an admin popup lists them with an "Install & activate" button for each, and a final
 * "Activate Prism Works Site Builder" button appears once both are ready.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pws_sb_required_plugins() {
	return array(
		'elementor'        => array(
			'name'   => 'Elementor',
			'file'   => 'elementor/elementor.php',
			'why'    => 'Builds and edits every page.',
			'active' => defined( 'ELEMENTOR_VERSION' ),
		),
		'header-footer-elementor' => array(
			'name'   => 'Ultimate Addons for Elementor (UAE)',
			'file'   => 'header-footer-elementor/header-footer-elementor.php',
			'why'    => 'Shows the Prism Works header and footer on every page.',
			'active' => defined( 'HFE_VER' ),
		),
		'popup-maker'      => array(
			'name'   => 'Popup Maker',
			'file'   => 'popup-maker/popup-maker.php',
			'why'    => 'Shows the free-consultation popup.',
			'active' => function_exists( 'pum_get_default_theme_id' ),
		),
		'404page'          => array(
			'name'   => '404page',
			'file'   => '404page/404page.php',
			'why'    => 'Shows the branded &ldquo;Page not found&rdquo; page.',
			'active' => function_exists( 'pp_404page' ),
		),
		'contact-form-7'   => array(
			'name'   => 'Contact Form 7',
			'file'   => 'contact-form-7/wp-contact-form-7.php',
			'why'    => 'Powers the Contact page form.',
			'active' => class_exists( 'WPCF7_ContactForm' ),
		),
	);
}

function pws_sb_deps_ok() {
	foreach ( pws_sb_required_plugins() as $p ) {
		if ( ! $p['active'] ) {
			return false;
		}
	}
	return true;
}

function pws_sb_is_ready() {
	return (bool) get_option( 'pws_sb_ready' );
}

/** Install (if missing) and activate one plugin from wordpress.org. */
function pws_sb_install_dep( $slug ) {
	$all = pws_sb_required_plugins();
	if ( ! isset( $all[ $slug ] ) ) {
		return false;
	}
	if ( $all[ $slug ]['active'] ) {
		return true;
	}
	if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
		return false;
	}
	// Large plugins (Elementor is ~23 MB) can take a while to download and unpack on slow hosts.
	@set_time_limit( 300 ); // phpcs:ignore
	wp_raise_memory_limit( 'admin' );
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$file = $all[ $slug ]['file'];
	if ( ! isset( get_plugins()[ $file ] ) ) {
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
			return false;
		}
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );
		if ( ! $result || is_wp_error( $result ) ) {
			return false;
		}
	}
	return ! is_wp_error( activate_plugin( $file ) );
}

/** Build everything: pages, header/footer, Contact Form 7 form. Called once the required plugins are active. */
function pws_sb_finish_setup() {
	pws_sb_enable_containers();
	pws_enquiry_register_type();
	pws_sb_run_import( false );
	pws_cf7_ensure_form();
	pws_cf7_ensure_popup_form();
	pws_cf7_ensure_chat_form();
	pws_popups_ensure();
	update_option( 'pws_sb_content_hash', pws_sb_content_hash() );
	update_option( 'pws_sb_ready', 1 );
}

add_action( 'admin_post_pws_sb_dep', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_sb_gate' );
	$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
	$ok   = pws_sb_install_dep( $slug );
	wp_safe_redirect( add_query_arg( 'pws_dep', $ok ? 'ok' : 'fail', wp_get_referer() ? wp_get_referer() : admin_url( 'plugins.php' ) ) );
	exit;
} );

add_action( 'admin_post_pws_sb_finish', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_sb_gate' );
	if ( pws_sb_deps_ok() ) {
		pws_sb_finish_setup();
		wp_safe_redirect( admin_url( 'admin.php?page=pws-site&pws_done=1' ) );
	} else {
		wp_safe_redirect( admin_url( 'plugins.php' ) );
	}
	exit;
} );

/**
 * While setup is unfinished, plugins that redirect to their own welcome screen after activation
 * (Elementor, UAE...) send the user back to the setup popup instead.
 */
add_filter( 'wp_redirect', function ( $location ) {
	if ( pws_sb_is_ready() || ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
		return $location;
	}
	if ( preg_match( '~/wp-admin/(?:admin|index)\.php\?(?:[^#]*&)?page=(?!pws-site)~', (string) $location ) ) {
		return admin_url( 'admin.php?page=pws-site' );
	}
	return $location;
}, 1 );

/** The popup. */
add_action( 'admin_footer', function () {
	if ( pws_sb_is_ready() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$deps   = pws_sb_required_plugins();
	$ready  = pws_sb_deps_ok();
	$action = esc_url( admin_url( 'admin-post.php' ) );
	$failed = isset( $_GET['pws_dep'] ) && 'fail' === $_GET['pws_dep'];
	?>
	<div id="pws-gate" role="dialog" aria-modal="true" aria-labelledby="pws-gate-title">
		<style>
			#pws-gate{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(4,6,10,.82);backdrop-filter:blur(4px);font-family:-apple-system,"Segoe UI",Roboto,sans-serif}
			#pws-gate .pws-box{position:relative;width:100%;max-width:560px;padding:34px 32px 28px;border:1px solid rgba(179,255,58,.35);border-radius:14px;background:#0d1217;color:#fff;box-shadow:0 30px 90px rgba(0,0,0,.6)}
			#pws-gate .pws-box:before{content:"";position:absolute;left:0;right:0;top:0;height:4px;border-radius:14px 14px 0 0;background:#b3ff3a}
			#pws-gate h2{margin:0 0 8px;font-size:22px;line-height:1.25;color:#fff}
			#pws-gate p{margin:0 0 18px;color:rgba(255,255,255,.72);font-size:14px;line-height:1.6}
			#pws-gate .pws-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;margin-bottom:10px;border:1px solid rgba(255,255,255,.1);border-radius:10px;background:#080b0f}
			#pws-gate .pws-row b{display:block;font-size:15px;color:#fff}
			#pws-gate .pws-row span{font-size:12.5px;color:rgba(255,255,255,.6)}
			#pws-gate .pws-ok{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:rgba(179,255,58,.14);color:#b3ff3a;font-size:12px;font-weight:700;white-space:nowrap}
			#pws-gate button{cursor:pointer;border:0;border-radius:8px;padding:11px 18px;font-size:13.5px;font-weight:700;background:#b3ff3a;color:#000;white-space:nowrap}
			#pws-gate button:hover{filter:brightness(1.06)}
			#pws-gate .pws-final{width:100%;margin-top:8px;padding:14px 18px;font-size:15px}
			#pws-gate .pws-final[disabled]{background:#1d252e;color:rgba(255,255,255,.4);cursor:not-allowed}
			#pws-gate .pws-err{margin:0 0 14px;padding:10px 14px;border:1px solid #fda4af;border-radius:8px;color:#fda4af;font-size:13px}
			#pws-gate .pws-x{position:absolute;right:12px;top:14px;width:32px;height:32px;padding:0;background:transparent;color:rgba(255,255,255,.6);font-size:22px;line-height:1}
			#pws-gate .pws-step{margin:18px 0 8px;font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#b3ff3a}
		</style>
		<div class="pws-box">
			<button type="button" class="pws-x" aria-label="Close" onclick="document.getElementById('pws-gate').remove()">&times;</button>
			<h2 id="pws-gate-title">Activate the required plugins first</h2>
			<p>Prism Works Site Builder needs these plugins. Activate them, then activate Prism Works Site Builder to create your pages.</p>
			<?php if ( $failed ) : ?>
				<div class="pws-err">That plugin could not be installed automatically. Install it from Plugins &rarr; Add New, then come back.</div>
			<?php endif; ?>
			<div class="pws-step">Step 1 &middot; Required plugins</div>
			<?php foreach ( $deps as $slug => $d ) : ?>
				<div class="pws-row">
					<div><b><?php echo esc_html( $d['name'] ); ?></b><span><?php echo esc_html( $d['why'] ); ?></span></div>
					<?php if ( $d['active'] ) : ?>
						<span class="pws-ok">&#10003; Active</span>
					<?php else : ?>
						<form method="post" action="<?php echo $action; ?>">
							<?php wp_nonce_field( 'pws_sb_gate' ); ?>
							<input type="hidden" name="action" value="pws_sb_dep">
							<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>">
							<button type="submit">Install &amp; activate</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<div class="pws-step">Step 2 &middot; Prism Works Site Builder</div>
			<form method="post" action="<?php echo $action; ?>">
				<?php wp_nonce_field( 'pws_sb_gate' ); ?>
				<input type="hidden" name="action" value="pws_sb_finish">
				<button type="submit" class="pws-final" <?php disabled( ! $ready ); ?>>Activate Prism Works Site Builder</button>
			</form>
		</div>
	</div>
	<?php
} );

/** After activating the plugin with missing dependencies, send the user to the popup. */
add_action( 'admin_init', function () {
	if ( get_transient( 'pws_sb_redirect' ) && ! pws_sb_is_ready() && current_user_can( 'activate_plugins' ) ) {
		delete_transient( 'pws_sb_redirect' );
		if ( ! wp_doing_ajax() && ! isset( $_GET['activate-multi'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pws-site' ) );
			exit;
		}
	}
} );
