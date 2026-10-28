<?php
/**
 * Live chat and AI assistant (built into this plugin, no chat plugin needed).
 *
 * - Instant answers: the assistant answers from the site's own content. Works with no setup.
 * - AI mode: add a Claude API key under Prism Works and it answers in natural language, using the same
 *   site facts. The key stays on the server and is never sent to the browser.
 * - Live chat: "Talk to a human" shows a Contact Form 7 form inside the chat window. The message (with the
 *   chat so far) is emailed to your team and saved under Enquiries. A third-party live chat widget can also
 *   be pasted under Prism Works if you want real-time replies from a person.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PWS_CHAT_PHONE = '+44 1902 937021';
const PWS_CHAT_EMAIL = 'contact@prismworks.studio';

function pws_chat_enabled() {
	return pws_sb_is_ready() && '0' !== (string) get_option( 'pws_chat_enabled', '1' );
}

function pws_chat_has_ai() {
	return '' !== trim( (string) get_option( 'pws_chat_key', '' ) );
}

/** The facts the assistant knows. [ keywords, answer ]. Only things the site itself says. */
function pws_chat_kb() {
	return array(
		array( array( 'hello', 'hi ', 'hey', 'good morning', 'good afternoon', 'good evening' ), 'Hi! I am the Prism Works assistant. I can tell you about our services, pricing, timelines and how to book a free discovery call. What would you like to know?' ),
		array( array( 'service', 'offer', 'what do you do', 'what can you', 'help with' ), 'Prism Works Studio is a digital marketing and ecommerce agency. We do SEO (including AI SEO, GEO, technical, ecommerce and international SEO), paid media (Google, Shopping, Meta, TikTok, LinkedIn, YouTube), digital PR and content, and website and ecommerce development on Shopify, WooCommerce and WordPress. See the Services page for details.' ),
		array( array( 'seo', 'search engine', 'rank', 'google search', 'organic', 'geo', 'generative' ), 'Our SEO covers AI SEO, GEO (Generative Engine Optimisation, so your brand is cited in tools like ChatGPT and Google AI Overviews), technical SEO, ecommerce SEO and international SEO. We build lasting organic growth, not quick wins that disappear. We never promise specific rankings.' ),
		array( array( 'paid', 'ppc', 'ads', 'advert', 'google ads', 'shopping', 'meta', 'facebook', 'instagram', 'tiktok', 'linkedin', 'youtube' ), 'We manage paid media across Google Ads, Google Shopping, Meta (Facebook and Instagram), TikTok, LinkedIn and YouTube, with audience targeting, creative testing and ongoing optimisation. Advertising spend is paid separately to the platforms.' ),
		array( array( 'pr ', 'digital pr', 'content', 'blog', 'press', 'backlink', 'copywriting' ), 'Our digital PR earns coverage in publications your customers read, which builds authority and backlinks. Our content marketing includes blog articles, buying guides, pillar pages and video scripts, all designed to rank, earn links and build trust.' ),
		array( array( 'website', 'web design', 'web develop', 'ecommerce', 'e-commerce', 'shopify', 'woocommerce', 'wordpress', 'landing page', 'store', 'shop' ), 'We design and build fast, conversion-focused websites and ecommerce stores on Shopify, WooCommerce and WordPress, plus landing pages and website redesigns and migrations that keep your search rankings. Websites and major technical work are scoped as separate projects.' ),
		array( array( 'price', 'pricing', 'cost', 'how much', 'quote', 'budget', 'retainer', 'package', 'afford', 'fee' ), 'Starting prices: Launch retainer £350/month (4 posts, basic local SEO, monthly report), Growth £750/month (8 posts, SEO, tracking, regular review) and Scale £1,500/month (12 posts, SEO, ads management, reporting). Small websites start at £1,000 to £2,000, ecommerce sites £1,500 to £3,500, and automation projects are quoted by scope. Ad spend is paid separately. Your final quote depends on scope, hours and complexity.' ),
		array( array( 'how long', 'timeline', 'turnaround', 'weeks', 'how soon', 'when will', 'deadline', 'duration' ), 'A standard website typically takes 3 to 5 weeks. Ecommerce builds take 5 to 12 weeks depending on catalogue size and custom functionality. SEO and paid media campaigns typically start to show meaningful results within 3 to 6 months of launch.' ),
		array( array( 'maintenance', 'support', 'security', 'updates', 'hosting' ), 'Yes, we offer ongoing maintenance packages for websites and ecommerce stores covering platform updates, security monitoring, performance optimisation and priority support.' ),
		array( array( 'redesign', 'revamp', 'existing website', 'existing site', 'migrate', 'migration' ), 'Yes, ecommerce redesigns are one of our specialities. We audit your current site for performance, UX, conversion and SEO issues, then build a new version that improves speed, design and sales, while preserving your search rankings during migration.' ),
		array( array( 'update the website', 'edit my', 'update content', 'manage myself', 'training', 'cms' ), 'Yes. We build on user-friendly platforms such as Shopify, WooCommerce and WordPress so you can manage products and content without any coding, and we provide training so you are confident from day one.' ),
		array( array( 'sector', 'industry', 'industries', 'healthcare', 'hospitality', 'restaurant', 'financial', 'saas', 'b2b', 'education' ), 'We specialise in health and healthcare, hospitality, ecommerce, financial advisors, SaaS companies, B2B and education and training. See the Sectors page for how we help each one.' ),
		array( array( 'process', 'how does it work', 'how do you work', 'steps', 'workflow', 'onboard' ), 'We follow a clear path: lead, qualify, discovery call, proposal, agreement, payment, onboarding, delivery, reporting and renewal. Projects start with a deposit and retainers are billed in advance. We start with research and planning, then concept and design, testing and quality, and ongoing help and support.' ),
		array( array( 'guarantee', 'promise', 'results', 'sure', 'roi' ), 'We never promise rankings, sales or results we cannot control. What we do promise is a clear scope, honest reporting and strategies built around measurable outcomes for your business.' ),
		array( array( 'contact', 'phone', 'call', 'email', 'reach', 'speak', 'book', 'consult', 'discovery', 'meeting' ), 'You can book a free, no-obligation 30-minute discovery call with the form on our Contact page, email ' . PWS_CHAT_EMAIL . ' or phone ' . PWS_CHAT_PHONE . '. Tap "Book a free call" below and we will take your details.' ),
		array( array( 'about', 'who are you', 'company', 'agency', 'uk ', 'where are you' ), 'Prism Works Studio is a results-driven digital marketing and ecommerce agency helping UK businesses build powerful online presences and measurable growth, combining strategy, creativity and technology including AI-powered tools.' ),
	);
}

