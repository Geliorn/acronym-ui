<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aui_acronyms' );
delete_option( 'aui_settings' );
delete_option( 'aui_migrated_from_legacy' );
delete_option( 'aui_db_version' );
