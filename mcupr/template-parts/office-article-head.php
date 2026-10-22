<?php
/**
 * The head of a news article that belongs to an office: the colour of the office, the way back to the office's news, the title and
 * the details. Short and calm (no tall dark picture); the cover picture, if any, sits at the top of the article.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$oid = mcupr_office_shown();
$cat = get_the_category();
$cat = $cat ? $cat[0] : null;
?>
<header class="of-article-head" style="<?php echo esc_attr( mcupr_office_style( $oid ) ); ?>"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="wrap">
		<nav class="of-crumbs" aria-label="<?php echo esc_attr( mcupr_t( 'of_nav_news' ) ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( mcupr_office_url( $oid ) ); ?>"><?php echo esc_html( get_the_title( $oid ) ); ?></a></li>
				<li><a href="<?php echo esc_url( mcupr_office_url( $oid, 'news' ) ); ?>"><?php mcupr_e( 'of_news' ); ?></a></li>
				<?php if ( $cat ) : ?>
					<li><a href="<?php echo esc_url( add_query_arg( 'ocat', $cat->slug, mcupr_office_url( $oid, 'news' ) ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li>
				<?php endif; ?>
			</ol>
		</nav>
		<h1 class="of-article-t"><?php the_title(); ?></h1>
		<?php get_template_part( 'template-parts/article-meta' ); ?>
	</div>
</header>
