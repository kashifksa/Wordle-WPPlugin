<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_AI {

	public static function generate_hints( $word, $engine = 'default' ) {
		$primary_key   = get_option( 'wordle_hint_ai_api_key' );
		$primary_model = get_option( 'wordle_hint_ai_model', 'llama-3.1-8b-instant' );
		$fallback_key   = get_option( 'wordle_hint_ai_api_key_fallback' );
		$fallback_model = get_option( 'wordle_hint_ai_model_fallback' );

		$result = new WP_Error( 'init', 'No attempts made' );

		// Try Primary AI if selected or default
		if ( $engine === 'default' || $engine === 'primary' ) {
			$result = self::execute_ai_request( $word, $primary_key, $primary_model );
			
			// If Rate Limited, wait 10s and try once more before giving up
			if ( is_wp_error( $result ) && strpos( $result->get_error_message(), '429' ) !== false ) {
				error_log( "Wordle AI: Rate limited. Cooling off for 10s..." );
				sleep( 10 );
				$result = self::execute_ai_request( $word, $primary_key, $primary_model );
			}

			if ( ! is_wp_error( $result ) ) {
				// Safety Check: Ensure the word isn't in the hints
				if ( self::is_word_in_hints( $word, $result ) ) {
					error_log( "Wordle AI Safety: Word '{$word}' found in hints. Retrying after 5s..." );
					sleep( 5 );
					$retry = self::execute_ai_request( $word, $primary_key, $primary_model );

					if ( ! is_wp_error( $retry ) && ! self::is_word_in_hints( $word, $retry ) ) {
						return $retry;
					}
					error_log( "Wordle AI Safety: Retry also unsafe or failed for '{$word}'. Falling back." );
					$result = new WP_Error( 'ai_safety_fail', "AI hints unsafe for word '{$word}' after retry." );
				} else {
					return $result;
				}
			}
			
			// If we are strictly Primary, return whatever we have (error or result)
			if ( $engine === 'primary' ) {
				return $result;
			}
		}

		// Try Fallback AI if selected or if primary failed in default mode
		if ( $engine === 'fallback' || ( $engine === 'default' && $fallback_key ) ) {
			$fallback_result = self::execute_ai_request( $word, $fallback_key, $fallback_model );
			if ( ! is_wp_error( $fallback_result ) ) {
				return $fallback_result;
			}
			
			// If both failed in default mode, combine errors
			if ( $engine === 'default' ) {
				return new WP_Error( 'ai_all_failed', 'Both Primary and Fallback AI failed. Primary: ' . $result->get_error_message() . ' | Fallback: ' . $fallback_result->get_error_message() );
			}
			
			return $fallback_result;
		}

		return $result; 
	}

	public static function test_fallback_connection( $word ) {
		$fallback_key   = get_option( 'wordle_hint_ai_api_key_fallback' );
		$fallback_model = get_option( 'wordle_hint_ai_model_fallback' );

		if ( ! $fallback_key ) {
			return new WP_Error( 'fallback_missing', 'No fallback API key configured' );
		}

		return self::execute_ai_request( $word, $fallback_key, $fallback_model );
	}

	private static function execute_ai_request( $word, $api_key, $model ) {
		$prompt_template = get_option( 'wordle_hint_ai_prompt' );

		if ( ! $api_key || ! $prompt_template ) {
			return new WP_Error( 'ai_config_missing', 'API key or prompt missing' );
		}

		$full_prompt = str_replace( '{{WORD}}', $word, $prompt_template );
		$full_prompt .= "\n\nCRITICAL: Return results in JSON format with keys: hint1, hint2, hint3, final_hint. NEVER reveal the word '{$word}' in any hint.";

		// Support for Gemini native API if key starts with AIza
		if ( strpos( $api_key, 'AIza' ) === 0 ) {
			return self::generate_hints_gemini( $word, $api_key, $model, $full_prompt );
		}

		// Basic support for OpenAI-compatible APIs
		$endpoint = 'https://api.openai.com/v1/chat/completions';
		if ( strpos( $api_key, 'gsk_' ) === 0 ) {
			$endpoint = 'https://api.groq.com/openai/v1/chat/completions';
		}

		$body_args = array(
			'model'    => $model,
			'messages' => array(
				array( 'role' => 'system', 'content' => "You are an elite Wordle hint generator. Your goal is to provide helpful clues for the word '{$word}' without ever using the word itself, its synonyms, or mentioning its length. You MUST output strictly valid JSON." ),
				array( 'role' => 'user', 'content' => $full_prompt ),
			),
		);

		// Only add response_format if not using Groq
		if ( strpos( $api_key, 'gsk_' ) === false ) {
			$body_args['response_format'] = array( 'type' => 'json_object' );
		}

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => json_encode( $body_args ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( $status_code !== 200 ) {
			$error_msg = $body['error']['message'] ?? 'Unknown API Error';
			error_log( "Wordle AI API Error ({$status_code}): " . $error_msg );
			return new WP_Error( 'ai_api_error', "API Error {$status_code}: {$error_msg}" );
		}
		
		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$json_content = self::extract_json( $body['choices'][0]['message']['content'] );
			if ( $json_content && ( ! empty( $json_content['hint1'] ) || ! empty( $json_content['final_hint'] ) ) ) {
				return array(
					'hint1'      => $json_content['hint1'] ?? '',
					'hint2'      => $json_content['hint2'] ?? '',
					'hint3'      => $json_content['hint3'] ?? '',
					'final_hint' => $json_content['final_hint'] ?? '',
				);
			}
		}

		return new WP_Error( 'ai_invalid_data', 'AI returned empty or invalid JSON hints' );
	}

	private static function generate_hints_gemini( $word, $api_key, $model, $prompt ) {
		$model = trim( $model );
		$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
		
		$body_args = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt )
					)
				)
			),
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => json_encode( $body_args ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$body = json_decode( $response_body, true );
		
		if ( $status_code !== 200 ) {
			$error_msg = $body['error']['message'] ?? 'Unknown Gemini Error';
			error_log( "Wordle AI: Gemini API Error ({$status_code}): " . $error_msg );
			return new WP_Error( 'gemini_api_error', "Gemini API Error {$status_code}: {$error_msg}" );
		}

		if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$text = $body['candidates'][0]['content']['parts'][0]['text'];
			$json_content = self::extract_json( $text );
			
			if ( $json_content && ( ! empty( $json_content['hint1'] ) || ! empty( $json_content['final_hint'] ) ) ) {
				return array(
					'hint1'      => $json_content['hint1'] ?? '',
					'hint2'      => $json_content['hint2'] ?? '',
					'hint3'      => $json_content['hint3'] ?? '',
					'final_hint' => $json_content['final_hint'] ?? '',
				);
			}
		}

		error_log( "Wordle AI: Gemini invalid format. Body: " . substr( $response_body, 0, 500 ) );
		return new WP_Error( 'gemini_invalid_data', 'Gemini returned invalid response format or empty hints' );
	}

	private static function extract_json( $string ) {
		// Remove markdown code blocks if present
		$string = preg_replace( '/^```json\s*/i', '', $string );
		$string = preg_replace( '/^```\s*/i', '', $string );
		$string = preg_replace( '/\s*```$/', '', $string );
		$string = trim( $string );

		// Try direct decode
		$json = json_decode( $string, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $json;
		}

		// Fallback: Try to find the first '{' and last '}'
		$start_pos = strpos( $string, '{' );
		$end_pos   = strrpos( $string, '}' );

		if ( $start_pos !== false && $end_pos !== false && $end_pos > $start_pos ) {
			$json_part = substr( $string, $start_pos, $end_pos - $start_pos + 1 );
			$json = json_decode( $json_part, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $json;
			}
		}

		error_log( "Wordle AI: Failed to parse JSON from string: " . substr( $string, 0, 100 ) . "..." );
		return null;
	}
	/**
	 * Enrich dictionary data using AI when MW results are incomplete.
	 */
	public static function enrich_dictionary_data( $word, $current_data = array() ) {
		$missing = array();
		if ( empty( $current_data['definition'] ) || $current_data['definition'] === 'Definition unavailable' ) $missing[] = 'definition';
		if ( empty( $current_data['synonyms'] ) || $current_data['synonyms'] === '[]' ) $missing[] = 'synonyms';
		if ( empty( $current_data['antonyms'] ) || $current_data['antonyms'] === '[]' ) $missing[] = 'antonyms';
		if ( empty( $current_data['example_sentence'] ) ) $missing[] = 'example_sentence';
		if ( empty( $current_data['etymology'] ) ) $missing[] = 'etymology';

		if ( empty( $missing ) ) {
			return $current_data;
		}

		$primary_key   = get_option( 'wordle_hint_ai_api_key' );
		$primary_model = get_option( 'wordle_hint_ai_model', 'llama-3.1-8b-instant' );

		if ( ! $primary_key ) {
			return $current_data;
		}

		$prompt = "For the word '{$word}', provide the following missing dictionary information: " . implode( ', ', $missing ) . ".\n\n";
		$prompt .= "Format: Return strictly JSON with keys: " . implode( ', ', $missing ) . ".\n";
		$prompt .= "- synonyms/antonyms should be arrays.\n";
		$prompt .= "- etymology should be a short history of the word origin.\n";
		$prompt .= "- definition should be concise.\n";

		$endpoint = 'https://api.openai.com/v1/chat/completions';
		if ( strpos( $primary_key, 'gsk_' ) === 0 ) {
			$endpoint = 'https://api.groq.com/openai/v1/chat/completions';
		}

		$body_args = array(
			'model'    => $primary_model,
			'messages' => array(
				array( 'role' => 'system', 'content' => "You are a professional lexicographer. Provide accurate linguistic data in JSON format." ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);

		// Groq-specific: no response_format
		if ( strpos( $primary_key, 'gsk_' ) === false ) {
			$body_args['response_format'] = array( 'type' => 'json_object' );
		}

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $primary_key,
			),
			'body'    => json_encode( $body_args ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			return $current_data;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$ai_data = self::extract_json( $body['choices'][0]['message']['content'] );
			if ( $ai_data ) {
				// Sanitize and merge
				if ( isset( $ai_data['synonyms'] ) ) $current_data['synonyms'] = json_encode( (array) $ai_data['synonyms'] );
				if ( isset( $ai_data['antonyms'] ) ) $current_data['antonyms'] = json_encode( (array) $ai_data['antonyms'] );
				if ( isset( $ai_data['definition'] ) ) $current_data['definition'] = sanitize_text_field( $ai_data['definition'] );
				if ( isset( $ai_data['example_sentence'] ) ) $current_data['example_sentence'] = sanitize_text_field( $ai_data['example_sentence'] );
				if ( isset( $ai_data['etymology'] ) ) $current_data['etymology'] = sanitize_text_field( $ai_data['etymology'] );
			}
		}

		return $current_data;
	}

	private static function is_word_in_hints( $word, $hints ) {
		$word = strtolower( $word );
		foreach ( $hints as $hint ) {
			if ( strpos( strtolower( $hint ), $word ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
