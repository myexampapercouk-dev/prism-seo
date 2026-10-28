( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		// Footer year.
		document.querySelectorAll( '[data-pws-year]' ).forEach( function ( el ) {
			el.textContent = new Date().getFullYear();
		} );

		// Scroll reveal (fade up / left / right), staggered inside each group.
		var targets = document.querySelectorAll( '.pws-head, .pws-card, .pws-step, .pws-faq, .pws-promise, .pws-table-wrap, .pws-form, .pws-contact > div:first-child, .pws-two > div, .pws-split > div, .pws-prism, .pws-brand-word, .pws-plan, .pws-flow li, .pws-rules > div, .pws-box, .pws-cta' );
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( targets.length && 'IntersectionObserver' in window && ! reduce ) {
			document.body.classList.add( 'pws-js' );
			var seen = new Map();
			targets.forEach( function ( el ) {
				var parent = el.parentElement;
				var i = seen.get( parent ) || 0;
				seen.set( parent, i + 1 );
				el.style.setProperty( '--d', Math.min( i, 5 ) * 90 + 'ms' );
				el.classList.add( 'pws-reveal' );
				if ( el.matches( '.pws-two > div, .pws-contact > div, .pws-split > div' ) ) {
					el.classList.add( i % 2 ? 'from-right' : 'from-left' );
				}
			} );
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( en ) {
					if ( en.isIntersecting ) {
						en.target.classList.add( 'is-in' );
						io.unobserve( en.target );
					}
				} );
			}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' } );
			targets.forEach( function ( el ) {
				io.observe( el );
			} );
		}

		// Back to top.
		document.querySelectorAll( '[data-pws-top]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				window.scrollTo( { top: 0, behavior: 'smooth' } );
			} );
		} );

		// Mobile menu.
		var toggle = document.querySelector( '[data-pws-menu-toggle]' );
		var menu = document.querySelector( '[data-pws-mobile-menu]' );
		if ( toggle && menu ) {
			toggle.addEventListener( 'click', function () {
				var open = menu.hasAttribute( 'hidden' );
				if ( open ) {
					menu.removeAttribute( 'hidden' );
				} else {
					menu.setAttribute( 'hidden', '' );
				}
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}

	} );
}() );
