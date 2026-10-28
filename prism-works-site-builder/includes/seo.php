<?php
/**
 * SEO for the pages this plugin builds: social tags, structured data, and keeping the "Page not found" page
 * out of search. Titles and meta descriptions come from manifest.json (see the main plugin file).
 * Everything here only runs on our own pages, so other content and SEO plugins are left alone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Business facts used in structured data. Keep them identical to the footer and contact page. */
function pws_seo_org() {
	return array(
		'name'      => 'Prism Works Studio',
		'email'     => 'contact@prismworks.studio',
		'telephone' => '+441902937021',
		'logo'      => PWS_SB_URL . 'assets/img/logo.png',
		'area'      => 'United Kingdom',
	);
}

/** True when a dedicated SEO plugin is active, so we don't print the same tags twice. */
function pws_seo_other_plugin() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/** The manifest entry for the current page, or null. */
function pws_seo_item( $post_id ) {
	$slug = (string) get_post_meta( $post_id, '_pws_page', true );
	if ( '' === $slug ) {
		return null;
	}
	foreach ( pws_sb_manifest() as $item ) {
		if ( $item['slug'] === $slug ) {
			return $item;
		}
	}
	return null;
}

// The "Page not found" page must never be indexed or listed in the sitemap.
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_404() || ( is_singular() && 'page-not-found' === get_post_meta( get_queried_object_id(), '_pws_page', true ) ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
} );

add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$nf = get_page_by_path( 'page-not-found', OBJECT, 'page' );
		if ( $nf ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), array( (int) $nf->ID ) );
		}
	}
	return $args;
}, 10, 2 );

// Author archives and user lists give away login names and add thin pages.
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );

add_action( 'wp_head', function () {
	if ( pws_seo_other_plugin() || ! pws_sb_is_ours() || ! is_singular() ) {
		return;
	}
	$id   = get_queried_object_id();
	$item = pws_seo_item( $id );
	if ( ! $item || 'page-not-found' === $item['slug'] ) {
		return;
	}
	$org   = pws_seo_org();
	$url   = get_permalink( $id );
	$title = ! empty( $item['doc_title'] ) ? $item['doc_title'] : get_the_title( $id );
	$desc  = isset( $item['description'] ) ? $item['description'] : '';
	$image = ! empty( $item['image'] ) ? PWS_SB_URL . 'assets/img/' . $item['image'] : $org['logo'];

	// Social sharing previews (WhatsApp, LinkedIn, Facebook, X).
	$og = array(
		'og:type'        => ! empty( $item['front'] ) ? 'website' : 'article',
		'og:site_name'   => $org['name'],
		'og:locale'      => 'en_GB',
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => $url,
		'og:image'       => $image,
	);
	foreach ( $og as $k => $v ) {
		if ( '' !== $v ) {
			echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n";
		}
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	// Structured data: the business on every page, plus the page itself, breadcrumbs and any FAQs.
	$home  = home_url( '/' );
	$graph = array(
		array(
			'@type'      => 'ProfessionalService',
			'@id'        => $home . '#organization',
			'name'       => $org['name'],
			'url'        => $home,
			'logo'       => $org['logo'],
			'image'      => $org['logo'],
			'email'      => $org['email'],
			'telephone'  => $org['telephone'],
			'areaServed' => array( '@type' => 'Country', 'name' => $org['area'] ),
		),
		array(
			'@type'       => 'WebPage',
			'@id'         => $url . '#webpage',
			'url'         => $url,
			'name'        => $title,
			'description' => $desc,
			'inLanguage'  => 'en-GB',
			'isPartOf'    => array( '@type' => 'WebSite', 'name' => $org['name'], 'url' => $home ),
			'about'       => array( '@id' => $home . '#organization' ),
		),
	);
	if ( empty( $item['front'] ) ) {
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => get_the_title( $id ), 'item' => $url ),
			),
		);
	}
	// Optional "service": {"name": "...", "type": "..."} in manifest.json marks a service page.
	if ( ! empty( $item['service'] ) ) {
		$graph[] = array(
			'@type'       => 'Service',
			'name'        => $item['service']['name'],
			'serviceType' => isset( $item['service']['type'] ) ? $item['service']['type'] : $item['service']['name'],
			'description' => $desc,
			'url'         => $url,
			'provider'    => array( '@id' => $home . '#organization' ),
			'areaServed'  => ! empty( $item['service']['area'] ) ? array( '@type' => 'City', 'name' => $item['service']['area'] ) : array( '@type' => 'Country', 'name' => $org['area'] ),
		);
	}
	// Optional "faqs": [["question", "answer"], ...] in manifest.json. They must also be visible on the page.
	if ( ! empty( $item['faqs'] ) ) {
		$graph[] = array(
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( function ( $f ) {
				return array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) );
			}, $item['faqs'] ),
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 2 );
