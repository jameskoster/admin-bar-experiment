/**
 * Admin Bar Experiment.
 *
 * - Hides the (now empty) inert WordPress logo from assistive technology.
 * - In the block editors, turns the logo into a trigger that reveals the
 *   admin menu (#adminmenumain) as an overlay on hover, focus or click.
 */
( function () {
	'use strict';

	var CLOSE_DELAY = 250;
	var TRANSITION = 200;
	var DESKTOP = window.matchMedia( '(min-width: 782px)' );

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var logo = document.getElementById( 'wp-admin-bar-wp-logo' );
		if ( ! logo ) {
			return;
		}

		var item = logo.querySelector( '.ab-item' );

		// Decorative logo: remove it from the accessibility tree.
		if ( logo.classList.contains( 'abx-inert' ) ) {
			logo.setAttribute( 'aria-hidden', 'true' );
			if ( item ) {
				item.setAttribute( 'role', 'presentation' );
			}
			return;
		}

		if ( ! logo.classList.contains( 'abx-nav-trigger' ) || ! item ) {
			return;
		}

		var body = document.body;
		var menu = document.getElementById( 'adminmenumain' );
		if ( ! menu ) {
			return;
		}

		var closeTimer = null;
		var restoreTimer = null;
		var savedFoldClasses = [];
		var isOpen = false;
		var openedAt = 0;
		var suppressFocusOpen = false;

		item.setAttribute( 'role', 'button' );
		item.setAttribute( 'aria-controls', 'adminmenumain' );
		item.setAttribute( 'aria-expanded', 'false' );

		function overlayAvailable() {
			return DESKTOP.matches && body.classList.contains( 'is-fullscreen-mode' );
		}

		function firstMenuLink() {
			return menu.querySelector( '#adminmenu a' );
		}

		function open() {
			clearTimeout( closeTimer );
			if ( isOpen || ! overlayAvailable() ) {
				return;
			}
			isOpen = true;
			openedAt = Date.now();

			// Show the full-width menu even if the user has it folded.
			clearTimeout( restoreTimer );
			if ( ! savedFoldClasses.length ) {
				savedFoldClasses = [ 'folded', 'auto-fold' ].filter( function ( c ) {
					return body.classList.contains( c );
				} );
			}
			savedFoldClasses.forEach( function ( c ) {
				body.classList.remove( c );
			} );

			body.classList.add( 'abx-nav-open' );
			logo.classList.add( 'abx-is-open' );
			item.setAttribute( 'aria-expanded', 'true' );
		}

		function close() {
			clearTimeout( closeTimer );
			if ( ! isOpen ) {
				return;
			}
			isOpen = false;

			body.classList.remove( 'abx-nav-open' );
			logo.classList.remove( 'abx-is-open' );
			item.setAttribute( 'aria-expanded', 'false' );

			// Restore fold state once the slide-out has finished.
			restoreTimer = setTimeout( function () {
				savedFoldClasses.forEach( function ( c ) {
					body.classList.add( c );
				} );
				savedFoldClasses = [];
			}, TRANSITION );
		}

		function scheduleClose() {
			clearTimeout( closeTimer );
			closeTimer = setTimeout( close, CLOSE_DELAY );
		}

		function within( node ) {
			return !! node && ( logo.contains( node ) || menu.contains( node ) );
		}

		// Pointer.
		[ logo, menu ].forEach( function ( el ) {
			el.addEventListener( 'mouseenter', open );
			el.addEventListener( 'mouseleave', scheduleClose );
		} );

		item.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			// Hover/focus usually opens it a moment before the click lands;
			// only treat the click as a toggle when it wasn't just opened.
			if ( isOpen && Date.now() - openedAt > 400 ) {
				close();
			} else {
				open();
			}
		} );

		document.addEventListener( 'pointerdown', function ( event ) {
			if ( isOpen && ! within( event.target ) ) {
				close();
			}
		} );

		// Focus.
		item.addEventListener( 'focus', function () {
			if ( ! suppressFocusOpen ) {
				open();
			}
		} );

		[ logo, menu ].forEach( function ( el ) {
			el.addEventListener( 'focusout', function ( event ) {
				if ( ! within( event.relatedTarget ) ) {
					scheduleClose();
				}
			} );
			el.addEventListener( 'focusin', function () {
				clearTimeout( closeTimer );
			} );
		} );

		// Keyboard.
		item.addEventListener( 'keydown', function ( event ) {
			var link;

			switch ( event.key ) {
				case 'Enter':
				case ' ':
				case 'ArrowDown':
					event.preventDefault();
					open();
					link = firstMenuLink();
					if ( link ) {
						link.focus();
					}
					break;
				case 'Tab':
					// Tab moves into the overlay rather than along the toolbar.
					if ( ! event.shiftKey && isOpen && ( link = firstMenuLink() ) ) {
						event.preventDefault();
						link.focus();
					}
					break;
				case 'Escape':
					close();
					break;
			}
		} );

		menu.addEventListener( 'keydown', function ( event ) {
			var link = firstMenuLink();

			if ( 'Escape' === event.key ) {
				event.preventDefault();
				close();
				suppressFocusOpen = true;
				item.focus();
				suppressFocusOpen = false;
			} else if ( 'Tab' === event.key && event.shiftKey && document.activeElement === link ) {
				event.preventDefault();
				item.focus();
			}
		} );

		// Close if the overlay stops being applicable (fullscreen toggled off, resize).
		DESKTOP.addEventListener( 'change', function () {
			if ( ! overlayAvailable() ) {
				close();
			}
		} );

		new MutationObserver( function () {
			if ( isOpen && ! overlayAvailable() ) {
				close();
			}
		} ).observe( body, { attributes: true, attributeFilter: [ 'class' ] } );
	} );
} )();
