<?php
/**
 * Plugin Name: RationalSEO
 * Plugin URI: https://rationalwp.com/plugins/rationalseo
 * Description: Technical SEO essentials with zero bloat. No dashboards, analytics, content scoring, or frontend assets.
 * Version: 1.0.1
 * Author: RationalWP
 * Author URI: https://rationalwp.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rationalseo
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RATIONALSEO_VERSION', '1.0.1' );
define( 'RATIONALSEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RATIONALSEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RATIONALSEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load shared RationalWP admin menu.
require_once RATIONALSEO_PLUGIN_DIR . 'includes/rationalwp-admin-menu.php';

// Load plugin classes.
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-settings.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-frontend.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-admin.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-term-meta.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-sitemap.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-activator.php';

// Load import system.
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/interface-importer.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/class-import-result.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/class-import-manager.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/class-import-admin.php';

// Load importers.
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/importers/class-yoast-importer.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/importers/class-rankmath-importer.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/importers/class-aioseo-importer.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/importers/class-seopress-importer.php';
require_once RATIONALSEO_PLUGIN_DIR . 'includes/import/importers/class-redirection-importer.php';

require_once RATIONALSEO_PLUGIN_DIR . 'includes/class-rationalseo.php';

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'RationalSEO_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RationalSEO_Activator', 'deactivate' ) );

// Check for database upgrades on every load (runs early, before plugin init).
add_action( 'plugins_loaded', array( 'RationalSEO_Activator', 'maybe_upgrade' ), 5 );

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'RationalSEO', 'get_instance' ) );
