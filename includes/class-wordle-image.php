<?php
/**
 * Wordle Image Generator (PHP GD)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Wordle_Image_Generator {

    private static $upload_dir = 'wordle-hint-cards';

    /**
     * Initialize the generator
     */
    public static function init() {
        // Ensure upload directory exists
        $upload = wp_upload_dir();
        $path = $upload['basedir'] . '/' . self::$upload_dir;
        if ( ! file_exists( $path ) ) {
            wp_mkdir_p( $path );
        }
    }

    /**
     * Generate or Get the image URL for a puzzle
     */
    public static function get_puzzle_image( $puzzle ) {
        if ( ! $puzzle ) return '';

        $upload = wp_upload_dir();
        $filename = 'wordle-hint-' . $puzzle['puzzle_number'] . '.png';
        $filepath = $upload['basedir'] . '/' . self::$upload_dir . '/' . $filename;
        $fileurl  = $upload['baseurl'] . '/' . self::$upload_dir . '/' . $filename;

        // If it exists, return it
        if ( file_exists( $filepath ) ) {
            return $fileurl;
        }

        // Otherwise, generate it
        if ( self::generate_image( $puzzle, $filepath ) ) {
            return $fileurl;
        }

        return '';
    }

    /**
     * Internal GD generation logic
     */
    private static function generate_image( $puzzle, $save_path ) {
        if ( ! function_exists( 'imagecreatetruecolor' ) ) return false;

        // 1. Create Canvas (Social Media Size: 1200 x 630)
        $width  = 1200;
        $height = 630;
        $image  = imagecreatetruecolor( $width, $height );

        // 2. Colors
        $white      = imagecolorallocate( $image, 255, 255, 255 );
        $bg_color   = imagecolorallocate( $image, 248, 249, 250 ); // Light Gray
        $text_main  = imagecolorallocate( $image, 33, 37, 41 );
        $text_muted = imagecolorallocate( $image, 108, 117, 125 );
        $accent     = imagecolorallocate( $image, 106, 170, 100 ); // Wordle Green

        // 3. Fill Background
        imagefilledrectangle( $image, 0, 0, $width, $height, $bg_color );

        // 4. Draw a "Premium" Border/Accent
        imagefilledrectangle( $image, 0, 0, 15, $height, $accent ); // Side bar

        // 5. Add Content (Using built-in fonts for initial stability)
        // Note: In a real production environment, we'd use imagettftext() with a custom .ttf font
        
        $font_size = 5; // Built-in font size (1-5)
        
        // Header
        imagestring( $image, 5, 50, 50, "TODAY'S WORDLE HINTS", $text_muted );
        imagestring( $image, 5, 50, 80, "Puzzle #" . $puzzle['puzzle_number'], $accent );
        imagestring( $image, 5, 50, 110, date( 'F j, Y', strtotime( $puzzle['puzzle_date'] ) ), $text_main );

        // Difficulty Badge
        imagestring( $image, 5, 50, 160, "Difficulty: " . $puzzle['difficulty'] . "/5", $text_main );

        // Hints Section
        imagestring( $image, 5, 50, 220, "--- HINTS ---", $text_muted );
        
        $hints = [
            "Hint 1: " . wp_trim_words( $puzzle['hint1'], 10 ),
            "Hint 2: " . wp_trim_words( $puzzle['hint2'], 10 ),
            "Hint 3: " . wp_trim_words( $puzzle['hint3'], 10 )
        ];

        $y = 260;
        foreach ( $hints as $hint ) {
            imagestring( $image, 5, 50, $y, $hint, $text_main );
            $y += 40;
        }

        // Footer / Branding
        imagestring( $image, 5, 50, 550, "TodayWordleHint.com", $accent );
        imagestring( $image, 5, 50, 580, "Premium Wordle Insights & Strategies", $text_muted );

        // 6. Save & Cleanup
        $success = imagepng( $image, $save_path );
        imagedestroy( $image );

        return $success;
    }
}
