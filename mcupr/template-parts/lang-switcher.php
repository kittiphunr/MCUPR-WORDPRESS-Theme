<?php
/**
 * Language switcher (links come from Polylang when it is active).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$cur   = mcupr_lang();
$items = array();
if ( mcupr_has_polylang() ) {
	foreach ( (array) pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) ) as $slug => $l ) {
		$items[ $slug ] = array(
			'label' => $l['name'],
			'html'  => str_replace( '_', '-', $l['locale'] ),
			'url'   => $l['url'],
		);
	}
} else {
	foreach ( mcupr_languages() as $code => $l ) {
		$items[ $code ] = array( 'label' => $l['label'], 'html' => $l['html'], 'url' => mcupr_lang_url( $code ) );
	}
}
?>
<nav class="flex flex-wrap items-center gap-x-2" aria-label="<?php echo esc_attr( mcupr_t( 'language' ) ); ?>">
	<span class="whitespace-nowrap" aria-hidden="true"><?php mcupr_e( 'language' ); ?></span>
	<ul class="m-0 flex list-none flex-wrap gap-1 p-0">
		<?php foreach ( $items as $code => $l ) : ?>
			<li>
				<a class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border-2 border-transparent px-3 text-on-bar no-underline hover:border-on-bar/50 aria-[current]:bg-primary aria-[current]:font-bold aria-[current]:text-on-primary contrast:border-line"
					href="<?php echo esc_url( $l['url'] ); ?>"
					lang="<?php echo esc_attr( $l['html'] ); ?>"
					hreflang="<?php echo esc_attr( $l['html'] ); ?>"
					<?php echo (string) $code === (string) $cur ? 'aria-current="true"' : ''; ?>>
					<?php echo esc_html( $l['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
