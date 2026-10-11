<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Acronyms: acronym list/CRUD + display settings.
 *
 * State-changing actions (add/edit/delete/bulk-delete/settings) go through
 * admin-post.php (or, for bulk delete, a load-{page} handler that runs
 * before any output) — each with its own nonce and a uniform
 * current_user_can( 'manage_options' ) check. The original plugin used GET
 * requests for add/edit and inconsistent capability checks between actions.
 */
class AUI_Admin_Page {

	const MENU_SLUG = 'acronym-ui';

	/** @var string admin page hook suffix, set once the menu is registered */
	private static $page_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_aui_save_acronym', array( __CLASS__, 'handle_save_acronym' ) );
		add_action( 'admin_post_aui_delete_acronym', array( __CLASS__, 'handle_delete_acronym' ) );
		add_action( 'admin_post_aui_save_settings', array( __CLASS__, 'handle_save_settings' ) );
	}

	public static function register_menu() {
		self::$page_hook = add_management_page(
			__( 'Manage Acronyms', 'acronym-ui' ),
			__( 'Acronyms', 'acronym-ui' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);

		// Bulk delete posts back to this same page; process it (and possibly
		// redirect) before admin-header.php sends any output.
		add_action( 'load-' . self::$page_hook, array( __CLASS__, 'handle_bulk_delete' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( self::$page_hook !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'acronym-ui-admin', AUI_PLUGIN_URL . 'assets/css/admin.css', array(), AUI_VERSION );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".aui-color-field").wpColorPicker();});' );
	}

	// --------------------------------------------------------------- handlers

	public static function handle_bulk_delete() {
		$list_table = new AUI_List_Table();

		if ( 'delete' !== $list_table->current_action() ) {
			return;
		}

		check_admin_referer( 'bulk-acronyms' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'acronym-ui' ) );
		}

		$acronyms = isset( $_POST['acronym'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['acronym'] ) ) : array();
		$deleted  = AUI_Repository::delete_many( $acronyms );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::MENU_SLUG,
					'message' => $deleted > 1 ? 'bulk-deleted' : ( $deleted ? 'deleted' : 'not-deleted' ),
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	public static function handle_save_acronym() {
		check_admin_referer( 'aui_save_acronym' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'acronym-ui' ) );
		}

		$acronym  = isset( $_POST['acronym'] ) ? sanitize_text_field( wp_unslash( $_POST['acronym'] ) ) : '';
		$fulltext = isset( $_POST['fulltext'] ) ? sanitize_text_field( wp_unslash( $_POST['fulltext'] ) ) : '';
		$is_edit  = ! empty( $_POST['is_edit'] );

		$saved   = AUI_Repository::save( $acronym, $fulltext );
		$message = $saved ? ( $is_edit ? 'updated' : 'added' ) : ( $is_edit ? 'not-updated' : 'not-added' );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'message' => $message ), admin_url( 'tools.php' ) ) );
		exit;
	}

	public static function handle_delete_acronym() {
		$acronym = isset( $_GET['acronym'] ) ? sanitize_text_field( wp_unslash( $_GET['acronym'] ) ) : '';

		check_admin_referer( 'aui_delete_acronym_' . $acronym );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'acronym-ui' ) );
		}

		$deleted = AUI_Repository::delete( $acronym );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'message' => $deleted ? 'deleted' : 'not-deleted' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	public static function handle_save_settings() {
		check_admin_referer( 'aui_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'acronym-ui' ) );
		}

		$values = array();
		foreach ( array( 'enable_content', 'enable_comments', 'enable_underline', 'enable_icon' ) as $key ) {
			$values[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
		}

		$values['icon_glyph']     = isset( $_POST['icon_glyph'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_glyph'] ) ) : '[?]';
		$values['max_highlights'] = isset( $_POST['max_highlights'] ) ? absint( $_POST['max_highlights'] ) : 1;

		if ( isset( $_POST['underline_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['underline_color'] ) );
			if ( ! empty( $color ) ) {
				$values['underline_color'] = $color;
			}
		}
		if ( isset( $_POST['icon_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['icon_color'] ) );
			if ( ! empty( $color ) ) {
				$values['icon_color'] = $color;
			}
		}

		AUI_Settings::update( $values );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'message' => 'settings-saved' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	// ---------------------------------------------------------------- render

	public static function render_page() {
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
		self::render_notice( $message );

		$editing = array(
			'acronym'  => '',
			'fulltext' => '',
		);
		if ( isset( $_GET['action'], $_GET['acronym'] ) && 'edit' === $_GET['action'] ) {
			$acronym = sanitize_text_field( wp_unslash( $_GET['acronym'] ) );
			$stored  = AUI_Repository::get( $acronym );
			if ( null !== $stored ) {
				$editing = array(
					'acronym'  => $acronym,
					'fulltext' => $stored,
				);
			}
		}

		$list_table = new AUI_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Acronyms', 'acronym-ui' ); ?></h1>

			<div id="col-container">
				<div id="col-right">
					<div class="col-wrap">
						<form method="get">
							<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
							<?php $list_table->search_box( __( 'Search acronyms', 'acronym-ui' ), 'aui-search' ); ?>
						</form>
						<form method="post">
							<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
							<?php wp_nonce_field( 'bulk-acronyms' ); ?>
							<?php $list_table->display(); ?>
						</form>
					</div>
				</div>
				<div id="col-left">
					<div class="col-wrap">
						<?php self::render_acronym_form( $editing ); ?>
						<?php self::render_settings_form(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_notice( string $message ) {
		$success = array(
			'added'          => __( 'Acronym added.', 'acronym-ui' ),
			'updated'        => __( 'Acronym updated.', 'acronym-ui' ),
			'deleted'        => __( 'Acronym deleted.', 'acronym-ui' ),
			'bulk-deleted'   => __( 'Acronyms deleted.', 'acronym-ui' ),
			'settings-saved' => __( 'Settings saved.', 'acronym-ui' ),
		);
		$errors = array( 'not-added', 'not-updated', 'not-deleted' );

		if ( isset( $success[ $message ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $success[ $message ] ) );
		} elseif ( in_array( $message, $errors, true ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__( 'Something went wrong, please check the values and try again.', 'acronym-ui' ) );
		}
	}

	private static function render_acronym_form( array $editing ) {
		$is_edit = '' !== $editing['acronym'];
		?>
		<div class="form-wrap">
			<h2><?php echo $is_edit ? esc_html__( 'Edit Acronym', 'acronym-ui' ) : esc_html__( 'Add Acronym', 'acronym-ui' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aui_save_acronym' ); ?>
				<input type="hidden" name="action" value="aui_save_acronym" />
				<input type="hidden" name="is_edit" value="<?php echo $is_edit ? '1' : '0'; ?>" />

				<div class="form-field form-required">
					<label for="aui-acronym"><?php esc_html_e( 'Acronym', 'acronym-ui' ); ?></label>
					<input type="text" id="aui-acronym" name="acronym" size="20" required
						value="<?php echo esc_attr( $editing['acronym'] ); ?>"
						<?php disabled( $is_edit ); ?> />
					<?php if ( $is_edit ) : ?>
						<input type="hidden" name="acronym" value="<?php echo esc_attr( $editing['acronym'] ); ?>" />
					<?php endif; ?>
					<p><?php esc_html_e( 'The acronym as it appears in your posts.', 'acronym-ui' ); ?></p>
				</div>

				<div class="form-field form-required">
					<label for="aui-fulltext"><?php esc_html_e( 'Full text', 'acronym-ui' ); ?></label>
					<input type="text" id="aui-fulltext" name="fulltext" size="40" required
						value="<?php echo esc_attr( $editing['fulltext'] ); ?>" />
					<p><?php esc_html_e( 'Shown in the tooltip.', 'acronym-ui' ); ?></p>
				</div>

				<p class="submit">
					<?php if ( $is_edit ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'acronym-ui' ); ?></a>
					<?php endif; ?>
					<?php submit_button( $is_edit ? __( 'Update Acronym', 'acronym-ui' ) : __( 'Add Acronym', 'acronym-ui' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	private static function render_settings_form() {
		$s = AUI_Settings::get_all();
		?>
		<div class="form-wrap">
			<h2><?php esc_html_e( 'Display settings', 'acronym-ui' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aui_save_settings' ); ?>
				<input type="hidden" name="action" value="aui_save_settings" />

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Highlight in', 'acronym-ui' ); ?></th>
						<td>
							<label><input type="checkbox" name="enable_content" value="1" <?php checked( $s['enable_content'] ); ?> /> <?php esc_html_e( 'Post content', 'acronym-ui' ); ?></label><br />
							<label><input type="checkbox" name="enable_comments" value="1" <?php checked( $s['enable_comments'] ); ?> /> <?php esc_html_e( 'Comments', 'acronym-ui' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Style', 'acronym-ui' ); ?></th>
						<td>
							<label><input type="checkbox" name="enable_underline" value="1" <?php checked( $s['enable_underline'] ); ?> /> <?php esc_html_e( 'Dotted underline', 'acronym-ui' ); ?></label><br />
							<label>
								<input type="checkbox" name="enable_icon" value="1" <?php checked( $s['enable_icon'] ); ?> />
								<?php esc_html_e( 'Show icon', 'acronym-ui' ); ?>
								<input type="text" name="icon_glyph" value="<?php echo esc_attr( $s['icon_glyph'] ); ?>" size="4" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aui-underline-color"><?php esc_html_e( 'Underline color', 'acronym-ui' ); ?></label></th>
						<td><input type="text" id="aui-underline-color" class="aui-color-field" name="underline_color" value="<?php echo esc_attr( $s['underline_color'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aui-icon-color"><?php esc_html_e( 'Icon color', 'acronym-ui' ); ?></label></th>
						<td><input type="text" id="aui-icon-color" class="aui-color-field" name="icon_color" value="<?php echo esc_attr( $s['icon_color'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aui-max-highlights"><?php esc_html_e( 'Highlight each acronym at most', 'acronym-ui' ); ?></label></th>
						<td>
							<input type="number" id="aui-max-highlights" name="max_highlights" min="0" step="1" style="width:80px"
								value="<?php echo esc_attr( $s['max_highlights'] ); ?>" />
							<?php esc_html_e( 'time(s) per page (0 = no limit)', 'acronym-ui' ); ?>
							<p class="description"><?php esc_html_e( 'E.g. with 1, only the first occurrence of "LLM" on a page is highlighted — repeats stay plain text.', 'acronym-ui' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'acronym-ui' ) ); ?>
			</form>
		</div>
		<?php
	}
}
