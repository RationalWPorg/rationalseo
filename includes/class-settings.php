<?php
/**
 * RationalSEO Settings Class
 *
 * Handles plugin settings storage and retrieval.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Settings {

	/**
	 * Option name in wp_options.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'rationalseo_settings';

	/**
	 * Cached settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->load_settings();
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'separator'              => '|',
			'site_type'              => 'organization',
			'site_name'              => get_bloginfo( 'name' ),
			'site_logo'              => '',
			'verification_google'    => '',
			'verification_bing'      => '',
			'social_default_image'   => '',
			'twitter_card_type'      => 'summary_large_image',
			'sitemap_enabled'        => true,
			'sitemap_max_age'        => 0,
			'sitemap_exclude_types'  => array(),
			'openai_api_key'         => '',
		);
	}

	/**
	 * Load settings from database.
	 *
	 * @return array
	 */
	private function load_settings() {
		$saved    = get_option( self::OPTION_NAME, array() );
		$defaults = $this->get_defaults();

		// Remove empty string values so they fall back to defaults.
		$saved = array_filter( $saved, function ( $value ) {
			return '' !== $value;
		} );

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		$defaults = $this->get_defaults();
		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->settings;
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	public function set( $key, $value ) {
		$this->settings[ $key ] = $value;
		return $this->save();
	}

	/**
	 * Set multiple settings at once.
	 *
	 * @param array $settings Settings to save.
	 * @return bool
	 */
	public function set_multiple( $settings ) {
		$this->settings = wp_parse_args( $settings, $this->settings );
		return $this->save();
	}

	/**
	 * Save settings to database.
	 *
	 * @return bool
	 */
	private function save() {
		return update_option( self::OPTION_NAME, $this->settings );
	}

	/**
	 * Refresh settings from database.
	 *
	 * @return void
	 */
	public function refresh() {
		$this->settings = $this->load_settings();
	}

	/**
	 * Encrypt a value using AES-256-CBC.
	 *
	 * @param string $value The value to encrypt.
	 * @return string Base64-encoded IV + ciphertext, or empty string on failure.
	 */
	public function encrypt_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$method = 'aes-256-cbc';
		$key    = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $method ) );

		$ciphertext = openssl_encrypt( $value, $method, $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return '';
		}

		// Prepend IV to ciphertext and base64 encode.
		return base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt a value encrypted with encrypt_value().
	 *
	 * @param string $encrypted Base64-encoded IV + ciphertext.
	 * @return string Decrypted value, or empty string on failure.
	 */
	public function decrypt_value( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$data = base64_decode( $encrypted, true );
		if ( false === $data ) {
			return '';
		}

		$method    = 'aes-256-cbc';
		$key       = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv_length = openssl_cipher_iv_length( $method );

		if ( strlen( $data ) < $iv_length ) {
			return '';
		}

		$iv         = substr( $data, 0, $iv_length );
		$ciphertext = substr( $data, $iv_length );

		$decrypted = openssl_decrypt( $ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $decrypted ) {
			return '';
		}

		return $decrypted;
	}

	/**
	 * Get a setting value and decrypt it if it's an encrypted field.
	 *
	 * @param string $key Setting key.
	 * @return string Decrypted value, or empty string if not set.
	 */
	public function get_decrypted( $key ) {
		$encrypted = $this->get( $key, '' );
		if ( empty( $encrypted ) ) {
			return '';
		}

		return $this->decrypt_value( $encrypted );
	}
}
