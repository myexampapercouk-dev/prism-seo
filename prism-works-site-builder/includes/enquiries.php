<?php
/**
 * Enquiries inbox: a private copy of everything sent through the Contact Form 7 forms.
 * (The forms themselves are Contact Form 7; this is only the list under Prism Works > Enquiries.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Where Contact Form 7 emails go unless one is set on the Prism Works screen.
function pws_enquiry_default_email() {
	return get_option( 'admin_email' );
}

function pws_enquiry_register_type() {
	register_post_type( 'pws_enquiry', array(
		'labels'          => array(
			'name'          => 'Enquiries',
			'singular_name' => 'Enquiry',
			'all_items'     => 'Enquiries',
			'edit_item'     => 'Enquiry',
			'search_items'  => 'Search enquiries',
			'not_found'     => 'No enquiries yet.',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'pws-site',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
}
add_action( 'init', 'pws_enquiry_register_type' );

function pws_enquiry_fields() {
	return array(
		'name'    => 'Name',
		'email'   => 'Email',
		'phone'   => 'Phone',
		'service' => 'Interested in',
		'message' => 'Message',
		'page'    => 'Sent from',
	);
}

/* Admin: list columns and the detail box. */
add_filter( 'manage_pws_enquiry_posts_columns', function () {
	return array(
		'cb'          => '<input type="checkbox">',
		'title'       => 'Name / interest',
		'pws_email'   => 'Email',
		'pws_phone'   => 'Phone',
		'pws_service' => 'Interested in',
		'date'        => 'Received',
	);
} );
add_action( 'manage_pws_enquiry_posts_custom_column', function ( $col, $id ) {
	$map = array( 'pws_email' => '_pws_email', 'pws_phone' => '_pws_phone', 'pws_service' => '_pws_service' );
	if ( isset( $map[ $col ] ) ) {
		$v = get_post_meta( $id, $map[ $col ], true );
		echo 'pws_email' === $col ? '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>' : esc_html( $v );
	}
}, 10, 2 );

add_action( 'add_meta_boxes_pws_enquiry', function () {
	add_meta_box( 'pws_enquiry_details', 'Message', function ( $post ) {
		echo '<table class="form-table"><tbody>';
		foreach ( pws_enquiry_fields() as $k => $label ) {
			$v = get_post_meta( $post->ID, '_pws_' . $k, true );
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $v ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'pws_enquiry', 'normal', 'high' );
} );
