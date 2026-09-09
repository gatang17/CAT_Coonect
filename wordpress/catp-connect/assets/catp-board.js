/**
 * Drop Zone lightbox.
 *
 * Uses a real <dialog> with showModal(), so Escape, the backdrop, focus
 * containment and returning focus to the tile all come from the browser
 * instead of being re-implemented (badly) here.
 */
( function () {
	'use strict';

	function open( tile ) {
		var gallery = tile.closest( '[data-catp-gallery]' );
		var dialog = gallery && gallery.parentNode.querySelector( '[data-catp-lightbox]' );
		if ( ! dialog ) {
			return;
		}

		var image = dialog.querySelector( '[data-catp-lightbox-image]' );
		image.src = tile.dataset.catpFull;
		image.alt = tile.dataset.catpTitle || '';
		dialog.querySelector( '[data-catp-lightbox-title]' ).textContent = tile.dataset.catpTitle || '';
		dialog.querySelector( '[data-catp-lightbox-date]' ).textContent = tile.dataset.catpDate || '';
		dialog.querySelector( '[data-catp-lightbox-author]' ).textContent =
			tile.dataset.catpAuthor ? 'By ' + tile.dataset.catpAuthor : '';

		if ( dialog.showModal ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );   // very old browsers: still readable
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var tile = event.target.closest( '.catp-tile[data-catp-full]' );
		if ( tile ) {
			event.preventDefault();
			open( tile );
			return;
		}

		var dialog = event.target.closest( '[data-catp-lightbox]' );
		if ( ! dialog ) {
			return;
		}
		// Close on the button, or on the backdrop — which is a click that
		// lands on the dialog element itself rather than on its contents.
		if ( event.target.closest( '[data-catp-close]' ) || event.target === dialog ) {
			dialog.close ? dialog.close() : dialog.removeAttribute( 'open' );
		}
	} );

	// Let go of the image when the dialog closes, so a large one is not held
	// in memory behind an invisible element.
	document.addEventListener( 'close', function ( event ) {
		if ( event.target.matches && event.target.matches( '[data-catp-lightbox]' ) ) {
			event.target.querySelector( '[data-catp-lightbox-image]' ).removeAttribute( 'src' );
		}
	}, true );
}() );
