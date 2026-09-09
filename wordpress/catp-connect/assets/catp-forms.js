/**
 * Toggles that reveal a field.
 *
 * Inside a Forminator form you usually do not need this: give the field the
 * class `catp-toggle` for the look, and use Forminator's own conditional
 * logic to show the dependent field. This covers the rest.
 *
 * Two ways to say what a toggle reveals:
 *
 *   data-catp-reveal="#guest-name"   an explicit selector — use it when you
 *                                    own the markup (a Custom HTML block).
 *   class="catp-reveal"              on the element to show. The toggle
 *                                    reveals the next `.catp-reveal` after
 *                                    it, which is all Forminator lets you
 *                                    express: a CSS class on each field.
 *
 * A `button.catp-toggle` also gets its pressed state managed here, since a
 * button has no checked state of its own. A checkbox needs none of that.
 */
( function () {
	'use strict';

	function target( toggle ) {
		var selector = toggle.dataset.catpReveal;
		if ( selector ) {
			return document.querySelector( selector );
		}
		// Otherwise: the next `.catp-reveal` in document order after the
		// toggle's own row, staying inside the same form when there is one.
		var scope = toggle.closest( 'form' ) || document;
		var candidates = Array.prototype.slice.call( scope.querySelectorAll( '.catp-reveal' ) );
		return candidates.find( function ( el ) {
			// compareDocumentPosition: 4 = el comes after the toggle
			return !! ( toggle.compareDocumentPosition( el ) & Node.DOCUMENT_POSITION_FOLLOWING );
		} ) || null;
	}

	function isOn( toggle ) {
		return 'checkbox' === toggle.type
			? toggle.checked
			: 'true' === toggle.getAttribute( 'aria-pressed' );
	}

	function apply( toggle ) {
		var revealed = target( toggle );
		if ( ! revealed ) {
			return;
		}
		var on = isOn( toggle );
		revealed.hidden = ! on;
		if ( ! on ) {
			// A hidden field must not keep a value the visitor cannot see or
			// correct — it would be submitted anyway.
			revealed.querySelectorAll( 'input, select, textarea' ).forEach( function ( field ) {
				if ( 'checkbox' === field.type || 'radio' === field.type ) {
					field.checked = false;
				} else if ( 'hidden' !== field.type ) {
					field.value = '';
				}
			} );
		}
	}

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( 'input.catp-toggle' ) ) {
			apply( event.target );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button.catp-toggle' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		button.setAttribute( 'aria-pressed', isOn( button ) ? 'false' : 'true' );
		apply( button );
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'input.catp-toggle, button.catp-toggle' ).forEach( function ( toggle ) {
			if ( 'button' === toggle.tagName.toLowerCase() && ! toggle.hasAttribute( 'aria-pressed' ) ) {
				toggle.setAttribute( 'aria-pressed', 'false' );
			}
			apply( toggle );
		} );
	} );
}() );
