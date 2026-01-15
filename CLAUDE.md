# RationalWP Plugin Development Standards

## Overview
RationalWP is a suite of practical, no-bloat WordPress plugins built for professionals. Each plugin follows strict WordPress.org standards with opinionated defaults and toggleable features.

## Local Development Environment
- All development-related `npm` and `npx` commandds MUST be prefixed with `NODE_ENV=development`

### WordPress
**URL:** https://development.local/wp-admin/
**Username:** claude
**Password:** &FKHRV4znkXt*SWn5k%IYTmN

## Core Architecture

### Singleton Pattern
```php
class PluginName {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('pluginname_options', array());
        $this->options = wp_parse_args($this->options, $this->get_defaults());
        // Hook initialization
    }
}
add_action('plugins_loaded', array('PluginName', 'get_instance'));
```

### Shared Menu System
All RationalWP plugins share a unified "RationalWP" parent menu (position 81, after Settings). Copy `includes/rationalwp-admin-menu.php` from RationalCleanup and add submenu pages:

```php
require_once PLUGIN_DIR . 'includes/rationalwp-admin-menu.php';

add_submenu_page(
    'rationalwp',           // Parent slug
    __('Plugin Name', 'textdomain'),
    __('Plugin Name', 'textdomain'),
    'manage_options',
    'plugin-slug',
    array($this, 'render_settings_page')
);
```

## Plugin Structure
```
pluginname/
├── pluginname.php              # Main file: header + singleton class
├── readme.txt                  # WordPress.org format (required)
├── README.md                   # Development docs
├── LICENSE                     # GPL v2 full text
├── CLAUDE.md                   # AI agent instructions
├── build.sh                    # Distribution build script
├── .distignore                 # Exclude dev files from distribution
├── includes/
│   ├── rationalwp-admin-menu.php   # Shared menu (copy from RationalCleanup)
│   ├── class-*.php            # Plugin classes (if needed)
│   └── import/                # Import system (RationalSEO)
│       ├── interface-importer.php      # Importer contract
│       ├── class-import-result.php     # Result data object
│       ├── class-import-manager.php    # Registry & orchestration
│       ├── class-import-admin.php      # Admin UI & AJAX
│       └── importers/                  # Individual importer implementations
│           └── class-yoast-importer.php
├── assets/
│   ├── css/admin.css          # Admin styles only
│   └── css/import.css         # Import system styles
└── tests/                     # PHPUnit tests (dev only, excluded from dist)
```

## WordPress.org Requirements
- **License**: GPL v2 or later with full LICENSE file
- **Text Domain**: Match plugin slug exactly
- **Translations**: Use `__()`, `esc_html__()`, `esc_attr__()` for all strings
- **Escaping**: `esc_html()`, `esc_attr()`, `esc_url()` on all output
- **Sanitization**: Validate and sanitize all input
- **Capabilities**: `current_user_can('manage_options')` before admin actions
- **No External Dependencies**: No CDN calls, no premium upsells, no tracking

## Settings API Pattern
```php
// Register settings
register_setting('pluginname_options_group', 'pluginname_options', array($this, 'sanitize_options'));

// Add sections and fields
add_settings_section('section_id', __('Section Title', 'textdomain'), 'callback', 'pluginname');
add_settings_field('field_id', __('Field Label', 'textdomain'), 'render_callback', 'pluginname', 'section_id');

// Render form
settings_fields('pluginname_options_group');
do_settings_sections('pluginname');
submit_button();
```

## Philosophy
- **Practical over flashy**: Solve real problems, no bloat
- **Opinionated defaults**: Works great out of the box
- **Toggleable features**: User maintains control
- **Performance-first**: Minimal footprint, no frontend JS
- **Professional**: Clean code, proper standards, no shortcuts

## Build Process
Run `./build.sh` to create distribution zip excluding:
- `.git/`, `tests/`, `vendor/`, `node_modules/`
- `composer.*`, `package*.json`, `phpunit.xml`
- `CLAUDE.md`, `HANDOFF-*.md`, dev docs

## Reference Implementation
See **RationalCleanup** for the canonical implementation: Clean singleton pattern, comprehensive Settings API usage, shared menu integration, and proper WordPress.org structure.

