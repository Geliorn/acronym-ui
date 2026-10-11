<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Acronym listing on the Tools → Acronyms screen: search, sortable column,
 * pagination and bulk delete — replaces the hand-rolled HTML table +
 * pagination markup of the original plugin.
 */
class AUI_List_Table extends WP_List_Table {

	const PER_PAGE = 15;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'acronym',
				'plural'   => 'acronyms',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'acronym' => __( 'Acronym', 'acronym-ui' ),
			'full'    => __( 'Full text', 'acronym-ui' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'acronym' => array( 'acronym', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'acronym-ui' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="acronym[]" value="%s" />', esc_attr( $item['acronym'] ) );
	}

	protected function column_full( $item ) {
		return esc_html( $item['full'] );
	}

	protected function column_acronym( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'    => AUI_Admin_Page::MENU_SLUG,
				'action'  => 'edit',
				'acronym' => rawurlencode( $item['acronym'] ),
			),
			admin_url( 'tools.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'aui_delete_acronym',
					'acronym' => rawurlencode( $item['acronym'] ),
				),
				admin_url( 'admin-post.php' )
			),
			'aui_delete_acronym_' . $item['acronym']
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'acronym-ui' ) ),
			'delete' => sprintf(
				'<a href="%1$s" onclick="return confirm(\'%2$s\');">%3$s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'You are about to delete this acronym. This cannot be undone.', 'acronym-ui' ) ),
				esc_html__( 'Delete', 'acronym-ui' )
			),
		);

		return sprintf( '<strong>%s</strong> %s', esc_html( $item['acronym'] ), $this->row_actions( $actions ) );
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$all    = AUI_Repository::search( $search );

		$items = array();
		foreach ( $all as $acronym => $fulltext ) {
			$items[] = array(
				'acronym' => $acronym,
				'full'    => $fulltext,
			);
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'acronym';
		$order   = isset( $_REQUEST['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'desc' : 'asc';

		usort(
			$items,
			function ( $a, $b ) use ( $orderby, $order ) {
				$result = strnatcasecmp( $a[ $orderby ] ?? '', $b[ $orderby ] ?? '' );
				return 'desc' === $order ? -$result : $result;
			}
		);

		$current_page = $this->get_pagenum();
		$total_items  = count( $items );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total_items / self::PER_PAGE ),
			)
		);

		$this->items = array_slice( $items, ( $current_page - 1 ) * self::PER_PAGE, self::PER_PAGE );
	}
}
