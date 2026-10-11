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

		$css_vars = sprintf(
			':root{--aui-underline-color:%1$s;--aui-icon-color:%2$s;--aui-icon-glyph:"%3$s";}',
			$settings['underline_color'] ? $settings['underline_color'] : '#666',
			$settings['icon_color'] ? $settings['icon_color'] : '#666',
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
}