## Import System Architecture (RationalSEO)

### Overview
RationalSEO includes a plugin-agnostic import framework for migrating SEO data from other plugins (Yoast, RankMath, AIOSEO).

### Key Components
- **`RationalSEO_Importer_Interface`**: Contract all importers must implement
- **`RationalSEO_Import_Result`**: Fluent result object with counts and messages
- **`RationalSEO_Import_Manager`**: Registry for importers, fires `rationalseo_register_importers` action
- **`RationalSEO_Import_Admin`**: Admin UI with AJAX handlers

### Creating an Importer
```php
class RationalSEO_Yoast_Importer implements RationalSEO_Importer_Interface {
    public function get_slug() { return 'yoast'; }
    public function get_name() { return 'Yoast SEO'; }
    public function get_description() { return 'Import from Yoast SEO'; }
    public function is_available() { /* check for Yoast data */ }
    public function get_importable_items() { /* return counts */ }
    public function preview( $item_types ) { /* return preview */ }
    public function import( $item_types, $options ) { /* perform import */ }
}

// Register via action hook
add_action( 'rationalseo_register_importers', function( $manager ) {
    $manager->register( new RationalSEO_Yoast_Importer() );
} );
```

### Import Tab
Located at: RationalWP > SEO > Import tab
- Shows available importers as cards with item counts
- Modal workflow: Preview → Select types → Import → Results

### Yoast SEO Importer (Implemented)
The Yoast importer (`class-yoast-importer.php`) handles:

**Post Meta Import:**
| Yoast Key | RationalSEO Key | Notes |
|-----------|-----------------|-------|
| `_yoast_wpseo_title` | `_rationalseo_title` | Direct copy |
| `_yoast_wpseo_metadesc` | `_rationalseo_desc` | Direct copy |
| `_yoast_wpseo_canonical` | `_rationalseo_canonical` | Direct copy |
| `_yoast_wpseo_meta-robots-noindex` | `_rationalseo_noindex` | 1→'1', else skip |
| `_yoast_wpseo_opengraph-image` | `_rationalseo_og_image` | Direct copy |

**Redirects Import (Yoast Premium):**
- Sources: `wpseo-premium-redirects-base`, `wpseo_redirect`, `wpseo-premium-redirects-export-plain`
- Supports plain and regex redirects
- Status codes: 301, 302, 307, 410 (skips 451)

**Settings Import:**
| Yoast Source | Yoast Key | RationalSEO Key |
|--------------|-----------|-----------------|
| `wpseo_titles` | `separator` | `separator` (converts `sc-*` codes) |
| `wpseo_titles` | `title-home-wpseo` | `home_title` (with variable conversion) |
| `wpseo_titles` | `metadesc-home-wpseo` | `home_description` (with variable conversion) |
| `wpseo_social` | `og_default_image` | `social_default_image` |
| `wpseo_social` | `twitter_card_type` | `twitter_card_type` |
| `wpseo_titles` | `company_logo` | `site_logo` |
| `wpseo` | `googleverify` | `verification_google` |
| `wpseo` | `msverify` | `verification_bing` |

**Yoast Variable Conversion:**
Since RationalSEO doesn't support template variables, Yoast variables are converted during import:
- `%%sitename%%`, `%%sitetitle%%` → Site name
- `%%sitedesc%%`, `%%tagline%%` → Site tagline
- `%%sep%%`, `%%separator%%` → Title separator
- `%%currentyear%%`, `%%current_year%%` → Current year
- `%%currentmonth%%`, `%%current_month%%` → Current month
- Post-specific variables (%%title%%, %%excerpt%%, etc.) cause the value to be skipped

**Batch Processing:** Post meta imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.

### Rank Math Importer (Implemented)
The Rank Math importer (`class-rankmath-importer.php`) handles:

**Post Meta Import:**
| Rank Math Key | RationalSEO Key | Notes |
|---------------|-----------------|-------|
| `rank_math_title` | `_rationalseo_title` | Direct copy |
| `rank_math_description` | `_rationalseo_desc` | Direct copy |
| `rank_math_canonical_url` | `_rationalseo_canonical` | Direct copy |
| `rank_math_robots` | `_rationalseo_noindex` | Checks array for 'noindex' |
| `rank_math_facebook_image` | `_rationalseo_og_image` | Direct copy |

