<?php
/**
 * Contact Form 7 integration. Every form on the site is a Contact Form 7 form.
 *
 *  - "Prism Works Contact": the full form on the Contact page ([pws_contact_form]).
 *  - "Prism Works Popup":   the compact form inside the Popup Maker popup (no message field, so it never scrolls).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pws_cf7_active() {
	return class_exists( 'WPCF7_ContactForm' );
}

/**
 * Form code for the Contact page (Form tab of Contact Form 7).
 * One element per line, so Contact Form 7 does not wrap the markup in extra <p> tags.
 */
function pws_cf7_form_code() {
	return '<div class="row">
<div><label for="pws-name">Name *</label>[text* your-name id:pws-name autocomplete:name placeholder "Your name"]</div>
<div><label for="pws-email">Email *</label>[email* your-email id:pws-email autocomplete:email placeholder "you@company.co.uk"]</div>
</div>
<div class="row">
<div><label for="pws-phone">Phone Number *</label>[tel* your-phone id:pws-phone autocomplete:tel placeholder "Your phone number"]</div>
<div><label for="pws-product">Please select your product *</label>[select* your-product id:pws-product first_as_label "- Please select -" "Web Designing" "Web Development" "Brand Strategy" "Digital Marketing"]</div>
</div>
<div><label for="pws-message">What would you like us to add?</label>[textarea your-message id:pws-message rows:4 placeholder "Tell us about your business and goals"]</div>
<div>[submit class:pws-btn class:pws-btn-primary "Submit"]</div>';
}

/** Compact form code for the popup. Different field ids, so it can sit on the same page as the full form. */
function pws_cf7_popup_form_code() {
	return '<div class="row">
<div><label for="pwp-name">Name *</label>[text* your-name id:pwp-name autocomplete:name placeholder "Your name"]</div>
<div><label for="pwp-email">Email *</label>[email* your-email id:pwp-email autocomplete:email placeholder "you@company.co.uk"]</div>
</div>
<div class="row">
<div><label for="pwp-phone">Phone Number *</label>[tel* your-phone id:pwp-phone autocomplete:tel placeholder "Your phone number"]</div>
<div><label for="pwp-product">Please select your product *</label>[select* your-product id:pwp-product first_as_label "- Please select -" "Web Designing" "Web Development" "Brand Strategy" "Digital Marketing"]</div>
</div>
<div>[submit class:pws-btn class:pws-btn-primary "Book my free call"]</div>';
}

/** "Talk to a human" form inside the chat window. The hidden field carries the chat so far. */
function pws_cf7_chat_form_code() {
	return '<div><label for="pwc-name">Name *</label>[text* your-name id:pwc-name autocomplete:name placeholder "Your name"]</div>
<div><label for="pwc-email">Email *</label>[email* your-email id:pwc-email autocomplete:email placeholder "Your email"]</div>
<div><label for="pwc-message">How can we help? *</label>[textarea* your-message id:pwc-message rows:3 placeholder "Your message"]</div>
<div>[hidden chat-transcript][submit class:pws-btn class:pws-btn-primary "Send to our team"]</div>';
}

function pws_cf7_domain() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', (string) $host );
	return $host ? $host : 'example.com';
}

/** The Mail tab settings shared by both forms. */
function pws_cf7_mail_settings() {
	$to = get_option( 'pws_enquiry_email' );
	return array(
		'active'             => true,
		'recipient'          => is_email( $to ) ? $to : '[_site_admin_email]',
		'sender'             => '[_site_title] <wordpress@' . pws_cf7_domain() . '>',
		'subject'            => '[_site_title] New enquiry: [your-product]',
		'body'               => "Name: [your-name]\nEmail: [your-email]\nPhone: [your-phone]\nProduct: [your-product]\n\nMessage:\n[your-message]\n\n-- \nSent from [_url]",
		'additional_headers' => 'Reply-To: [your-name] <[your-email]>',
		'attachments'        => '',
		'use_html'           => false,
		'exclude_blank'      => false,
	);
}

/**
 * Create one Contact Form 7 form once. An existing form is only rewritten when $reset is true.
 * Returns the form id, or 0 when Contact Form 7 is not available.
 */
function pws_cf7_ensure( $option, $title, $code, $reset = false ) {
	if ( ! pws_cf7_active() ) {
		return 0;
	}
	$id = (int) get_option( $option );
	if ( $id && 'wpcf7_contact_form' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) && ! $reset ) {
		return $id;
	}
	$form = ( $id && 'wpcf7_contact_form' === get_post_type( $id ) ) ? WPCF7_ContactForm::get_instance( $id ) : WPCF7_ContactForm::get_template();
	if ( ! $form ) {
		return 0;
	}
	$form->set_title( $title );
	$form->set_properties( array(
		'form' => $code,
		'mail' => pws_cf7_mail_settings(),
	) );
	$id = (int) $form->save();
	if ( $id ) {
		update_option( $option, $id );
	}
	return $id;
}

function pws_cf7_ensure_chat_form( $reset = false ) {
	if ( ! pws_cf7_active() ) {
		return 0;
	}
	$id = (int) get_option( 'pws_cf7_chat_id' );
	if ( $id && 'wpcf7_contact_form' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) && ! $reset ) {
		return $id;
	}
	$id = pws_cf7_ensure( 'pws_cf7_chat_id', 'Prism Works Chat', pws_cf7_chat_form_code(), true );
	if ( $id ) {
		$form = WPCF7_ContactForm::get_instance( $id );
		$mail = pws_cf7_mail_settings();
		$mail['subject'] = '[_site_title] Live chat message from [your-name]';
		$mail['body']    = "Live chat message\n\nName: [your-name]\nEmail: [your-email]\n\nMessage:\n[your-message]\n\n--- Chat so far ---\n[chat-transcript]\n\n-- \nSent from [_url]";
		$form->set_properties( array( 'mail' => $mail ) );
		$form->save();
	}
	return $id;
}

