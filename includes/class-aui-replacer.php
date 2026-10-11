<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag-aware acronym replacement.
 *
 * The original plugin ran a single preg_replace with lookarounds directly
 * against the raw HTML, which only avoids matching *inside a tag* — not
 * inside the *text* of a heading or a link. Here the HTML is split into
 * alternating tag/text tokens and acronyms are substituted only inside text
 * tokens, and only while we are not currently inside one of SKIP_TAGS
 * (headings, links, code blocks, an existing <abbr>, etc).
 */
class AUI_Replacer {

	const SKIP_TAGS = array(
		'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'abbr',
		'script', 'style', 'code', 'pre', 'kbd', 'textarea',
		'select', 'option', 'button', 'template', 'title',
	);

	/** @var array<string,string> */
	private static $acronyms_map = array();

	/** @var int 0 = unlimited */
	private static $max_highlights = 0;

	/** @var array<string,int> occurrences already highlighted this request, per acronym */
	private static $seen_counts = array();

	public static function init() {
		$settings = AUI_Settings::get_all();

		if ( ! empty( $settings['enable_content'] ) ) {
			add_filter( 'the_content', array( __CLASS__, 'replace' ), 20 );
		}
		if ( ! empty( $settings['enable_comments'] ) ) {
			add_filter( 'comment_text', array( __CLASS__, 'replace' ), 20 );
		}
	}

	public static function replace( string $html ): string {
		$acronyms = AUI_Repository::get_all();
		if ( empty( $acronyms ) ) {
			return $html;
		}

		$pattern = self::build_pattern( $acronyms );
		if ( null === $pattern ) {
			return $html;
		}

		self::$acronyms_map   = $acronyms;
		self::$max_highlights = (int) AUI_Settings::get( 'max_highlights', 1 );

		$tokens = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( false === $tokens ) {
			return $html;
		}

		$skip_tags  = apply_filters( 'aui_skip_tags', self::SKIP_TAGS );
		$skip_depth = 0;

		foreach ( $tokens as $index => $token ) {
			if ( '<' === $token[0] ) {
				if ( preg_match( '/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9]*)/', $token, $m ) ) {
					$tag_name = strtolower( $m[2] );
					if ( in_array( $tag_name, $skip_tags, true ) ) {
						$skip_depth += ( '/' === $m[1] ) ? -1 : 1;
						$skip_depth  = max( 0, $skip_depth );
					}
				}
				continue;
			}

			if ( 0 === $skip_depth ) {
				$tokens[ $index ] = preg_replace_callback( $pattern, array( __CLASS__, 'render_match' ), $token );
			}
		}

		return implode( '', $tokens );
	}

	/**
	 * One alternation of all acronyms (longest first, so e.g. "IE6" wins
	 * over "IE"), applied in a single pass instead of one preg_replace per
	 * acronym per render.
	 */
	private static function build_pattern( array $acronyms ): ?string {
		$keys = array_keys( $acronyms );
		if ( empty( $keys ) ) {
			return null;
		}

		usort(
			$keys,
			function ( $a, $b ) {
				return mb_strlen( $b ) <=> mb_strlen( $a );
			}
		);

		$escaped = array_map(
			function ( $key ) {
				return preg_quote( $key, '/' );
			},
			$keys
		);

		return '/\b(' . implode( '|', $escaped ) . ')\b/u';
	}

	private static function render_match( array $m ): string {
		$acronym = $m[1];
		if ( ! isset( self::$acronyms_map[ $acronym ] ) ) {
			return $acronym;
		}

		if ( self::$max_highlights > 0 ) {
			$count = self::$seen_counts[ $acronym ] ?? 0;
			if ( $count >= self::$max_highlights ) {
				return $acronym;
			}
			self::$seen_counts[ $acronym ] = $count + 1;
		}

		$fulltext = self::$acronyms_map[ $acronym ];
		$html     = sprintf(
			'<abbr class="aui-acronym" title="%1$s" tabindex="0">%2$s</abbr>',
			esc_attr( $fulltext ),
			esc_html( $acronym )
		);

		return apply_filters( 'aui_acronym_html', $html, $acronym, $fulltext );
	}
}
