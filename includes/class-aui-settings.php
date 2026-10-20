<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display/behaviour settings, stored as a single option array. Also owns
 * enqueuing the front-end assets and turning the settings into inline CSS
 * custom properties + body classes, so styling stays optional/configurable
 * without touching the stylesheet by hand.
 */
class AUI_Settings {

	const OPTION_KEY = 'aui_settings';

	public static function defaults(): array {
		return array(
			'enable_content'   => 1,
			'enable_comments'  => 1,
			'enable_underline' => 1,
			'enable_icon'      => 1,
			'icon_glyph'       => '[?]',
			// Take underline/icon color from the theme's accent preset; the two
			// colors below are then only the fallback for themes without one.
			'use_theme_accent' => 1,
			'underline_color'  => '#666666',
			'icon_color'       => '#666666',
			// How many times a single acronym gets highlighted per page. 0 = no limit.
			'max_highlights'   => 1,
		);
	}

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	public static function get_all(): array {
		return wp_parse_args( (array) get_option( self::OPTION_KEY, array() ), self::defaults() );
	}

	public static function get( string $key, $default = null ) {
		$all = self::get_all();
		return $all[ $key ] ?? $default;
	}

	public static function update( array $values ) {
		$all = self::get_all();
		foreach ( $values as $key => $value ) {
			$all[ $key ] = $value;
		}
		update_option( self::OPTION_KEY, $all );
	}

	public static function enqueue_frontend_assets() {
		$settings = self::get_all();

		if ( empty( $settings['enable_content'] ) && empty( $settings['enable_comments'] ) ) {
			return;
		}

		wp_enqueue_style( 'acronym-ui', AUI_PLUGIN_URL . 'assets/css/acronym-ui.css', array(), AUI_VERSION );
		wp_enqueue_script( 'acronym-ui', AUI_PLUGIN_URL . 'assets/js/acronym-ui.js', array(), AUI_VERSION, true );

		$underline_color = $settings['underline_color'] ? $settings['underline_color'] : '#666';
		$icon_color      = $settings['icon_color'] ? $settings['icon_color'] : '#666';

		if ( ! empty( $settings['use_theme_accent'] ) ) {
			$underline_color = self::theme_accent_css( $underline_color );
			$icon_color      = self::theme_accent_css( $icon_color );
		}

		$css_vars = sprintf(
			':root{--aui-underline-color:%1$s;--aui-icon-color:%2$s;--aui-icon-glyph:"%3$s";}',
			$underline_color,
			$icon_color,
			addslashes( $settings['icon_glyph'] ? $settings['icon_glyph'] : '[?]' )
		);
		wp_add_inline_style( 'acronym-ui', $css_vars );

		add_filter(
			'body_class',
			function ( $classes ) use ( $settings ) {
				if ( empty( $settings['enable_underline'] ) ) {
					$classes[] = 'aui-no-underline';
				}
				if ( empty( $settings['enable_icon'] ) ) {
					$classes[] = 'aui-no-icon';
				}
				return $classes;
			}
		);
	}

	/**
	 * CSS value resolving to the active theme's accent color. Block themes
	 * expose palette presets as --wp--preset--color--{slug} custom
	 * properties; the slug naming differs between themes, so the common ones
	 * are chained (accent → accent-1 → primary) ending in the configured
	 * color for themes that define none of them. Reading the variable at
	 * render time (instead of resolving a hex in PHP) also follows style
	 * variations and Site Editor palette edits automatically.
	 */
	private static function theme_accent_css( string $fallback ): string {
		$slugs = apply_filters( 'aui_theme_accent_slugs', array( 'accent', 'accent-1', 'primary' ) );

		$css = $fallback;
		foreach ( array_reverse( (array) $slugs ) as $slug ) {
			$css = sprintf( 'var(--wp--preset--color--%s,%s)', sanitize_key( $slug ), $css );
		}

		return $css;
	}
}
