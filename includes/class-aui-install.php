<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation-time setup: seed the default dictionary/settings, and migrate
 * data left behind by the original "Acronyms 2" plugin (option keys
 * acronym_acronyms / acronym_content / acronym_comments) if present, so
 * switching to this plugin doesn't lose an existing dictionary.
 */
class AUI_Install {

	const DB_VERSION_KEY = 'aui_db_version';

	public static function activate() {
		self::maybe_migrate_legacy_data();

		if ( false === get_option( AUI_Repository::OPTION_KEY ) ) {
			add_option( AUI_Repository::OPTION_KEY, self::default_acronyms() );
			update_option( self::DB_VERSION_KEY, AUI_VERSION );
		}

		self::maybe_upgrade();

		if ( false === get_option( AUI_Settings::OPTION_KEY ) ) {
			add_option( AUI_Settings::OPTION_KEY, AUI_Settings::defaults() );
		}
	}

	private static function maybe_migrate_legacy_data() {
		if ( get_option( 'aui_migrated_from_legacy' ) ) {
			return;
		}

		$legacy_acronyms = get_option( 'acronym_acronyms' );
		if ( is_array( $legacy_acronyms ) && ! empty( $legacy_acronyms ) ) {
			update_option( AUI_Repository::OPTION_KEY, $legacy_acronyms );
		}

		$legacy_content  = get_option( 'acronym_content' );
		$legacy_comments = get_option( 'acronym_comments' );

		if ( false !== $legacy_content || false !== $legacy_comments ) {
			$settings = AUI_Settings::defaults();
			if ( false !== $legacy_content ) {
				$settings['enable_content'] = (int) $legacy_content;
			}
			if ( false !== $legacy_comments ) {
				$settings['enable_comments'] = (int) $legacy_comments;
			}
			update_option( AUI_Settings::OPTION_KEY, $settings );
		}

		update_option( 'aui_migrated_from_legacy', 1 );
	}

	/**
	 * Chat slang that shipped in the 1.0.0 dictionary and is no longer part
	 * of the defaults (the dictionary is limited to technical and product
	 * terms since 1.1.0).
	 */
	const REMOVED_IN_1_1 = array(
		'AFAIK', 'ASAP', 'BTW', 'EOM', 'FUD', 'IANAL', 'IIRC', 'IMHO', 'IMO', 'IOW', 'IRL',
		'LOL', 'OMG', 'OTOH', 'PITA', 'ROFL', 'ROFLMAO', 'RTFM', 'TIA', 'WTF', 'YMMV',
	);

	/**
	 * Bring an existing dictionary up to the current defaults without
	 * clobbering the site owner's edits. Runs once per version bump.
	 *
	 * From 1.0.x: entries still holding their untouched 1.0.0 value get the
	 * new "Expansion — explanation" text (or are dropped if they were chat
	 * slang); brand-new terms are added; edited entries, custom entries and
	 * defaults the owner deleted are left as they are.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::DB_VERSION_KEY );
		if ( AUI_VERSION === $installed ) {
			return;
		}

		$current = get_option( AUI_Repository::OPTION_KEY );

		if ( is_array( $current ) && ( false === $installed || version_compare( $installed, '1.1.0', '<' ) ) ) {
			$legacy   = require AUI_PLUGIN_DIR . 'includes/data/legacy-defaults-1.0.php';
			$defaults = self::default_acronyms();

			foreach ( $legacy as $acronym => $old_text ) {
				$untouched = isset( $current[ $acronym ] ) && $current[ $acronym ] === $old_text;
				if ( ! $untouched ) {
					continue;
				}
				if ( in_array( $acronym, self::REMOVED_IN_1_1, true ) ) {
					unset( $current[ $acronym ] );
				} elseif ( isset( $defaults[ $acronym ] ) ) {
					$current[ $acronym ] = $defaults[ $acronym ];
				}
			}

			foreach ( array_diff_key( $defaults, $legacy, $current ) as $acronym => $text ) {
				$current[ $acronym ] = $text;
			}

			update_option( AUI_Repository::OPTION_KEY, $current );
		}

		update_option( self::DB_VERSION_KEY, AUI_VERSION );
	}

	/**
	 * Default dictionary: "Expansion — explanation" per term, split by topic
	 * into includes/data/defaults-*.php.
	 *
	 * @return array<string,string>
	 */
	public static function default_acronyms(): array {
		$dir = AUI_PLUGIN_DIR . 'includes/data/';

		return array_merge(
			require $dir . 'defaults-general.php',
			require $dir . 'defaults-dev.php',
			require $dir . 'defaults-marketing.php',
			require $dir . 'defaults-ai.php'
		);
	}
}