function pws_cf7_ensure_form( $reset = false ) {
	return pws_cf7_ensure( 'pws_cf7_id', 'Prism Works Contact', pws_cf7_form_code(), $reset );
}

function pws_cf7_ensure_popup_form( $reset = false ) {
	return pws_cf7_ensure( 'pws_cf7_popup_id', 'Prism Works Popup', pws_cf7_popup_form_code(), $reset );
}

/** The Contact page form. */
function pws_sb_contact_form() {
	$id = pws_cf7_ensure_form();
	if ( $id ) {
		return '<div class="pws-form pws-form-cf7">' . do_shortcode( '[contact-form-7 id="' . (int) $id . '" title="Prism Works Contact"]' ) . '</div>';
	}
	return '<div class="pws-form"><p class="pws-note">The contact form needs the Contact Form 7 plugin. Please activate it.</p></div>';
}

add_shortcode( 'pws_contact_form', 'pws_sb_contact_form' );

/** The Contact page keeps the token in an Elementor HTML widget; swap it for the form when rendering. */
add_filter( 'elementor/widget/render_content', function ( $content ) {
	if ( false !== strpos( $content, '[pws_contact_form]' ) ) {
		$content = str_replace( '[pws_contact_form]', pws_sb_contact_form(), $content );
	}
	return $content;
}, 10, 1 );

/** Create the forms as soon as Contact Form 7 is active and setup is finished. */
add_action( 'admin_init', function () {
	if ( pws_cf7_active() && function_exists( 'pws_sb_is_ready' ) && pws_sb_is_ready() ) {
		if ( ! get_option( 'pws_cf7_id' ) ) {
			pws_cf7_ensure_form();
		}
		if ( ! get_option( 'pws_cf7_popup_id' ) ) {
			pws_cf7_ensure_popup_form();
		}
		if ( ! get_option( 'pws_cf7_chat_id' ) ) {
			pws_cf7_ensure_chat_form();
		}
	}
} );

/** Keep a copy of every submission of either form in the Prism Works inbox. */
add_action( 'wpcf7_mail_sent', function ( $form ) {
	$ours = array( (int) get_option( 'pws_cf7_id' ), (int) get_option( 'pws_cf7_popup_id' ), (int) get_option( 'pws_cf7_chat_id' ) );
	if ( ! in_array( (int) $form->id(), $ours, true ) || ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}
	$sub = WPCF7_Submission::get_instance();
	if ( ! $sub ) {
		return;
	}
	$d    = $sub->get_posted_data();
	$get  = function ( $k ) use ( $d ) {
		return isset( $d[ $k ] ) ? ( is_array( $d[ $k ] ) ? implode( ', ', $d[ $k ] ) : (string) $d[ $k ] ) : '';
	};
	$is_chat = (int) $form->id() === (int) get_option( 'pws_cf7_chat_id' );
	$data = array(
		'name'    => sanitize_text_field( $get( 'your-name' ) ),
		'email'   => sanitize_email( $get( 'your-email' ) ),
		'phone'   => sanitize_text_field( $get( 'your-phone' ) ),
		'service' => $is_chat ? 'Live chat' : sanitize_text_field( $get( 'your-product' ) ),
		'message' => sanitize_textarea_field( $get( 'your-message' ) . ( $is_chat && $get( 'chat-transcript' ) ? "\n\n--- Chat so far ---\n" . $get( 'chat-transcript' ) : '' ) ),
		'page'    => esc_url_raw( (string) $sub->get_meta( 'url' ) ),
	);
	$id   = wp_insert_post( array(
		'post_type'   => 'pws_enquiry',
		'post_status' => 'private',
		'post_title'  => $data['name'] . ( $data['service'] ? ' – ' . $data['service'] : '' ),
	) );
	if ( $id && ! is_wp_error( $id ) ) {
		foreach ( $data as $k => $v ) {
			update_post_meta( $id, '_pws_' . $k, $v );
		}
	}
} );

/** Admin box: status and the form code. */
function pws_cf7_admin_box() {
	echo '<h2 style="margin-top:28px">Forms (Contact Form 7)</h2>';
	if ( pws_cf7_active() ) {
		$id   = (int) get_option( 'pws_cf7_id' );
		$pid  = (int) get_option( 'pws_cf7_popup_id' );
		$edit = function ( $i ) {
			return $i ? ' (<a href="' . esc_url( admin_url( 'admin.php?page=wpcf7&post=' . $i . '&action=edit' ) ) . '">edit</a>)' : '';
		};
		echo '<p>Every form on the site is a Contact Form 7 form: <strong>Prism Works Contact</strong>' . $edit( $id ) . ' on the Contact page, <strong>Prism Works Popup</strong>' . $edit( $pid ) . ' in the popup and <strong>Prism Works Chat</strong>' . $edit( (int) get_option( 'pws_cf7_chat_id' ) ) . ' in the chat window. Submissions are emailed and also saved under Enquiries.</p>';
	} else {
		echo '<p>Contact Form 7 is not active. Activate it to show the forms.</p>';
	}
	echo '<p class="description">Form code for the Contact page (already applied automatically):</p>';
	echo '<textarea readonly rows="12" class="large-text code" style="max-width:920px" onclick="this.select()">' . esc_textarea( pws_cf7_form_code() ) . '</textarea>';
}
