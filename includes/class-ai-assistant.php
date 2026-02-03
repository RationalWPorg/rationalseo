<?php
/**
 * RationalSEO AI Assistant Class
 *
 * Handles AI-powered keyword suggestion and description generation.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_AI_Assistant {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * OpenAI API endpoint.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Model to use for completions.
	 *
	 * @var string
	 */
	const MODEL = 'gpt-4o-mini';

	/**
	 * Constructor.
	 *
	 * @param RationalSEO_Settings $settings Settings instance.
	 */
	public function __construct( RationalSEO_Settings $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_rationalseo_suggest_keyword', array( $this, 'ajax_suggest_keyword' ) );
		add_action( 'wp_ajax_rationalseo_generate_description', array( $this, 'ajax_generate_description' ) );
	}

	/**
	 * Check if AI features are available.
	 *
	 * @return bool
	 */
	public function is_available() {
		$api_key = $this->settings->get_decrypted( 'openai_api_key' );
		return ! empty( $api_key );
	}

	/**
	 * AJAX handler for suggesting a focus keyword.
	 */
	public function ajax_suggest_keyword() {
		check_ajax_referer( 'rationalseo_meta_box', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( empty( $content ) && empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'No content to analyze.', 'rationalseo' ) ) );
		}

		// Strip HTML and limit content length for API.
		$plain_content = wp_strip_all_tags( $content );
		$plain_content = preg_replace( '/\s+/', ' ', $plain_content );
		$plain_content = trim( $plain_content );

		// Limit to ~2000 characters to control token usage.
		if ( strlen( $plain_content ) > 2000 ) {
			$plain_content = substr( $plain_content, 0, 2000 ) . '...';
		}

		$prompt = "Analyze the following content and suggest ONE focus keyword or keyphrase (2-4 words) that this content should rank for in search engines. The keyword should be specific, searchable, and represent the main topic.\n\n";

		if ( ! empty( $title ) ) {
			$prompt .= "Title: {$title}\n\n";
		}

		$prompt .= "Content: {$plain_content}\n\n";
		$prompt .= "Respond with ONLY the suggested keyword/keyphrase, nothing else. No quotes, no explanation.";

		$response = $this->call_openai( $prompt );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		// Clean up the response.
		$keyword = trim( $response );
		$keyword = trim( $keyword, '"\'.' );

		wp_send_json_success( array( 'keyword' => $keyword ) );
	}

	/**
	 * AJAX handler for generating a meta description.
	 */
	public function ajax_generate_description() {
		check_ajax_referer( 'rationalseo_meta_box', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		if ( empty( $content ) && empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'No content to analyze.', 'rationalseo' ) ) );
		}

		// Strip HTML and limit content length for API.
		$plain_content = wp_strip_all_tags( $content );
		$plain_content = preg_replace( '/\s+/', ' ', $plain_content );
		$plain_content = trim( $plain_content );

		// Limit to ~2000 characters to control token usage.
		if ( strlen( $plain_content ) > 2000 ) {
			$plain_content = substr( $plain_content, 0, 2000 ) . '...';
		}

		$prompt = "Write a compelling meta description (150-160 characters) for the following content. The description should be engaging, include a call to action or value proposition, and accurately summarize the content.\n\n";

		if ( ! empty( $keyword ) ) {
			$prompt .= "IMPORTANT: Naturally include this focus keyword: \"{$keyword}\"\n\n";
		}

		if ( ! empty( $title ) ) {
			$prompt .= "Title: {$title}\n\n";
		}

		$prompt .= "Content: {$plain_content}\n\n";
		$prompt .= "Respond with ONLY the meta description, nothing else. No quotes around it.";

		$response = $this->call_openai( $prompt );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		// Clean up the response.
		$description = trim( $response );
		$description = trim( $description, '"' );

		wp_send_json_success( array( 'description' => $description ) );
	}

	/**
	 * Call the OpenAI API.
	 *
	 * @param string $prompt The prompt to send.
	 * @return string|WP_Error The response text or error.
	 */
	private function call_openai( $prompt ) {
		$api_key = $this->settings->get_decrypted( 'openai_api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'rationalseo' ) );
		}

		$body = array(
			'model'       => self::MODEL,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => 'You are an SEO expert assistant. Provide concise, practical responses.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens'  => 100,
			'temperature' => 0.7,
		);

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$error_message = isset( $data['error']['message'] )
				? $data['error']['message']
				: __( 'API request failed.', 'rationalseo' );

			return new WP_Error( 'api_error', $error_message );
		}

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'empty_response', __( 'No response from API.', 'rationalseo' ) );
		}

		return $data['choices'][0]['message']['content'];
	}
}
