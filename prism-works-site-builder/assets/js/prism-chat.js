( function () {
	'use strict';
	if ( typeof PWSCHAT === 'undefined' ) {
		return;
	}

	var KEY = 'pwsChat';
	var state = { open: false, busy: false, msgs: [], mode: 'chat' };
	var els = {};

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text ) {
			n.textContent = text;
		}
		return n;
	}

	function load() {
		try {
			var s = JSON.parse( window.sessionStorage.getItem( KEY ) || '[]' );
			if ( Array.isArray( s ) ) {
				state.msgs = s.slice( -30 );
			}
		} catch ( e ) {}
	}

	function save() {
		try {
			window.sessionStorage.setItem( KEY, JSON.stringify( state.msgs.slice( -30 ) ) );
		} catch ( e ) {}
	}

	/* The "Talk to a human" form is a Contact Form 7 form; it carries the chat so far in a hidden field. */
	function syncTranscript() {
		var f = document.querySelector( '#pws-chat-cf7 input[name="chat-transcript"]' );
		if ( f ) {
			f.value = state.msgs.slice( -12 ).map( function ( m ) {
				return ( 'user' === m.role ? 'Visitor: ' : 'Assistant: ' ) + m.content;
			} ).join( '\n' ).slice( 0, 3000 );
		}
	}

	/* Turn emails and phone numbers into links, without ever inserting HTML. */
	function fill( node, text ) {
		var re = /([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})|(\+\d[\d ]{8,}\d)/g;
		var last = 0, m;
		while ( ( m = re.exec( text ) ) ) {
			if ( m.index > last ) {
				node.appendChild( document.createTextNode( text.slice( last, m.index ) ) );
			}
			var a = el( 'a', '', m[0] );
			a.href = m[1] ? 'mailto:' + m[1] : 'tel:' + m[2].replace( /\s/g, '' );
			node.appendChild( a );
			last = m.index + m[0].length;
		}
		if ( last < text.length ) {
			node.appendChild( document.createTextNode( text.slice( last ) ) );
		}
	}

	function addMsg( role, text, silent ) {
		var row = el( 'div', 'pws-chat-row pws-chat-' + role );
		var bubble = el( 'div', 'pws-chat-bubble' );
		fill( bubble, text );
		row.appendChild( bubble );
		els.log.appendChild( row );
		els.log.scrollTop = els.log.scrollHeight;
		if ( ! silent ) {
			state.msgs.push( { role: role, content: text } );
			save();
			syncTranscript();
		}
	}

	function chips( list ) {
		var old = els.log.querySelector( '.pws-chat-chips' );
		if ( old ) {
			old.remove();
		}
		var wrap = el( 'div', 'pws-chat-chips' );
		list.forEach( function ( c ) {
			var b = el( 'button', 'pws-chat-chip', c.label );
			b.type = 'button';
			b.addEventListener( 'click', function () {
				wrap.remove();
				c.run();
			} );
			wrap.appendChild( b );
		} );
		els.log.appendChild( wrap );
		els.log.scrollTop = els.log.scrollHeight;
	}

	function ask( text ) {
		return function () {
			send( text );
		};
	}

	/* Open the free-consultation popup (Popup Maker), or go to the Contact page if it is not available. */
	function bookCall() {
		close();
		if ( PWSCHAT.popup && window.PUM && typeof window.PUM.open === 'function' ) {
			window.PUM.open( PWSCHAT.popup );
		} else {
			window.location.href = PWSCHAT.home + 'contact/';
		}
	}

	function defaultChips() {
		chips( [
			{ label: 'Our services', run: ask( 'What services do you offer?' ) },
			{ label: 'Pricing', run: ask( 'How much does it cost?' ) },
			{ label: 'How long does it take?', run: ask( 'How long will a project take?' ) },
			{ label: 'Book a free call', run: bookCall },
			{ label: 'Talk to a human', run: showHandoff }
		] );
	}

	function typing( on ) {
		var t = els.log.querySelector( '.pws-chat-typing' );
		if ( on && ! t ) {
			t = el( 'div', 'pws-chat-row pws-chat-assistant pws-chat-typing' );
			var b = el( 'div', 'pws-chat-bubble' );
			b.innerHTML = '<i></i><i></i><i></i>';
			t.appendChild( b );
			els.log.appendChild( t );
			els.log.scrollTop = els.log.scrollHeight;
		} else if ( ! on && t ) {
			t.remove();
		}
	}

	function post( action, data ) {
		var body = new URLSearchParams();
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		body.append( 'action', action );
		body.append( 'nonce', PWSCHAT.nonce );
		return fetch( PWSCHAT.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) {
			return r.json();
		} );
	}

	function send( text ) {
		text = ( text || '' ).trim();
		if ( ! text || state.busy ) {
			return;
		}
		var old = els.log.querySelector( '.pws-chat-chips' );
		if ( old ) {
			old.remove();
		}
		addMsg( 'user', text );
		els.input.value = '';
		state.busy = true;
		typing( true );
		var started = Date.now();
		post( 'pws_chat', { messages: JSON.stringify( state.msgs.slice( -10 ) ) } )
			.then( function ( res ) {
				var wait = Math.max( 0, 600 - ( Date.now() - started ) );
				return new Promise( function ( ok ) {
					window.setTimeout( function () {
						ok( res );
					}, wait );
				} );
			} )
			.then( function ( res ) {
				typing( false );
				if ( res && res.success ) {
					addMsg( 'assistant', res.data.reply );
					if ( res.data.handoff ) {
						chips( [
							{ label: 'Talk to a human', run: showHandoff },
							{ label: 'Book a free call', run: bookCall }
						] );
					} else {
						defaultChips();
					}
				} else {
					addMsg( 'assistant', 'Sorry, something went wrong. You can email ' + PWSCHAT.email + ' or call ' + PWSCHAT.phone + '.' );
				}
			} )
			.catch( function () {
				typing( false );
				addMsg( 'assistant', 'Sorry, I could not connect. You can email ' + PWSCHAT.email + ' or call ' + PWSCHAT.phone + '.' );
			} )
			.then( function () {
				state.busy = false;
			} );
	}

	function showHandoff() {
		if ( ! els.cf7 ) {
			addMsg( 'assistant', 'You can reach our team at ' + PWSCHAT.email + ' or on ' + PWSCHAT.phone + '.' );
			defaultChips();
			return;
		}
		state.mode = 'handoff';
		syncTranscript();
		els.panel.classList.add( 'is-handoff' );
		var first = els.cf7.querySelector( 'input[type="text"]' );
		if ( first ) {
			first.focus();
		}
	}

	function hideHandoff() {
		state.mode = 'chat';
		els.panel.classList.remove( 'is-handoff' );
		els.input.focus();
	}

	function open() {
		state.open = true;
		els.panel.hidden = false;
		window.requestAnimationFrame( function () {
			els.root.classList.add( 'is-open' );
			els.input.focus( { preventScroll: true } );
		} );
		els.btn.setAttribute( 'aria-expanded', 'true' );
		els.dot.hidden = true;
		if ( ! els.log.children.length ) {
			if ( state.msgs.length ) {
				state.msgs.forEach( function ( m ) {
					addMsg( m.role, m.content, true );
				} );
			} else {
				addMsg( 'assistant', 'Hi! I am the Prism Works assistant. Ask me about our services, pricing or timelines, or book a free discovery call.' );
			}
			defaultChips();
		}
	}

	function close() {
		state.open = false;
		els.root.classList.remove( 'is-open' );
		els.btn.setAttribute( 'aria-expanded', 'false' );
		window.setTimeout( function () {
			if ( ! state.open ) {
				els.panel.hidden = true;
			}
		}, 300 );
	}

	function build() {
		var root = el( 'div', 'pws-chat' );
		els.root = root;

		var btn = el( 'button', 'pws-chat-btn' );
		btn.type = 'button';
		btn.setAttribute( 'aria-label', 'Chat with us' );
		btn.setAttribute( 'aria-expanded', 'false' );
		btn.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"/></svg>';
		els.dot = el( 'span', 'pws-chat-dot' );
		btn.appendChild( els.dot );
		btn.addEventListener( 'click', function () {
			if ( state.open ) {
				close();
			} else {
				open();
			}
		} );
		els.btn = btn;

		var panel = el( 'div', 'pws-chat-panel' );
		panel.hidden = true;
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-label', 'Chat with Prism Works' );
		els.panel = panel;

		var head = el( 'div', 'pws-chat-head' );
		var who = el( 'div', 'pws-chat-who' );
		who.appendChild( el( 'strong', '', 'Prism Works Assistant' ) );
		who.appendChild( el( 'span', '', PWSCHAT.ai ? 'AI assistant · answers instantly' : 'Instant answers · human help on request' ) );
		var x = el( 'button', 'pws-chat-x', '×' );
		x.type = 'button';
		x.setAttribute( 'aria-label', 'Close chat' );
		x.addEventListener( 'click', close );
		head.appendChild( who );
		head.appendChild( x );

		var log = el( 'div', 'pws-chat-log' );
		log.setAttribute( 'aria-live', 'polite' );
		els.log = log;

		var form = el( 'form', 'pws-chat-form' );
		var input = el( 'input' );
		input.type = 'text';
		input.placeholder = 'Type your question…';
		input.maxLength = 500;
		input.setAttribute( 'aria-label', 'Your message' );
		input.autocomplete = 'off';
		var go = el( 'button', '', '' );
		go.type = 'submit';
		go.setAttribute( 'aria-label', 'Send' );
		go.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>';
		form.appendChild( input );
		form.appendChild( go );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			send( input.value );
		} );
		els.input = input;

		/* Talk to a human: the Contact Form 7 form from the page footer, shown inside the chat window. */
		var h = el( 'div', 'pws-chat-handoff' );
		h.appendChild( el( 'strong', '', 'Talk to our team' ) );
		h.appendChild( el( 'p', '', 'Leave your details and a member of the team will reply by email.' ) );
		var src = document.getElementById( 'pws-chat-cf7' );
		if ( src ) {
			src.hidden = false;
			h.appendChild( src );
			els.cf7 = src;
			src.addEventListener( 'wpcf7mailsent', function () {
				hideHandoff();
				addMsg( 'assistant', 'Thank you. Your message is with our team and we will reply by email. You can also reach us at ' + PWSCHAT.email + ' or on ' + PWSCHAT.phone + '.' );
				defaultChips();
			} );
		}
		var back = el( 'button', 'pws-chat-back', 'Back to chat' );
		back.type = 'button';
		back.addEventListener( 'click', function () {
			hideHandoff();
			if ( ! els.log.querySelector( '.pws-chat-chips' ) ) {
				defaultChips();
			}
		} );
		h.appendChild( back );

		panel.appendChild( head );
		panel.appendChild( log );
		panel.appendChild( form );
		panel.appendChild( h );
		root.appendChild( panel );
		root.appendChild( btn );
		document.body.appendChild( root );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && state.open ) {
				close();
			}
		} );
	}

	function init() {
		load();
		build();
		syncTranscript();
		/* A small nudge so the button is noticed. */
		try {
			if ( ! window.sessionStorage.getItem( 'pwsChatSeen' ) ) {
				window.sessionStorage.setItem( 'pwsChatSeen', '1' );
				els.dot.hidden = false;
			} else {
				els.dot.hidden = true;
			}
		} catch ( e ) {
			els.dot.hidden = true;
		}
	}

	if ( 'loading' !== document.readyState ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
}() );
