<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_Dictionary {

	/**
	 * Fetch enrichment data from the Free Dictionary API (Backup).
	 */
	public static function fetch_free_dictionary_enrichment( $word ) {
		$word = strtolower( trim( $word ) );
		$url = "https://api.dictionaryapi.dev/api/v2/entries/en/{$word}";
		
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) return $response;

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! is_array( $json ) || empty( $json[0] ) ) {
			return new WP_Error( 'not_found', 'Word not found in Free Dictionary API' );
		}

		$entry = $json[0];
		$data = array();

		// Origin / Etymology
		if ( ! empty( $entry['origin'] ) ) {
			$data['etymology'] = sanitize_text_field( $entry['origin'] );
		}

		// Pronunciation
		if ( ! empty( $entry['phonetic'] ) ) {
			$data['pronunciation'] = sanitize_text_field( $entry['phonetic'] );
		}

		// Audio (Find first available audio URL)
		if ( ! empty( $entry['phonetics'] ) ) {
			foreach ( $entry['phonetics'] as $p ) {
				if ( ! empty( $p['audio'] ) ) {
					$audio_url = $p['audio'];
					// Add protocol if missing
					if ( strpos( $audio_url, '//' ) === 0 ) $audio_url = 'https:' . $audio_url;
					
					// Download locally for performance
					$local_url = self::download_audio_locally( $audio_url, $word );
					$data['audio_url'] = $local_url ?: $audio_url;
					break;
				}
			}
		}

		// Meanings / Definitions
		if ( ! empty( $entry['meanings'] ) ) {
			$meaning = $entry['meanings'][0];
			$data['part_of_speech'] = $meaning['partOfSpeech'] ?? '';
			
			if ( ! empty( $meaning['definitions'] ) ) {
				$def_obj = $meaning['definitions'][0];
				$data['definition'] = $def_obj['definition'] ?? '';
				$data['example_sentence'] = $def_obj['example'] ?? '';
				
				// Collect synonyms/antonyms from definition level or meaning level
				$syns = ! empty( $def_obj['synonyms'] ) ? $def_obj['synonyms'] : ( $meaning['synonyms'] ?? array() );
				$ants = ! empty( $def_obj['antonyms'] ) ? $def_obj['antonyms'] : ( $meaning['antonyms'] ?? array() );
				
				if ( ! empty( $syns ) ) $data['synonyms'] = json_encode( array_slice( $syns, 0, 15 ) );
				if ( ! empty( $ants ) ) $data['antonyms'] = json_encode( array_slice( $ants, 0, 10 ) );
				
				$data['num_definitions'] = count( $meaning['definitions'] );
			}
		}

		return $data;
	}

	/**
	 * Fetch enrichment data from Merriam-Webster APIs.
	 * 
	 * @param string $word The 5-letter word to lookup.
	 * @return array|WP_Error Enrichment data array or WP_Error on failure.
	 */
	public static function fetch_enrichment( $word ) {
		$word = strtolower( trim( $word ) );
		$dict_key = self::sanitize_key( get_option( 'wordle_mw_dictionary_key' ) );
		$thes_key = self::sanitize_key( get_option( 'wordle_mw_thesaurus_key' ) );

		if ( empty( $dict_key ) ) {
			return new WP_Error( 'missing_api_key', 'Merriam-Webster Dictionary API key is not configured.' );
		}

		$data = array();

		// 1. Fetch from Collegiate Dictionary API
		$dict_response = self::fetch_mw_data( 'collegiate', $word, $dict_key );
		if ( ! is_wp_error( $dict_response ) ) {
			$data = array_merge( $data, self::parse_dictionary_data( $dict_response, $word ) );
		}

		// 2. Fetch from Collegiate Thesaurus API (if key exists)
		if ( ! empty( $thes_key ) ) {
			$thes_response = self::fetch_mw_data( 'thesaurus', $word, $thes_key );
			if ( ! is_wp_error( $thes_response ) ) {
				$data = array_merge( $data, self::parse_thesaurus_data( $thes_response ) );
			}
		}

		return $data;
	}

	/**
	 * Remote GET request to MW API.
	 */
	private static function fetch_mw_data( $ref, $word, $key ) {
		$url = "https://www.dictionaryapi.com/api/v3/references/{$ref}/json/{$word}?key={$key}";
		
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		
		// MW returns plain text error if key is invalid
		if ( $body === 'Invalid API key. Not subscribed for this reference.' ) {
			return new WP_Error( 'invalid_api_key', 'Merriam-Webster: Invalid API key.' );
		}

		$json = json_decode( $body, true );

		if ( ! is_array( $json ) ) {
			return new WP_Error( 'invalid_json', 'Invalid response from Merriam-Webster API' );
		}

		// MW returns strings in the array if the word isn't found (suggestions)
		if ( ! empty( $json ) && ! is_array( $json[0] ) ) {
			return new WP_Error( 'word_not_found', 'Word not found in Merriam-Webster' );
		}

		return $json;
	}

	/**
	 * Parse Dictionary API response.
	 */
	private static function parse_dictionary_data( $json, $word ) {
		$entry = $json[0] ?? array();
		if ( empty( $entry ) ) return array();

		$data = array();

		// Part of Speech
		$data['part_of_speech'] = $entry['fl'] ?? '';

		// Definition (First shortdef)
		if ( ! empty( $entry['shortdef'] ) ) {
			$data['definition'] = $entry['shortdef'][0];
			$data['num_definitions'] = count( $entry['shortdef'] );
			$data['definitions_json'] = json_encode( $entry['shortdef'] );
		} elseif ( ! empty( $entry['cxs'] ) ) {
			$data['definition'] = self::clean_mw_text( ($entry['cxs'][0]['pcl'] ?? '') . ' ' . ($entry['cxs'][0]['xt'] ?? '') );
		} else {
			$data['definition'] = 'Definition details available in dictionary archives.';
		}

		// Pronunciation
		if ( ! empty( $entry['hwi']['prs'] ) ) {
			$prs = $entry['hwi']['prs'][0];
			$data['pronunciation'] = $prs['mw'] ?? '';
			
			if ( ! empty( $prs['sound']['audio'] ) ) {
				$audio = $prs['sound']['audio'];
				$subdir = self::get_audio_subdir( $audio );
				$remote_url = "https://media.merriam-webster.com/audio/prons/en/us/mp3/{$subdir}/{$audio}.mp3";
				
				// Download locally
				$local_url = self::download_audio_locally( $remote_url, $word );
				$data['audio_url'] = $local_url ?: $remote_url; // Fallback to remote if download fails
			}
		}

		// Etymology
		if ( ! empty( $entry['et'] ) ) {
			// MW etymology is often an array of arrays, we want the text
			$et_text = '';
			foreach ( $entry['et'] as $et_item ) {
				if ( is_array( $et_item ) && $et_item[0] === 'text' ) {
					$et_text .= $et_item[1];
				}
			}
			$data['etymology'] = self::clean_mw_text( $et_text );
		}

		// First Known Use
		$fku = self::clean_mw_text( $entry['date'] ?? '' );
		if ( preg_match( '/^[0-9]{4}$/', $fku ) ) {
			$fku .= ' AD';
		}
		$data['first_known_use'] = $fku;

		// Example Sentence
		if ( ! empty( $entry['def'] ) ) {
			$vis = self::find_first_vis( $entry['def'] );
			if ( $vis ) {
				$data['example_sentence'] = self::clean_mw_text( $vis );
			}
		}

		return $data;
	}

	/**
	 * Parse Thesaurus API response.
	 */
	private static function parse_thesaurus_data( $json ) {
		$entry = $json[0] ?? array();
		if ( empty( $entry ) || ! isset( $entry['meta'] ) ) return array();

		$data = array();

		// Synonyms
		if ( ! empty( $entry['meta']['syns'] ) ) {
			$all_syns = array_merge( ...$entry['meta']['syns'] );
			$data['synonyms'] = json_encode( array_slice( $all_syns, 0, 15 ) );
		}

		// Antonyms
		if ( ! empty( $entry['meta']['ants'] ) ) {
			$all_ants = array_merge( ...$entry['meta']['ants'] );
			$data['antonyms'] = json_encode( array_slice( $all_ants, 0, 10 ) );
		}

		return $data;
	}

	/**
	 * Helper to determine MW audio subdirectory.
	 */
	private static function get_audio_subdir( $audio ) {
		if ( strpos( $audio, 'bix' ) === 0 ) return 'bix';
		if ( strpos( $audio, 'gg' ) === 0 ) return 'gg';
		if ( preg_match( '/^[0-9_\W]/', $audio[0] ) ) return 'number';
		return $audio[0];
	}

	/**
	 * Recursive search for the first 'vis' (verbal illustration) in the 'def' structure.
	 */
	private static function find_first_vis( $def ) {
		if ( ! is_array( $def ) ) return null;

		foreach ( $def as $item ) {
			// Check for MW structure: ["vis", [ {"t": "..." } ]]
			if ( is_array( $item ) && isset( $item[0] ) && $item[0] === 'vis' && ! empty( $item[1] ) ) {
				return $item[1][0]['t'] ?? null;
			}

			// Recursive search
			if ( is_array( $item ) ) {
				$found = self::find_first_vis( $item );
				if ( $found ) return $found;
			}
		}
		return null;
	}

	/**
	 * Clean MW specific tokens like {bc}, {it}, etc.
	 */
	private static function clean_mw_text( $text ) {
		if ( empty( $text ) ) return '';
		
		// Remove tokens like {bc}, {it}, {b}, {phrase}, etc.
		// Patterns like {bc} becomes ":"
		$text = str_replace( '{bc}', ': ', $text );
		
		// Remove other tokens entirely but keep content inside if any
		// e.g. {it}word{/it} -> word
		$text = preg_replace( '/\{[a-z_]+\}/', '', $text );
		$text = preg_replace( '/\{\/[a-z_]+\}/', '', $text );
		
		// Handle link tokens like {a_link|word} or {sx|word||}
		$text = preg_replace( '/\{[a-z_]+\|([^|}]*)[^}]*\}/', '$1', $text );

		// Remove any remaining tokens entirely (e.g. {ds||1||})
		$text = preg_replace( '/\{[^}]*\}/', '', $text );

		return trim( $text );
	}

	/**
	 * Helper to sanitize API keys (strips URLs if pasted)
	 */
	public static function sanitize_key( $key ) {
		$key = trim( $key );
		if ( strpos( $key, 'http' ) === 0 ) {
			// Extract key from URL like ...?key=XYZ or .../json/word?XYZ
			if ( preg_match( '/[?&](?:key=)?([a-zA-Z0-9-]+)/', $key, $matches ) ) {
				return $matches[1];
			}
			// Try matching the last segment if no query param
			if ( preg_match( '/\/([a-zA-Z0-9-]+)$/', $key, $matches ) ) {
				return $matches[1];
			}
		}
		return $key;
	}

	/**
	 * Download remote audio file and save it locally.
	 */
	private static function download_audio_locally( $url, $word ) {
		$audio_dir = WORDLE_HINT_PATH . 'assets/audio';
		
		// Create directory if it doesn't exist
		if ( ! file_exists( $audio_dir ) ) {
			wp_mkdir_p( $audio_dir );
		}

		$filename = sanitize_title( $word ) . '.mp3';
		$file_path = $audio_dir . '/' . $filename;
		$file_url = WORDLE_HINT_URL . 'assets/audio/' . $filename;

		// Skip if already exists
		if ( file_exists( $file_path ) ) {
			return $file_url;
		}

		// Download the file
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}

		// Save to file
		if ( file_put_contents( $file_path, $body ) ) {
			return $file_url;
		}

		return false;
	}
}
