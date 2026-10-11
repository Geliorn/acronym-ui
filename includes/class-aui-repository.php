<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD over the acronym dictionary (option: acronym => full text).
 * Replaces the direct get_option()/update_option() calls scattered through
 * the original plugin's presentation layer.
 */
class AUI_Repository {

	const OPTION_KEY = 'aui_acronyms';

	/** @return array<string,string> */
	public static function get_all(): array {
		return (array) get_option( self::OPTION_KEY, array() );
	}

	public static function get( string $acronym ): ?string {
		$all = self::get_all();
		return $all[ $acronym ] ?? null;
	}

	public static function save( string $acronym, string $fulltext ): bool {
		$acronym  = trim( $acronym );
		$fulltext = trim( $fulltext );

		if ( '' === $acronym || '' === $fulltext ) {
			return false;
		}

		$all              = self::get_all();
		$all[ $acronym ]  = $fulltext;
		update_option( self::OPTION_KEY, $all );

		return true;
	}

	public static function delete( string $acronym ): bool {
		$all = self::get_all();

		if ( ! array_key_exists( $acronym, $all ) ) {
			return false;
		}

		unset( $all[ $acronym ] );
		update_option( self::OPTION_KEY, $all );

		return true;
	}

	/**
	 * @param string[] $acronyms
	 * @return int number of acronyms actually removed
	 */
	public static function delete_many( array $acronyms ): int {
		$all     = self::get_all();
		$deleted = 0;

		foreach ( $acronyms as $acronym ) {
			if ( array_key_exists( $acronym, $all ) ) {
				unset( $all[ $acronym ] );
				++$deleted;
			}
		}

		if ( $deleted > 0 ) {
			update_option( self::OPTION_KEY, $all );
		}

		return $deleted;
	}

	/** @return array<string,string> */
	public static function search( string $term ): array {
		$all = self::get_all();

		if ( '' === $term ) {
			return $all;
		}

		$term = mb_strtolower( $term );

		return array_filter(
			$all,
			function ( $fulltext, $acronym ) use ( $term ) {
				return false !== mb_strpos( mb_strtolower( $acronym ), $term )
					|| false !== mb_strpos( mb_strtolower( $fulltext ), $term );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	public static function count( string $term = '' ): int {
		return count( self::search( $term ) );
	}
}
