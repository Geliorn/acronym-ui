<?php
/**
 * Plugin Name: Acronym UI
 * Description: Оборачивает акронимы и сокращения в постах и комментариях в <abbr> с настраиваемой подсказкой (пунктирное подчёркивание, значок [?], клик на мобильных). Переписан на основе плагина Acronyms 2 (Chris Cox) под современный PHP/WordPress.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: llm-agent.ru
 * Text Domain: acronym-ui
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AUI_VERSION', '1.0.0' );
define( 'AUI_PLUGIN_FILE', __FILE__ );
define( 'AUI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AUI_PLUGIN_DIR . 'includes/class-aui-repository.php';
require_once AUI_PLUGIN_DIR . 'includes/class-aui-install.php';
require_once AUI_PLUGIN_DIR . 'includes/class-aui-settings.php';
require_once AUI_PLUGIN_DIR . 'includes/class-aui-replacer.php';

if ( is_admin() ) {
	require_once AUI_PLUGIN_DIR . 'includes/class-aui-list-table.php';
	require_once AUI_PLUGIN_DIR . 'includes/class-aui-admin-page.php';
}

/**
 * Boot the plugin — every class hooks itself in its own init() method.
 */
function aui_boot() {
	load_plugin_textdomain( 'acronym-ui', false, dirname( plugin_basename( AUI_PLUGIN_FILE ) ) . '/languages' );

	AUI_Settings::init();
	AUI_Replacer::init();

	if ( is_admin() ) {
		AUI_Admin_Page::init();
	}
}
add_action( 'plugins_loaded', 'aui_boot' );

/**
 * Activation: seed the default acronym dictionary + settings, migrate data
 * left behind by the original "Acronyms 2" plugin if present.
 */
function aui_activate() {
	AUI_Install::activate();
}
register_activation_hook( __FILE__, 'aui_activate' );
