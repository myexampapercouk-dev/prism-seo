<?php
/**
 * Popups are made with Popup Maker (a ready-made plugin); this file only sets them up.
 *
 *  - "Prism Works Welcome Popup": opens by itself a few seconds after the home page loads (once per visit)
 *    and from every button that points to the Contact page.
 *  - "Prism Works Contact Popup": the same popup on every other page, opened by those buttons.
 *
 * Both contain the compact Contact Form 7 form, laid out so the popup never needs a scrollbar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pws_popup_maker_active() {
	return post_type_exists( 'popup' ) && function_exists( 'pum_get_default_theme_id' );
}

function pws_popup_content() {
	$id = pws_cf7_ensure_popup_form();
	$form = $id
		? '<div class="pws-form pws-form-cf7">[contact-form-7 id="' . (int) $id . '" title="Prism Works Popup"]</div>'
		: '<p>Please activate Contact Form 7.</p>';
	return '<div class="pws-pop-head"><span class="pws-eyebrow">Free consultation</span><h2>Book a Free Discovery Call</h2>'
		. '<p>Tell us about your business. A member of our team will respond promptly.</p></div>' . $form;
}

/** CSS selector for buttons that point to the Contact page. */
function pws_popup_click_selector() {
	return 'a.pws-btn[href$="/contact/"], a.pws-btn[href$="/contact"]';
}

function pws_popup_settings( $id, $welcome ) {
	$cookie   = 'pum-' . $id;
	$triggers = array();
	if ( $welcome ) {
		$triggers[] = array(
			'type'     => 'auto_open',
			'settings' => array( 'cookie_name' => array( $cookie ), 'delay' => 4000 ),
		);
	}
	$triggers[] = array(
		'type'     => 'click_open',
		'settings' => array( 'extra_selectors' => pws_popup_click_selector(), 'cookie_name' => '' ),
	);
	return array(
		'triggers'                       => $triggers,
		// Once closed, the welcome popup does not open by itself again during the same visit.
		'cookies'                        => $welcome ? array( array(
			'event'    => 'on_popup_close',
			'settings' => array( 'name' => $cookie, 'key' => '', 'session' => true, 'time' => '', 'path' => '1' ),
		) ) : array(),
		// Home page for the welcome popup; every other page for the contact popup.
		'conditions'                     => array( array( array( 'target' => 'is_front_page', 'not_operand' => $welcome ? 0 : 1 ) ) ),
		'theme_id'                       => pum_get_default_theme_id(),
		'size'                           => 'custom',
		'custom_width'                   => '640px',
		'custom_height_auto'             => true,
		'responsive_min_width'           => '0%',
		'responsive_max_width'           => '100%',
		'animation_type'                 => 'fade',
		'animation_speed'                => '350',
		'animation_origin'               => 'center top',
		'location'                       => 'center',
		'position_top'                   => '100',
		'position_bottom'                => '0',
		'position_left'                  => '0',
		'position_right'                 => '0',
		'zindex'                         => '1999999999',
		'close_text'                     => '×',
		'close_button_delay'             => '0',
		'close_on_form_submission'       => false,
		'close_on_overlay_click'         => true,
		'close_on_esc_press'             => true,
		'scrollable_content'             => false,
		'overlay_disabled'               => false,
		'disable_on_mobile'              => false,
		'disable_on_tablet'              => false,
	);
}

/** Create (or, with $reset, rewrite) the two popups. */
function pws_popups_ensure( $reset = false ) {
	if ( ! pws_popup_maker_active() ) {
		return;
	}
	$defs = array(
		'welcome' => array( 'title' => 'Prism Works Welcome Popup', 'welcome' => true ),
		'contact' => array( 'title' => 'Prism Works Contact Popup', 'welcome' => false ),
	);
	foreach ( $defs as $key => $def ) {
		$found = get_posts( array(
			'post_type'      => 'popup',
			'post_status'    => 'any',
			'meta_key'       => '_pws_popup',
			'meta_value'     => $key,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		$id = $found ? (int) $found[0] : 0;
		if ( $id && ! $reset ) {
			continue;
		}
		$post = array(
			'post_type'    => 'popup',
			'post_status'  => 'publish',
			'post_title'   => $def['title'],
			'post_content' => pws_popup_content(),
		);
		if ( $id ) {
			$post['ID'] = $id;
			wp_update_post( $post );
		} else {
			$id = (int) wp_insert_post( $post );
			if ( ! $id ) {
				continue;
			}
		}
		update_post_meta( $id, '_pws_popup', $key );
		update_post_meta( $id, 'popup_settings', pws_popup_settings( $id, $def['welcome'] ) );
		if ( function_exists( 'pum_get_popup' ) ) {
			$popup = pum_get_popup( $id );
			if ( $popup && method_exists( $popup, 'update_cache' ) ) {
				$popup->update_cache();
			}
		}
	}
}
