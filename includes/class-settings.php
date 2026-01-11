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
			'home_title'             => '',
			'home_description'       => '',
			'social_default_image'   => '',
			'twitter_card_type'      => 'summary_large_image',
			'sitemap_enabled'        => true,
			'sitemap_max_age'        => 0,
			'sitemap_exclude_types'  => array(),
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
}
