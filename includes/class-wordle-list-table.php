<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Wordle_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'puzzle',
			'plural'   => 'puzzles',
			'ajax'     => false
		) );
	}

	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'date'          => 'Date',
			'puzzle_number' => 'Puzzle #',
			'word'          => 'Word',
			'difficulty'    => 'Difficulty',
			'avg_guesses'   => 'Avg. Guesses',
			'dictionary'    => 'Dictionary',
			'actions'       => 'Actions'
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'date':
				return '<strong>' . esc_html( $item['date'] ) . '</strong>';
			case 'puzzle_number':
				return esc_html( $item['puzzle_number'] );
			case 'word':
				return '<code style="font-size:1.2em; font-weight:bold; letter-spacing:1px;">' . esc_html( $item['word'] ) . '</code>';
			case 'difficulty':
				$diff = floatval( $item['difficulty'] ?? 0 );
				$label = 'N/A';
				$color = '#999';
				if ($diff > 0) {
					if ($diff < 3.5) { $label = 'Easy'; $color = '#6aaa64'; }
					elseif ($diff < 4.2) { $label = 'Medium'; $color = '#c9b458'; }
					else { $label = 'Hard'; $color = '#787c7e'; }
				}
				return sprintf('<span style="color:%s; font-weight:bold;">%s (%s)</span>', $color, $label, $diff);
			case 'avg_guesses':
				return esc_html( $item['average_guesses'] ?? 'N/A' );
			case 'dictionary':
				$status = ! empty( $item['definition'] ) ? '✅' : '❌';
				return $status . ' ' . esc_html( $item['part_of_speech'] ?? '' );
			case 'actions':
				return sprintf(
					'<a href="?page=%s&action=delete&id=%s&_wpnonce=%s" class="button wh-btn wh-btn-secondary" style="color: #d63638; border-color: #d63638; padding: 2px 8px !important; height: auto !important; line-height: 1.5 !important;" onclick="return confirm(\'Are you sure?\')"><i data-lucide="trash-2" style="width:14px; height:14px;"></i> Delete</a>',
					$_REQUEST['page'],
					$item['id'],
					wp_create_nonce( 'delete_puzzle_' . $item['id'] )
				);
			default:
				return print_r( $item, true );
		}
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id'] );
	}

	public function get_sortable_columns() {
		return array(
			'date'          => array( 'date', true ),
			'puzzle_number' => array( 'puzzle_number', false ),
			'difficulty'    => array( 'difficulty', false )
		);
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = Wordle_DB::get_table_name();
		$per_page = 20;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Handle Delete Action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'delete_puzzle_' . $_GET['id'] );
			$wpdb->delete( $table_name, array( 'id' => intval( $_GET['id'] ) ) );
			// Refresh JSON cache
			if ( class_exists( 'Wordle_API' ) ) { Wordle_API::refresh_json_cache(); }
		}

		// Handle Bulk Actions
		if ( ( isset( $_POST['action'] ) && $_POST['action'] === 'bulk-delete' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] === 'bulk-delete' ) ) {
			check_admin_referer( 'bulk-puzzles' );
			if ( ! empty( $_POST['bulk-delete'] ) && is_array( $_POST['bulk-delete'] ) ) {
				foreach ( $_POST['bulk-delete'] as $id ) {
					$wpdb->delete( $table_name, array( 'id' => intval( $id ) ) );
				}
				if ( class_exists( 'Wordle_API' ) ) { Wordle_API::refresh_json_cache(); }
			}
		}

		// Whitelist to prevent SQL injection
		$allowed_orderby = array( 'date', 'puzzle_number', 'difficulty', 'average_guesses', 'word' );
		$allowed_order   = array( 'ASC', 'DESC' );
		$orderby = ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ) ? $_GET['orderby'] : 'date';
		$order   = ( ! empty( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), $allowed_order, true ) ) ? strtoupper( $_GET['order'] ) : 'DESC';

		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
		$paged = $this->get_pagenum();
		$offset = ( $paged - 1 ) * $per_page;

		$this->items = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
			$per_page,
			$offset
		), ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}

	public function get_bulk_actions() {
		return array(
			'bulk-delete' => 'Delete'
		);
	}
}
