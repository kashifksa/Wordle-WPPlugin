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
		$date   = $request['date'];
		$format = $request->get_param( 'format' ) ?: 'social'; // 'social' (landscape) or 'mobile' (portrait)
		$locale = $request->get_param( 'locale' ) ?: 'global';

		$puzzle = Wordle_DB::get_puzzle_by_date( $date, $locale );

		if ( ! $puzzle ) {
			error_log( "Wordle Image Generator: Puzzle not found for date $date" );
			wp_die( 'Puzzle not found for this date.' );
		}

		error_log( "Wordle Image Generator: Serving image for $date, format: $format" );
		$file_path = self::generate_card( $puzzle, $format );
		
		if ( $file_path && file_exists( $file_path ) ) {
			header( 'Content-Type: image/png' );
			header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path );
		} else {
			error_log( "Wordle Image Generator: Failed to generate/find file: $file_path" );
			wp_die( 'Failed to generate image.' );
		}
		exit;
	}

	/**
	 * Generates and SAVES a hint card.
	 */
	public static function generate_card( $puzzle, $format = 'social' ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$is_mobile = ( $format === 'mobile' );
		$width     = $is_mobile ? 1080 : 1200;
		$height    = $is_mobile ? 1920 : 630;
		
		$upload_dir = wp_upload_dir();
		$base_path  = $upload_dir['basedir'] . '/wordle-cards';
		if ( ! file_exists( $base_path ) ) {
			if ( ! wp_mkdir_p( $base_path ) ) {
				error_log( "Wordle Image Generator: Failed to create directory $base_path" );
				return false;
			}
		}

		$filename = "wordle-hints-{$puzzle['date']}-{$format}.png";
		$file_path = $base_path . '/' . $filename;

		// Skip generation if already exists (unless specifically asked to refresh)
		if ( file_exists( $file_path ) && ! isset( $_GET['refresh'] ) ) {
			return $file_path;
		}

		$image = imagecreatetruecolor( $width, $height );
		if ( ! $image ) {
			error_log( "Wordle Image Generator: imagecreatetruecolor failed for {$width}x{$height}" );
			return false;
		}

		// --- COLORS ---
		$bg_color      = imagecolorallocate( $image, 18, 18, 19 ); // Wordle Dark Bg
		$card_bg       = imagecolorallocate( $image, 33, 33, 34 ); // Slightly lighter card
		$text_white    = imagecolorallocate( $image, 255, 255, 255 );
		$text_muted    = imagecolorallocate( $image, 129, 131, 132 );
		$accent_gold   = imagecolorallocate( $image, 201, 180, 88 );
		$accent_green  = imagecolorallocate( $image, 106, 170, 100 );
		$border_color  = imagecolorallocate( $image, 58, 58, 60 );

		// 1. Fill Background
		imagefill( $image, 0, 0, $bg_color );

		// 2. Add Premium "Glass" Border for Mobile
		if ( $is_mobile ) {
			imagefilledrectangle( $image, 0, 0, $width, 200, $card_bg ); // Header block
			imagefilledrectangle( $image, 0, $height - 150, $width, $height, $card_bg ); // Footer block
		}

		// 3. Draw Side Accent Line
		imagefilledrectangle( $image, 0, 0, 10, $height, $accent_gold );

		// 4. Content Placement Logic
		$margin_left = $is_mobile ? 80 : 100;
		$y = $is_mobile ? 120 : 70;

		// Header: Title
		imagestring( $image, 5, $margin_left, $y, "WORDLE HINT PRO", $accent_gold );
		$y += $is_mobile ? 60 : 35;
		
		imagestring( $image, 5, $margin_left, $y, "Puzzle #" . $puzzle['puzzle_number'] . " - " . date( 'F j, Y', strtotime( $puzzle['date'] ) ), $text_white );
		$y += $is_mobile ? 150 : 80;

		// Hints Section
		$hints = array(
			"THE THEME"      => $puzzle['hint1'],
			"THE CATEGORY"   => $puzzle['hint2'],
			"THE CLUE"       => $puzzle['hint3'],
			"THE FINAL WORD" => $puzzle['final_hint'],
		);

		foreach ( $hints as $label => $text ) {
			if ( empty( $text ) ) continue;

			// Draw a small bullet/indicator
			imagefilledrectangle( $image, $margin_left - 30, $y + 5, $margin_left - 20, $y + 15, $accent_green );
			
			imagestring( $image, 5, $margin_left, $y, $label, $accent_gold );
			$y += $is_mobile ? 50 : 30;
			
			// Wrap text based on width
			$wrap_width = $is_mobile ? 60 : 85;
			$wrapped = wordwrap( $text, $wrap_width, "\n" );
			$lines = explode( "\n", $wrapped );
			foreach ( $lines as $line ) {
				imagestring( $image, 5, $margin_left, $y, trim( $line ), $text_white );
				$y += $is_mobile ? 40 : 25;
			}
			$y += $is_mobile ? 80 : 35;
		}

		// Stats Badge (Difficulty / Avg)
		if ( ! $is_mobile ) {
			$y = $height - 80;
			$stats_text = "Difficulty: " . ( $puzzle['difficulty'] ?: 'N/A' ) . " | Global Avg: " . ( $puzzle['average_guesses'] ?: 'N/A' ) . " guesses";
			imagestring( $image, 5, $margin_left, $y, $stats_text, $text_muted );
		} else {
			$y = $height - 110;
			imagestring( $image, 5, $margin_left, $y, "Difficulty: " . $puzzle['difficulty'], $text_white );
			imagestring( $image, 5, $width - 400, $y, "Avg Guesses: " . $puzzle['average_guesses'], $text_white );
		}

		// Footer Branding
		$brand_y = $is_mobile ? $height - 60 : $height - 50;
		$brand_link = str_replace( array( 'http://', 'https://' ), '', home_url() );
		imagestring( $image, 5, $is_mobile ? $margin_left : $width - 350, $brand_y, "Hints at: " . $brand_link, $accent_green );

		// Save and Cleanup
		$saved = imagepng( $image, $file_path );
		imagedestroy( $image );

		if ( ! $saved ) {
			error_log( "Wordle Image Generator: imagepng failed to save to $file_path" );
			return false;
		}

		return $file_path;
	}
}
