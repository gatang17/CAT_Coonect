/**
 * Teacher directory: filter as you type, and expand a row for its actions.
 *
 * The searchable text is already on each row as `data-catp-search-key`,
 * lowercased and stripped of accents by PHP. This normalises what the visitor
 * types the same way, so "avila" finds "Ávila-Ugalde" — then it is a substring
 * test. No index, no fetch, no debounce needed at this size (a dozen rows).
 */
( function () {
	'use strict';

	function normalise( text ) {
		return text
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' )   // drop the accents NFD split off
			.toLowerCase()
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function filter( root ) {
		var query = normalise( root.querySelector( '[data-catp-search]' ).value );
		var shown = 0;

		root.querySelectorAll( '[data-catp-entry]' ).forEach( function ( entry ) {
			var match = ! query || ( entry.dataset.catpSearchKey || '' ).indexOf( query ) !== -1;
			entry.hidden = ! match;
			if ( match ) {
				shown++;
			} else {
				collapse( entry );   // a hidden row must not keep an open panel
			}
		} );

		// A group whose every row is filtered out should take its heading with it.
		root.querySelectorAll( '[data-catp-group]' ).forEach( function ( group ) {
			var any = Array.prototype.some.call(
				group.querySelectorAll( '[data-catp-entry]' ),
				function ( entry ) { return ! entry.hidden; }
			);
			group.hidden = ! any;
		} );

		var empty = root.querySelector( '[data-catp-empty]' );
		if ( empty ) {
			empty.hidden = shown !== 0;
		}
		var count = root.querySelector( '[data-catp-count]' );
		if ( count ) {
			count.textContent = query
				? shown + ( 1 === shown ? ' result' : ' results' )
				: '';
		}
	}

	function collapse( entry ) {
		var toggle = entry.querySelector( '[data-catp-toggle]' );
		var panel = entry.querySelector( '[data-catp-panel]' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'false' );
		}
		if ( panel ) {
			panel.hidden = true;
		}
	}

	document.addEventListener( 'input', function ( event ) {
		if ( ! event.target.matches( '[data-catp-search]' ) ) {
			return;
		}
		var root = event.target.closest( '[data-catp-directory]' );
		if ( root ) {
			filter( root );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '[data-catp-toggle]' );
		if ( ! toggle ) {
			return;
		}
		var entry = toggle.closest( '[data-catp-entry]' );
		var panel = entry && entry.querySelector( '[data-catp-panel]' );
		if ( ! panel ) {
			return;
		}
		event.preventDefault();
		var open = 'true' === toggle.getAttribute( 'aria-expanded' );
		// One open row at a time, like the prototype.
		entry.closest( '[data-catp-directory]' )
			.querySelectorAll( '[data-catp-entry]' )
			.forEach( collapse );
		if ( ! open ) {
			toggle.setAttribute( 'aria-expanded', 'true' );
			panel.hidden = false;
		}
	} );
}() );
