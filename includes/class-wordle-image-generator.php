<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Image_Generator {

	/**
	 * Main entry point for the REST API.
	 * Fetches data and outputs the image.
	 */
	public static function serve_image( $request ) {
		$date = $request['date'];
		$locale = $request->get_param( 'locale' ) ?: 'global';

		$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );

		if ( ! $puzzle ) {
			wp_die( 'Puzzle not found for this date.' );
		}

		$file_path = self::generate_share_image( $puzzle );
		
		if ( file_exists( $file_path ) ) {
			header( 'Content-Type: image/png' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path );
		}
		exit;
	}

	/**
	 * Generates and SAVES a 1200x630 OG image to the server.
	 */
	public static function generate_share_image( $puzzle ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			wp_die( 'GD Library is not enabled on this server. Please enable it in php.ini.' );
		}

		$upload_dir = wp_upload_dir();
		$base_path  = $upload_dir['basedir'] . '/wordle-cards';
		$base_url   = $upload_dir['baseurl'] . '/wordle-cards';
		
		// Ensure directory exists
		if ( ! file_exists( $base_path ) ) {
			wp_mkdir_p( $base_path );
		}

		$filename = "wordle-hints-{$puzzle['date']}.png";
		$file_path = $base_path . '/' . $filename;

		// If file already exists, just return its path
		if ( file_exists( $file_path ) ) {
			return $file_path;
		}

		$width  = 1200;
		$height = 630;
		$image  = imagecreatetruecolor( $width, $height );

		// Colors
		$bg_color     = imagecolorallocate( $image, 18, 18, 19 ); // Wordle Dark Bg
		$text_white   = imagecolorallocate( $image, 255, 255, 255 );
		$text_muted   = imagecolorallocate( $image, 129, 131, 132 );
		$accent_gold  = imagecolorallocate( $image, 201, 180, 88 );
		$accent_green = imagecolorallocate( $image, 106, 170, 100 );
		$border_color = imagecolorallocate( $image, 58, 58, 60 );

		// Fill Background
		imagefill( $image, 0, 0, $bg_color );

		// Draw subtle border
		imagerectangle( $image, 0, 0, $width - 1, $height - 1, $border_color );

		// Header: Wordle Hint Pro
		$title = "WORDLE HINT PRO";
		$puzzle_title = "Puzzle #" . $puzzle['puzzle_number'];
		$date_str = date( 'F j, Y', strtotime( $puzzle['date'] ) );

		// Title
		imagestring( $image, 5, 50, 50, $title, $accent_gold );
		imagestring( $image, 5, 50, 80, $puzzle_title . " - " . $date_str, $text_white );

		// Drawing a separator line
		imageline( $image, 50, 120, 1150, 120, $border_color );

		// Hints Section
		$y = 170;
		$hints = array(
			"Hint 1 (Vague): "    => $puzzle['hint1'],
			"Hint 2 (Category): " => $puzzle['hint2'],
			"Hint 3 (Specific): " => $puzzle['hint3'],
		);

		foreach ( $hints as $label => $text ) {
			imagestring( $image, 5, 50, $y, $label, $accent_gold );
			$y += 30;
			
			$wrapped = wordwrap( $text, 80, "\n" );
			$lines = explode( "\n", $wrapped );
			foreach ( $lines as $line ) {
				imagestring( $image, 5, 70, $y, $line, $text_white );
				$y += 25;
			}
			$y += 20;
		}

		// Stats Badge at bottom
		$footer_y = $height - 80;
		imageline( $image, 50, $footer_y - 20, 1150, $footer_y - 20, $border_color );
		
		$stats_text = "Difficulty: " . ($puzzle['difficulty'] ?? 'N/A') . " | Global Avg: " . ($puzzle['average_guesses'] ?? 'N/A') . " guesses";
		imagestring( $image, 5, 50, $footer_y, $stats_text, $text_muted );
		
		$brand_link = "Get daily clues at: " . str_replace( array('http://', 'https://'), '', home_url() );
		imagestring( $image, 5, $width - 550, $footer_y, $brand_link, $accent_green );

		// Save to File
		imagepng( $image, $file_path );
		imagedestroy( $image );

		return $file_path;
	}
}
