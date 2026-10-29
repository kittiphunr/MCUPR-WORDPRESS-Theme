<?php
/**
 * The search results page: the title in the visitor's language, nothing listed for an empty search, and what the page itself
 * needs (the form on the page with the choice of what to search in, the helpers that search.php uses).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The words searched for, trimmed (what the page says it searched). */
function mcupr_search_words() {
	return trim( (string) get_search_query( false ) );
}

/** "?s=" with nothing in it lists every post in WordPress: here it lists nothing and asks for a word. */
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_search() && '' === trim( (string) $q->get( 's' ) ) ) {
			$q->set( 'post__in', array( 0 ) );
		}
	},
	100
);

/** <title>: "ผลการค้นหา: word – site" in the language of the page (WordPress writes it in English), or "สืบค้น – site" for an empty search. */
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_search() && ! is_admin() ) {
			$w              = mcupr_search_words();
			$parts['title'] = '' === $w ? mcupr_t( 'search_title' ) : mcupr_t( 'search_for' ) . ': ' . $w;
		}
		return $parts;
	},
	30
);

/** The popular words (the same list as the search window): array of strings. */
function mcupr_search_popular() {
	$raw = (string) ( mcupr_text_mod( 'mcupr_popular' ) ? mcupr_text_mod( 'mcupr_popular' ) : mcupr_t( 'popular_terms' ) );
	return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
}

/** The popular words as chips ('' when there are none). */
function mcupr_search_popular_html() {
	$terms = mcupr_search_popular();
	if ( ! $terms ) {
		return '';
	}
	$title = mcupr_text_mod( 'mcupr_popular' ) ? mcupr_t( 'popular' ) : mcupr_t( 'sr_try' );
	$o     = '<section class="sp-pop" aria-label="' . esc_attr( $title ) . '"><h2 class="sp-pop-t">' . esc_html( $title ) . '</h2><ul class="sr-chips">';
	foreach ( $terms as $t ) {
		$o .= '<li><a href="' . esc_url( add_query_arg( 's', rawurlencode( $t ), mcupr_home_url() ) ) . '">' . mcupr_icon( 'search', 16 ) . esc_html( $t ) . '</a></li>';
	}
	return $o . '</ul></section>';
}

/** The form of the page: the words, the search button, and what to search in (news, events, pages) as choices that are the same form. */
function mcupr_search_page_form() {
	$uid  = wp_unique_id( 'sp-' );
	$cur  = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$all  = function_exists( 'mcupr_hero_search_scopes' ) ? mcupr_hero_search_scopes() : array( '' => mcupr_t( 'scope_all' ) );
	$cur  = isset( $all[ $cur ] ) ? $cur : '';
	$role = mcupr_search_role_attr();
	?>
	<form<?php echo $role; // phpcs:ignore WordPress.Security.EscapeOutput ?> method="get" class="sp-form" action="<?php echo esc_url( mcupr_home_url() ); ?>" data-sp>
		<div class="sp-field">
			<label for="<?php echo esc_attr( $uid ); ?>" class="sr-only"><?php mcupr_e( 'search' ); ?></label>
			<?php echo mcupr_icon( 'search', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="search" id="<?php echo esc_attr( $uid ); ?>" name="s" value="<?php echo esc_attr( mcupr_search_words() ); ?>" placeholder="<?php echo esc_attr( mcupr_t( 'hero_search_ph' ) ); ?>" autocomplete="off" enterkeyhint="search">
			<button type="submit" class="sp-go"><?php mcupr_e( 'search' ); ?></button>
		</div>
		<?php if ( isset( $_GET['lang'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<input type="hidden" name="lang" value="<?php echo esc_attr( mcupr_lang() ); ?>">
		<?php endif; ?>
		<fieldset class="sp-chips">
			<legend class="sr-only"><?php mcupr_e( 'scope_t' ); ?></legend>
			<?php foreach ( $all as $val => $label ) : ?>
				<label class="sp-chip"><input type="radio" name="post_type" value="<?php echo esc_attr( $val ); ?>"<?php echo (string) $val === (string) $cur ? ' checked' : ''; ?>><span><?php echo esc_html( $label ); ?></span></label>
			<?php endforeach; ?>
		</fieldset>
	</form>
	<?php
}
