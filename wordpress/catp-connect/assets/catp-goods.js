/**
 * Program Goods calculator.
 *
 * Adds up the quantities a student picks. No dependencies, no build step, and
 * no network calls — the prices are already in the markup as data attributes,
 * printed by the [catp_goods_calculator] shortcode.
 *
 * It also fills any field marked `catp-goods-summary` with a plain-text list,
 * so a Forminator "request these supplies" form can carry the chosen items
 * without a second source of truth. Nothing breaks when no such field exists.
 */
( function () {
	'use strict';

	function money( currency, amount ) {
		return currency + amount.toFixed( 2 );
	}

	function summarise( root, currency ) {
		var lines = [];
		root.querySelectorAll( '[data-catp-item]' ).forEach( function ( row ) {
			var qty = Number( row.querySelector( '[data-catp-qty]' ).textContent ) || 0;
			if ( qty <= 0 ) {
				return;
			}
			var name = row.querySelector( '.catp-kit-copy strong' );
			lines.push(
				qty + ' x ' + ( name ? name.textContent.trim() : 'item' ) +
				' — ' + money( currency, qty * Number( row.dataset.catpPrice || 0 ) )
			);
		} );
		return lines;
	}

	function recalculate( root ) {
		var currency = root.dataset.catpCurrency || '$';
		var total = 0;

		root.querySelectorAll( '[data-catp-item]' ).forEach( function ( row ) {
			var price = Number( row.dataset.catpPrice ) || 0;
			var qty = Number( row.querySelector( '[data-catp-qty]' ).textContent ) || 0;
			var subtotal = qty * price;
			total += subtotal;
			row.querySelector( '[data-catp-subtotal]' ).textContent = money( currency, subtotal );
		} );

		var totalEl = root.querySelector( '[data-catp-total]' );
		if ( totalEl ) {
			totalEl.textContent = money( currency, total );
		}

		// Nothing chosen yet is not a request worth sending.
		root.querySelectorAll( '[data-catp-requires-items]' ).forEach( function ( el ) {
			el.disabled = total <= 0;
		} );

		var lines = summarise( root, currency );
		document.querySelectorAll( '.catp-goods-summary input, .catp-goods-summary textarea' ).forEach( function ( field ) {
			field.value = lines.length
				? lines.join( '\n' ) + '\nEstimated total: ' + money( currency, total )
				: '';
		} );
	}

	function step( row, direction ) {
		var output = row.querySelector( '[data-catp-qty]' );
		var next = ( Number( output.textContent ) || 0 ) + direction;
		output.textContent = next < 0 ? 0 : next;   // never below zero
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-catp-step]' );
		if ( ! button ) {
			return;
		}
		var root = button.closest( '[data-catp-goods]' );
		var row = button.closest( '[data-catp-item]' );
		if ( ! root || ! row ) {
			return;
		}
		event.preventDefault();
		step( row, Number( button.dataset.catpStep ) );
		recalculate( root );
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-catp-goods]' ).forEach( recalculate );
	} );
}() );