**Redirects Import:**
- Source: `wp_rank_math_redirections` database table
- Supports comparison types: exact, regex, contains, start, end
- Contains/start/end patterns are converted to regex
- Status codes: 301, 302, 307, 410

**Settings Import:**
| Rank Math Source | Rank Math Key | RationalSEO Key |
|------------------|---------------|-----------------|
| `rank-math-options-titles` | `title_separator` | `separator` |
| `rank-math-options-titles` | `homepage_title` | `home_title` (with variable conversion) |
| `rank-math-options-titles` | `homepage_description` | `home_description` (with variable conversion) |
| `rank-math-options-titles` | `open_graph_image` | `social_default_image` |
| `rank-math-options-titles` | `twitter_card_type` | `twitter_card_type` |
| `rank-math-options-titles` | `knowledgegraph_logo` | `site_logo` |
| `rank-math-options-general` | `google_verify` | `verification_google` |
| `rank-math-options-general` | `bing_verify` | `verification_bing` |

**Rank Math Variable Conversion:**
Rank Math uses `%variable%` (single percent) syntax. Supported conversions:
- `%sitename%`, `%site_title%` → Site name
- `%sitedesc%` → Site tagline
- `%sep%`, `%separator%` → Title separator
- `%currentyear%`, `%currentmonth%`, `%currentday%`, `%currentdate%` → Date values
- `%currenttime%`, `%currenttime(format)%` → Time values (custom PHP format supported)
- `%org_name%`, `%org_url%`, `%org_logo%` → Organization info from Local SEO settings
- Post-specific variables (%title%, %excerpt%, etc.) cause the value to be skipped

**Important:** Rank Math option names use **hyphens** (`rank-math-options-titles`), not underscores.

**Batch Processing:** Post meta imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.

### AIOSEO Importer (Implemented)
The AIOSEO importer (`class-aioseo-importer.php`) handles:

**Important:** AIOSEO stores post data in custom table `aioseo_posts`, NOT post meta.

**Post Meta Import (from `aioseo_posts` table):**
| AIOSEO Column | RationalSEO Key | Notes |
|---------------|-----------------|-------|
| `title` | `_rationalseo_title` | Direct copy |
| `description` | `_rationalseo_desc` | Direct copy |
| `canonical_url` | `_rationalseo_canonical` | Direct copy |
| `robots_noindex` | `_rationalseo_noindex` | 1→'1' |
| `og_image_custom_url` | `_rationalseo_og_image` | Direct copy |

**Redirects Import:**
- Source: `aioseo_redirects` database table (Pro/Premium feature only)
- Columns: `source_url`, `target_url`, `type`, `regex`, `enabled`
- Status codes: 301, 302, 307, 410

**Settings Import (from `aioseo_options` JSON):**
| AIOSEO Path | RationalSEO Key |
|-------------|-----------------|
| `searchAppearance.global.separator` | `separator` |
| `searchAppearance.global.siteTitle` | `home_title` (with variable conversion) |
| `searchAppearance.global.metaDescription` | `home_description` (with variable conversion) |
| `social.facebook.general.defaultImagePosts` | `social_default_image` |
| `social.twitter.general.defaultCardType` | `twitter_card_type` |
| `searchAppearance.global.schema.organizationLogo` | `site_logo` |
| `webmasterTools.google` | `verification_google` |
| `webmasterTools.bing` | `verification_bing` |

**AIOSEO Variable Conversion:**
AIOSEO uses `#variable` (hash prefix) syntax. Supported conversions:
- `#site_title` → Site name
- `#tagline` → Site tagline
- `#separator_sa` → Title separator
- `#current_year`, `#current_month`, `#current_day`, `#current_date` → Date values
- `#page_number` → Empty (for static content)
- Post-specific variables (#post_title, #post_excerpt, etc.) cause the value to be skipped

**Important:** AIOSEO options are stored as JSON in `aioseo_options`. Use `get_option_value()` helper with dot notation to access nested values.

**Batch Processing:** Post imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.