/** Plain reply from the site's own content. Returns array( text, handoff ). */
function pws_chat_faq_reply( $text ) {
	$q    = ' ' . strtolower( wp_strip_all_tags( (string) $text ) ) . ' ';
	$best = null;
	$top  = 0;
	foreach ( pws_chat_kb() as $row ) {
		$score = 0;
		foreach ( $row[0] as $kw ) {
			if ( false !== strpos( $q, $kw ) ) {
				$score += strlen( trim( $kw ) ) > 3 ? 2 : 1;
			}
		}
		if ( $score > $top ) {
			$top  = $score;
			$best = $row[1];
		}
	}
	if ( $best ) {
		return array( $best, false );
	}
	return array( 'I am not sure about that one. I can pass your question to our team, or you can book a free discovery call. Tap "Talk to a human" below and leave your details.', true );
}

function pws_chat_system_prompt() {
	$facts = array();
	foreach ( pws_chat_kb() as $i => $row ) {
		if ( 0 === $i ) {
			continue;
		}
		$facts[] = '- ' . $row[1];
	}
	return "You are the website assistant for Prism Works Studio, a digital marketing and ecommerce agency for UK businesses.\n"
		. "Answer in warm, concise British English, in 1 to 4 short sentences, using ONLY the facts below. Plain text only, no markdown.\n"
		. "If something is not covered, say you are not sure and suggest booking a free discovery call or tapping \"Talk to a human\". Never invent prices, timelines, clients, results or guarantees. Never promise rankings or sales.\n"
		. 'Contact: ' . PWS_CHAT_EMAIL . ', ' . PWS_CHAT_PHONE . ".\n\nFacts:\n" . implode( "\n", $facts );
}

