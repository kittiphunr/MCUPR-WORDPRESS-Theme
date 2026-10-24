<?php
/**
 * A post or page made with Elementor, printed by the theme (inc/elementor.php): the theme's header and footer, the page itself in full
 * width. The title band is shown unless the page says "hide title" or was saved with the "Full width" template of Elementor.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$mcupr_el_id = get_the_ID();
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( ! mcupr_el_hide_title( $mcupr_el_id ) ) : ?>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mcu-title mt-4 text-3xl font-bold min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<div class="band-lead"><?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?></div>
			<?php endif; ?>
		</div>
	</header>
	<?php endif; ?>

	<div class="mcupr-el-wrap" <?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php the_content(); ?>
	</div>

	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="mx-auto mt-12 w-[min(100%-1.5rem,48rem)] min-[400px]:w-[min(100%-2rem,48rem)]">';
		comments_template();
		echo '</div>';
	}
	?>
</article>
	<?php
endwhile;
mcupr_slot( 'content_after' );
get_footer();
