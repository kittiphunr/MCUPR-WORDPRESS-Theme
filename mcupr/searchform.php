<?php
/**
 * Search form.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$uid = wp_unique_id( 'search-' );
$mcupr_search_role = mcupr_search_role_attr();
?>
<form<?php echo $mcupr_search_role; // phpcs:ignore WordPress.Security.EscapeOutput ?> method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $uid ); ?>" class="screen-reader-text"><?php mcupr_e( 'search' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $uid ); ?>" class="search-form__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( mcupr_t( 'search_ph' ) ); ?>" autocomplete="off">
	<?php if ( isset( $_GET['lang'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<input type="hidden" name="lang" value="<?php echo esc_attr( mcupr_lang() ); ?>">
	<?php endif; ?>
	<button type="submit" class="search-form__btn">
		<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15zM16 16l5 5"/></svg>
		<span class="screen-reader-text"><?php mcupr_e( 'search' ); ?></span>
	</button>
</form>
