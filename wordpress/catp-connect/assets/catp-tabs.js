/**
 * Turns the page's plain Groups into tabs.
 *
 * The editor content stays what the setup tool already writes: one Group per
 * section, each starting with a heading. Give the wrapper `catp-tabs` and
 * this builds the tab strip from those headings at load time.
 *
 * Why this way rather than a tabs block:
 *   - With no script (or in print, or in a feed) the page is still complete:
 *     every section, each under its heading, in order. Nothing is hidden
 *     behind a control that never arrives.
 *   - The editor never maintains tab markup. Renaming a tab is renaming a
 *     heading; reordering is dragging a Group.
 *   - It emits the ARIA roles catp-app.css already styles, so the look is
 *     identical whether the markup comes from here or from a block plugin.
 *
 * A tab's `catp-tab-<slug>` class becomes its id, so /resources/#tutoring
 * opens that tab directly — which is how a link from the directory can land
 * on the right one.
 */
( function () {
	'use strict';

	var uid = 0;

	function slugOf( panel, index ) {
		var found = Array.prototype.find.call( panel.classList, function ( name ) {
			return 0 === name.indexOf( 'catp-tab-' );
		} );
		return found ? found.slice( 'catp-tab-'.length ) : 'tab-' + ( index + 1 );
	}

	/** The panels of THIS container — not those of a nested one. */
	function panelsOf( container ) {
		return Array.prototype.filter.call(
			container.querySelectorAll( '.catp-tab' ),
			function ( panel ) {
				return panel.closest( '.catp-tabs' ) === container;
			}
		);
	}

	function select( group, index ) {
		group.tabs.forEach( function ( tab, i ) {
			var on = i === index;
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.tabIndex = on ? 0 : -1;          // one stop in the tab order
			group.panels[ i ].hidden = ! on;
		} );
	}

	function build( container ) {
		var panels = panelsOf( container );
		if ( panels.length < 2 ) {
			return null;                          // one section is not a tab set
		}

		var list = document.createElement( 'div' );
		list.setAttribute( 'role', 'tablist' );
		var label = container.getAttribute( 'aria-label' );
		if ( label ) {
			list.setAttribute( 'aria-label', label );
		}

		var group = { tabs: [], panels: panels };
		uid++;

		panels.forEach( function ( panel, index ) {
			var slug = slugOf( panel, index );
			var heading = panel.querySelector( 'h1, h2, h3, h4' );
			var tab = document.createElement( 'button' );
			tab.type = 'button';
			tab.setAttribute( 'role', 'tab' );
			tab.id = 'catp-tab-' + uid + '-' + slug;
			tab.textContent = heading ? heading.textContent.trim() : slug;

			// The strip now says what the heading said.
			if ( heading ) {
				heading.hidden = true;
			}

			panel.setAttribute( 'role', 'tabpanel' );
			panel.setAttribute( 'aria-labelledby', tab.id );
			panel.id = panel.id || 'catp-panel-' + uid + '-' + slug;
			panel.dataset.catpSlug = slug;

			tab.addEventListener( 'click', function () {
				select( group, index );
			} );

			group.tabs.push( tab );
			list.appendChild( tab );
		} );

		// Left/Right move between tabs, Home/End jump to the ends.
		list.addEventListener( 'keydown', function ( event ) {
			var current = group.tabs.indexOf( document.activeElement );
			if ( current < 0 ) {
				return;
			}
			var next = null;
			if ( 'ArrowRight' === event.key ) {
				next = ( current + 1 ) % group.tabs.length;
			} else if ( 'ArrowLeft' === event.key ) {
				next = ( current - 1 + group.tabs.length ) % group.tabs.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = group.tabs.length - 1;
			}
			if ( null !== next ) {
				event.preventDefault();
				select( group, next );
				group.tabs[ next ].focus();
			}
		} );

		panels[ 0 ].parentNode.insertBefore( list, panels[ 0 ] );
		select( group, 0 );
		return group;
	}

	/**
	 * #tutoring opens the Tutoring tab, at whatever level it sits.
	 *
	 * A nested tab is no use on its own: opening it means opening the tab that
	 * contains it too, so this walks outwards until it runs out of ancestors.
	 */
	function openFromHash( groups ) {
		var wanted = ( window.location.hash || '' ).replace( /^#/, '' );
		if ( ! wanted ) {
			return;
		}

		var match = null;
		groups.forEach( function ( group ) {
			group.panels.forEach( function ( panel, index ) {
				if ( panel.dataset.catpSlug === wanted ) {
					match = { group: group, index: index, panel: panel };
				}
			} );
		} );
		if ( ! match ) {
			return;
		}

		var group = match.group;
		var index = match.index;
		while ( group ) {
			select( group, index );
			var parent = group.container.parentElement;
			var outerPanel = parent && parent.closest( '.catp-tab' );
			if ( ! outerPanel ) {
				break;
			}
			var outer = groups.find( function ( candidate ) {
				return candidate.panels.indexOf( outerPanel ) > -1;
			} );
			if ( ! outer ) {
				break;
			}
			index = outer.panels.indexOf( outerPanel );
			group = outer;
		}
		match.panel.scrollIntoView( { block: 'nearest' } );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var groups = [];
		// Outermost first, so a nested set is built after its parent exists.
		document.querySelectorAll( '.catp-tabs' ).forEach( function ( container ) {
			var group = build( container );
			if ( group ) {
				group.container = container;
				groups.push( group );
			}
		} );
		openFromHash( groups );
		window.addEventListener( 'hashchange', function () {
			openFromHash( groups );
		} );
	} );
}() );