/** Ask Claude. Returns text, or false when no key is set or the call fails. */
function pws_chat_ai_reply( $messages ) {
	$key = trim( (string) get_option( 'pws_chat_key', '' ) );
	if ( '' === $key || ! $messages ) {
		return false;
	}
	$model = trim( (string) get_option( 'pws_chat_model', '' ) );
	$model = '' !== $model ? $model : 'claude-haiku-5-5';
	$resp  = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 25,
		'headers' => array(
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'max_tokens' => 400,
			'system'     => pws_chat_system_prompt(),
			'messages'   => $messages,
		) ),
	) );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return false;
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	$out  = '';
	if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
		foreach ( $data['content'] as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$out .= $block['text'];
			}
		}
	}
	$out = trim( wp_strip_all_tags( $out ) );
	return '' !== $out ? mb_substr( $out, 0, 1200 ) : false;
}

function pws_chat_rate_ok() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'pws_chat_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 40 ) {
		return false;
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}

add_action( 'wp_ajax_pws_chat', 'pws_chat_ajax' );
add_action( 'wp_ajax_nopriv_pws_chat', 'pws_chat_ajax' );
function pws_chat_ajax() {
	check_ajax_referer( 'pws_chat', 'nonce' );
	if ( ! pws_chat_enabled() ) {
		wp_send_json_error( array( 'message' => 'off' ), 404 );
	}
	if ( ! pws_chat_rate_ok() ) {
		wp_send_json_success( array( 'reply' => 'You are sending messages very quickly. Please wait a few minutes, or email us at ' . PWS_CHAT_EMAIL . '.', 'mode' => 'faq', 'handoff' => true ) );
	}
	$raw  = isset( $_POST['messages'] ) ? json_decode( wp_unslash( $_POST['messages'] ), true ) : array();
	$msgs = array();
	if ( is_array( $raw ) ) {
		foreach ( array_slice( $raw, -10 ) as $m ) {
			if ( ! is_array( $m ) || empty( $m['content'] ) || ! isset( $m['role'] ) || ! in_array( $m['role'], array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			$text = mb_substr( sanitize_textarea_field( (string) $m['content'] ), 0, 600 );
			if ( '' === trim( $text ) ) {
				continue;
			}
			if ( $msgs && $msgs[ count( $msgs ) - 1 ]['role'] === $m['role'] ) {
				$msgs[ count( $msgs ) - 1 ]['content'] .= "\n" . $text;
			} else {
				$msgs[] = array( 'role' => $m['role'], 'content' => $text );
			}
		}
	}
	while ( $msgs && 'user' !== $msgs[0]['role'] ) {
		array_shift( $msgs );
	}
	if ( ! $msgs || 'user' !== $msgs[ count( $msgs ) - 1 ]['role'] ) {
		wp_send_json_error( array( 'message' => 'empty' ), 400 );
	}
	$last = $msgs[ count( $msgs ) - 1 ]['content'];

	$ai = pws_chat_ai_reply( $msgs );
	if ( false !== $ai ) {
		wp_send_json_success( array( 'reply' => $ai, 'mode' => 'ai', 'handoff' => false !== stripos( $ai, 'talk to a human' ) ) );
	}
	$faq = pws_chat_faq_reply( $last );
	wp_send_json_success( array( 'reply' => $faq[0], 'mode' => 'faq', 'handoff' => $faq[1] ) );
}

/** Popup Maker popup that matches this page, so "Book a free call" in the chat can open it. */
function pws_chat_popup_id() {
	if ( ! post_type_exists( 'popup' ) ) {
		return 0;
	}
	$key   = is_front_page() ? 'welcome' : 'contact';
	$found = get_posts( array(
		'post_type'      => 'popup',
		'post_status'    => 'publish',
		'meta_key'       => '_pws_popup',
		'meta_value'     => $key,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $found ? (int) $found[0] : 0;
}

function pws_chat_on_this_page() {
	return pws_chat_enabled() && function_exists( 'pws_sb_is_ours' ) && pws_sb_is_ours() && ! isset( $_GET['elementor-preview'] );
}

/** Front end: script and settings. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! pws_chat_on_this_page() ) {
		return;
	}
	$f = PWS_SB_DIR . 'assets/js/prism-chat.js';
	wp_enqueue_script( 'pws-chat', PWS_SB_URL . 'assets/js/prism-chat.js', array(), file_exists( $f ) ? (string) filemtime( $f ) : PWS_SB_VERSION, true );
	wp_localize_script( 'pws-chat', 'PWSCHAT', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'pws_chat' ),
		'ai'    => pws_chat_has_ai(),
		'home'  => home_url( '/' ),
		'phone' => PWS_CHAT_PHONE,
		'email' => PWS_CHAT_EMAIL,
		'popup' => pws_chat_popup_id(),
		'human' => pws_cf7_active() && (int) get_option( 'pws_cf7_chat_id' ) > 0,
	) );
}, 1000 );

/** The "Talk to a human" form (Contact Form 7). The chat window moves it into place. */
add_action( 'wp_footer', function () {
	if ( ! pws_chat_on_this_page() ) {
		return;
	}
	$id = pws_cf7_ensure_chat_form();
	if ( $id ) {
		echo '<div id="pws-chat-cf7" class="pws-chat-cf7" hidden>' . do_shortcode( '[contact-form-7 id="' . (int) $id . '" title="Prism Works Chat"]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$embed = (string) get_option( 'pws_chat_embed', '' );
	if ( '' !== trim( $embed ) ) {
		echo "\n" . $embed . "\n"; // Pasted by an administrator with the unfiltered_html capability.
	}
}, 9 );

/** Admin settings box (shown on the Prism Works screen). */
function pws_chat_admin_box() {
	if ( isset( $_GET['chat_saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Chat settings saved.</p></div>';
	}
	$on  = '0' !== (string) get_option( 'pws_chat_enabled', '1' );
	$has = pws_chat_has_ai();
	echo '<h2 style="margin-top:28px">Live chat &amp; AI assistant</h2>';
	echo '<p>A chat button appears on every page. It answers from your site content right away. Add a Claude API key to switch it to AI answers. &ldquo;Talk to a human&rdquo; shows a Contact Form 7 form, emails your team and saves the message under Enquiries.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:920px">';
	wp_nonce_field( 'pws_chat_settings' );
	echo '<input type="hidden" name="action" value="pws_chat_settings">';
	echo '<table class="form-table"><tbody>';
	echo '<tr><th>Show chat</th><td><label><input type="checkbox" name="pws_chat_enabled" value="1"' . checked( $on, true, false ) . '> Show the chat button on the site</label></td></tr>';
	echo '<tr><th>Claude API key</th><td><input type="password" name="pws_chat_key" class="regular-text" autocomplete="off" placeholder="' . ( $has ? 'Saved. Leave empty to keep it' : 'sk-ant-…' ) . '">';
	echo '<p class="description">Optional. With a key the assistant answers in natural language. Visitor questions are sent to Anthropic to generate the answer, and the key never reaches the browser. ' . ( $has ? '<strong>AI mode is on.</strong> ' : 'Without a key, the built-in instant answers are used. ' ) . '<label><input type="checkbox" name="pws_chat_key_clear" value="1"> Remove the saved key</label></p></td></tr>';
	echo '<tr><th>Model</th><td><input type="text" name="pws_chat_model" class="regular-text" value="' . esc_attr( get_option( 'pws_chat_model', '' ) ) . '" placeholder="claude-haiku-5-5"></td></tr>';
	echo '<tr><th>Real-time chat widget code</th><td><textarea name="pws_chat_embed" rows="4" class="large-text code"' . ( current_user_can( 'unfiltered_html' ) ? '' : ' disabled' ) . ' placeholder="Optional: paste the embed code from Tawk.to, Crisp…">' . esc_textarea( get_option( 'pws_chat_embed', '' ) ) . '</textarea>';
	echo '<p class="description">Optional. For real-time replies from a person, paste your live chat provider&rsquo;s code here. It is added to every Prism Works page.</p></td></tr>';
	echo '</tbody></table><p><button class="button button-primary">Save chat settings</button></p></form>';
}

add_action( 'admin_post_pws_chat_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'pws_chat_settings' );
	update_option( 'pws_chat_enabled', isset( $_POST['pws_chat_enabled'] ) ? '1' : '0' );
	if ( ! empty( $_POST['pws_chat_key_clear'] ) ) {
		update_option( 'pws_chat_key', '' );
	} elseif ( ! empty( $_POST['pws_chat_key'] ) ) {
		update_option( 'pws_chat_key', sanitize_text_field( wp_unslash( $_POST['pws_chat_key'] ) ) );
	}
	update_option( 'pws_chat_model', isset( $_POST['pws_chat_model'] ) ? sanitize_text_field( wp_unslash( $_POST['pws_chat_model'] ) ) : '' );
	if ( current_user_can( 'unfiltered_html' ) && isset( $_POST['pws_chat_embed'] ) ) {
		update_option( 'pws_chat_embed', wp_unslash( $_POST['pws_chat_embed'] ) );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'pws-site', 'chat_saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );
