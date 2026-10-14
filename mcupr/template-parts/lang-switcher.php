<?php
/**
 * Language choices shown in the language dialog (links come from Polylang when active).
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
<ul class="m-0 flex list-none flex-wrap gap-3 p-0" aria-label="<?php echo esc_attr( mcupr_t( 'language' ) ); ?>">
	<?php foreach ( $items as $code => $l ) : ?>
		<li class="flex-1 basis-24">
			<a class="flex min-h-14 items-center justify-center rounded-2xl border-2 border-line px-4 text-lg text-fg no-underline hover:border-primary aria-[current]:border-primary aria-[current]:bg-primary aria-[current]:font-bold aria-[current]:text-on-primary"
				href="<?php echo esc_url( $l['url'] ); ?>"
				lang="<?php echo esc_attr( $l['html'] ); ?>"
				hreflang="<?php echo esc_attr( $l['html'] ); ?>"
				<?php echo (string) $code === (string) $cur ? 'aria-current="true"' : ''; ?>>
				<?php echo esc_html( $l['label'] ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
