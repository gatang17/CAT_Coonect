<?php
/**
 * The Program Goods calculator.
 *
 * The one place the app has to compute something. A price list cannot add
 * itself up: no free block plugin sums ACF values, so this ships markup and
 * a small script. It is deliberately the ONLY behaviour the plugin renders.
 *
 * Styling still lives in catp-app.css in the Customizer — the classes below
 * (catp-kit-row, catp-qty, catp-kit-total …) are the ones that file already
 * styles, so a designer changes the look without touching this file.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'catp_connect_register_goods_script' );
function catp_connect_register_goods_script() {
	wp_register_script(
		'catp-goods',
		plugins_url( 'assets/catp-goods.js', CATP_CONNECT_FILE ),
		array(),
		CATP_CONNECT_VERSION,
		true
	);
}

/**
 * Every published Product, cheapest field access first, grouped by category.
 *
 * @return array<int, array{title:string, size:string, price:float, category:string}>
 */
function catp_connect_goods_items() {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}
	$posts = get_posts(
		array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
	$items = array();
	foreach ( $posts as $post ) {
		$items[] = array(
			'title'    => get_the_title( $post ),
			'size'     => (string) get_field( 'product_size', $post->ID ),
			'price'    => (float) get_field( 'product_price', $post->ID ),
			'category' => (string) get_field( 'product_category', $post->ID ),
		);
	}
	// Print materials first, merch last — the order the prototype shows and
	// the order the MERCH divider assumes.
	usort(
		$items,
		function ( $a, $b ) {
			$rank = function ( $c ) {
				return 'merch' === $c ? 1 : 0;
			};
			$diff = $rank( $a['category'] ) - $rank( $b['category'] );
			return $diff ? $diff : strcasecmp( $a['title'], $b['title'] );
		}
	);
	return $items;
}

/** Human label for a category slug, falling back to the slug itself. */
function catp_connect_goods_category_label( $slug ) {
	$labels = array(
		'print_material' => 'Print materials',
		'merch'          => 'Merch',
	);
	return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( '_', ' ', $slug ) );
}

/**
 * [catp_goods_calculator]
 *
 * Attributes:
 *   currency   Symbol to print before each amount. Default "$".
 *   note       The small print under the total. Pass note="" to drop it.
 *   heading    Set to "no" to leave out the eyebrow + title (if the page
 *              already has its own).
 */
add_shortcode( 'catp_goods_calculator', 'catp_connect_goods_calculator_shortcode' );
function catp_connect_goods_calculator_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'currency' => '$',
			'note'     => 'Pricing is a current best estimate.',
			'heading'  => 'yes',
		),
		$atts,
		'catp_goods_calculator'
	);

	$items = catp_connect_goods_items();
	if ( empty( $items ) ) {
		// Nothing published yet: say so to an editor, stay silent for a visitor.
		return current_user_can( 'edit_posts' )
			? '<p class="catp-note">Goods calculator: no published Products yet. Add them under Products, or press <strong>App Settings → Setup Tools → Load starter catalog</strong>.</p>'
			: '';
	}

	wp_enqueue_script( 'catp-goods' );

	$currency = $atts['currency'];
	$zero     = esc_html( $currency . '0.00' );

	ob_start();
	?>
	<section class="catp-form catp-kit" data-catp-goods data-catp-currency="<?php echo esc_attr( $currency ); ?>">
		<?php if ( 'no' !== $atts['heading'] ) : ?>
			<p class="catp-eyebrow">CURRENT ESTIMATE</p>
			<h3>Build your supply list.</h3>
			<p class="catp-form-description">Adjust quantities, then request your list for admin preparation.</p>
		<?php endif; ?>

		<div class="catp-kit-list">
			<?php
			$previous_category = null;
			foreach ( $items as $index => $item ) :
				$show_divider      = ( null !== $previous_category && $item['category'] !== $previous_category );
				$previous_category = $item['category'];
				?>
				<div class="catp-kit-row" data-catp-item data-catp-price="<?php echo esc_attr( number_format( $item['price'], 2, '.', '' ) ); ?>">
					<?php if ( $show_divider ) : ?>
						<p class="catp-kit-divider"><?php echo esc_html( strtoupper( catp_connect_goods_category_label( $item['category'] ) ) ); ?></p>
					<?php endif; ?>
					<div class="catp-kit-copy">
						<strong><?php echo esc_html( $item['title'] ); ?></strong>
						<?php if ( '' !== $item['size'] ) : ?>
							<small><?php echo esc_html( $item['size'] ); ?></small>
						<?php endif; ?>
					</div>
					<div class="catp-qty">
						<button type="button" data-catp-step="-1" aria-label="<?php echo esc_attr( 'Decrease ' . $item['title'] ); ?>">&minus;</button>
						<output data-catp-qty aria-live="polite" aria-label="<?php echo esc_attr( 'Quantity of ' . $item['title'] ); ?>">0</output>
						<button type="button" data-catp-step="1" aria-label="<?php echo esc_attr( 'Increase ' . $item['title'] ); ?>">+</button>
					</div>
					<strong class="catp-kit-subtotal" data-catp-subtotal><?php echo $zero; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></strong>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="catp-kit-total">
			<span>Estimated total</span>
			<strong data-catp-total><?php echo $zero; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></strong>
		</div>

		<?php if ( '' !== $atts['note'] ) : ?>
			<p class="catp-kit-note"><?php echo esc_html( $atts['note'] ); ?></p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
