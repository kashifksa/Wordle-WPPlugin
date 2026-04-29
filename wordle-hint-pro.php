<?php
/**
 * Plugin Name: Wordle Hint Pro
 * Plugin URI:  https://github.com/yourusername/wordle-hint-pro
 * Description: A premium Wordle hint service with automated scraping, AI-powered hint generation, and a stunning frontend.
 * Version:     1.0.0
 * Author:      Antigravity
 * Author URI:  https://google.com
 * Text Domain: wordle-hint-pro
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants
define( 'WORDLE_HINT_VERSION', '1.0.1' );
define( 'WORDLE_HINT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WORDLE_HINT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Wordle_Hint_Pro {

	/**
	 * Instance of this class.
	 */
	private static $instance = null;

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-db.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-scraper.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-ai.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-static-hints.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-api.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-admin.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-frontend.php';
		require_once WORDLE_HINT_PATH . 'includes/class-wordle-scheduler.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( 'Wordle_DB', 'create_table' ) );
		
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'wordle_hints', array( 'Wordle_Frontend', 'render_hints' ) );
		add_shortcode( 'wordle-hints', array( 'Wordle_Frontend', 'render_hints' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'wordle-hint-style', WORDLE_HINT_URL . 'assets/css/style.css', array(), WORDLE_HINT_VERSION );
		wp_enqueue_script( 'wordle-hint-script', WORDLE_HINT_URL . 'assets/js/script.js', array( 'jquery' ), WORDLE_HINT_VERSION, true );
		
		wp_localize_script( 'wordle-hint-script', 'wordleHintData', array(
			'apiUrl'    => get_rest_url( null, 'wordle/v1/' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => WORDLE_HINT_URL,
		) );
	}
}

// Initialize the plugin.
Wordle_Hint_Pro::get_instance();
