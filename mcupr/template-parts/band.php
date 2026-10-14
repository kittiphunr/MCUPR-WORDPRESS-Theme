<?php
/**
 * Page band for listings: tint background, wheel watermark, title, description and category chips.
 * Args: title (string), description (html), chips (bool)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$chips = ! isset( $args['chips'] ) || $args['chips'];
?>
<header class="mcu-wheel bg-tint py-10">
	<div class="wrap">
		<?php mcupr_breadcrumb( false ); ?>
		<h1 class="mcu-title mt-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php echo isset( $args['title'] ) ? $args['title'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<div class="mt-3 max-w-3xl text-muted"><?php echo wp_kses_post( $args['description'] ); ?></div>
		<?php endif; ?>
		<?php if ( $chips ) : ?>
			<?php
			$posts_page = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );
			$cur        = is_category() ? get_queried_object_id() : 0;
			?>
			<nav class="mt-6" aria-label="<?php echo esc_attr( mcupr_t( 'news_categories' ) ); ?>">
				<ul class="m-0 flex list-none flex-wrap gap-2 p-0">
					<li><a class="inline-flex min-h-11 items-center rounded-full border-2 border-line bg-bg px-4 font-semibold text-fg no-underline hover:border-primary aria-[current]:border-primary aria-[current]:bg-primary aria-[current]:text-on-primary" href="<?php echo esc_url( $posts_page ); ?>" <?php echo $cur || ! is_home() ? '' : 'aria-current="page"'; ?>><?php mcupr_e( 'all_cats' ); ?></a></li>
					<?php foreach ( get_categories( array( 'hide_empty' => true ) ) as $c ) : ?>
						<li><a class="inline-flex min-h-11 items-center rounded-full border-2 border-line bg-bg px-4 font-semibold text-fg no-underline hover:border-primary aria-[current]:border-primary aria-[current]:bg-primary aria-[current]:text-on-primary" href="<?php echo esc_url( get_category_link( $c ) ); ?>" <?php echo (int) $c->term_id === $cur ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $c->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</header>
