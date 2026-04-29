<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Static_Hints {

	/**
	 * A local dictionary of high-quality static hints for common Wordle words.
	 * This acts as a tertiary fallback if both Primary and Fallback AI fail.
	 */
	private static $dictionary = array(
		'RISER' => array(
			'hint1'      => "This word might evoke a feeling of movement or progression.",
			'hint2'      => "People described by this word often work in entertainment or construction.",
			'hint3'      => "In architecture or ballet, this word refers to an upward step or movement.",
			'final_hint' => "You're probably thinking of someone rising to fame or a step in a staircase.",
		),
		'LATCH' => array(
			'hint1'      => "Something you might hold onto or engage for safety.",
			'hint2'      => "A hardware item often found near a door or a gate.",
			'hint3'      => "A mechanical device used in sailing, cabinetry, and security.",
			'final_hint' => "In a window or door, it secures the moving part in place.",
		),
		'BRING' => array(
			'hint1'      => "It can be an action that causes excitement on certain special occasions.",
			'hint2'      => "Something you'd find on a 'Gift Registry' before a wedding or party.",
			'hint3'      => "Imagine attending a gathering and being asked to contribute something.",
			'final_hint' => "The first thing a host might ask you to do with a gift or a dish.",
		),
		'PUFFY' => array(
			'hint1'      => "Something you might feel or look like after eating a very heavy meal.",
			'hint2'      => "A characteristic often associated with a cartoon cloud or a marshmallow.",
			'hint3'      => "A trait commonly found in soft, downy textures and winter jackets.",
			'final_hint' => "Imagine holding a feather pillow or looking at a cumulus cloud.",
		),
		'LIKEN' => array(
			'hint1'      => "This word is used when you want to compare one thing to another.",
			'hint2'      => "It functions as a verb that highlights similarities.",
			'hint3'      => "Often used in literature to create metaphors or analogies.",
			'final_hint' => "To say that something is 'like' something else is to do this.",
		),
		'RURAL' => array(
			'hint1'      => "Relating to the countryside rather than the town.",
			'hint2'      => "The opposite of 'urban' or 'city-like'.",
			'hint3'      => "Evokes images of farms, fields, and open spaces.",
			'final_hint' => "Life away from the hustle and bustle of a metropolitan area.",
		),
		'CROCK' => array(
			'hint1'      => "An earthenware pot or jar used for cooking or storage.",
			'hint2'      => "Also used informally to describe something nonsensical or false.",
			'hint3'      => "You might find one filled with butter or slow-cooking soup.",
			'final_hint' => "Think of a heavy ceramic container or a '____' of lies.",
		),
	);

	/**
	 * Get static hints for a word if it exists in our dictionary.
	 * 
	 * @param string $word
	 * @return array|null
	 */
	public static function get_hints( $word ) {
		$word = strtoupper( trim( $word ) );
		return isset( self::$dictionary[ $word ] ) ? self::$dictionary[ $word ] : null;
	}

	/**
	 * Check if a word exists in our static dictionary.
	 * 
	 * @param string $word
	 * @return bool
	 */
	public static function has_hints( $word ) {
		return isset( self::$dictionary[ strtoupper( trim( $word ) ) ] );
	}
}
