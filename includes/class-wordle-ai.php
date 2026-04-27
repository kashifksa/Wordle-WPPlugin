<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordle_AI {

	public static function generate_hints( $word ) {
		$api_key = get_option( 'wordle_hint_ai_api_key' );
		$model   = get_option( 'wordle_hint_ai_model', 'gpt-3.5-turbo' );
		$prompt  = get_option( 'wordle_hint_ai_prompt' );

		if ( ! $api_key || ! $prompt ) {
			return new WP_Error( 'ai_config_missing', 'AI API key or prompt missing' );
		}

		$full_prompt = str_replace( '{{WORD}}', $word, $prompt );
		$full_prompt .= "\nReturn results in JSON format with keys: hint1, hint2, hint3, final_hint. No direct reveal. Max 12 words per hint.";

		// Basic support for OpenAI-compatible APIs (including Gemini if using a proxy or direct OpenAI)
		// For simplicity, I'll implement the OpenAI Chat Completion format.
		// Auto-detect endpoint based on key
		$endpoint = 'https://api.openai.com/v1/chat/completions';
		if ( strpos( $api_key, 'gsk_' ) === 0 ) {
			$endpoint = 'https://api.groq.com/openai/v1/chat/completions';
		}

		$body_args = array(
			'model'    => $model,
			'messages' => array(
				array( 'role' => 'system', 'content' => 'You are a Wordle hint generator. Output JSON only.' ),
				array( 'role' => 'user', 'content' => $full_prompt ),
			),
		);

		// Only add response_format if not using Groq (some Groq models might be picky, though they mostly support it)
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

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$json_content = json_decode( $body['choices'][0]['message']['content'], true );
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
}
