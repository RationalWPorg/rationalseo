# RationalWP Plugin Development Standards

## Overview
RationalWP is a suite of practical, no-bloat WordPress plugins built for professionals. Each plugin follows strict WordPress.org standards with opinionated defaults and toggleable features.

## Local Development Environment
- All development-related `npm` and `npx` commands MUST be prefixed with `NODE_ENV=development`

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

## Frontend Title Defaults

Titles are managed per-page via post meta (`_rationalseo_title`, `_rationalseo_desc`) and per-term via term meta, with these fallbacks:

| Context | Title Default | Description Default |
|---------|--------------|-------------------|
| Front page | `{site name} {sep} {site description}` | Site tagline |
| Blog page | `Blog {sep} {site name}` | Site tagline |
| Singular | `{post title} {sep} {site name}` | Excerpt or auto-generated from content |
| Taxonomy archive | `{term name} {sep} {site name}` | Term description |
| Post type archive | `{post type name} {sep} {site name}` | — |
| Search | `Search Results for "query" {sep} {site name}` | — |
| 404 | `Page Not Found {sep} {site name}` | — |

**Important:** Front page and blog page check `page_on_front` and `page_for_posts` post meta before falling back. Taxonomy archives check term meta before falling back. There are no homepage settings in the admin — title/description are set on the page itself.

## Settings Defaults Behavior

Empty string values in saved settings are filtered out before merging with defaults (`class-settings.php`). This ensures blank fields in the admin UI correctly fall back to defaults (e.g., empty `site_name` falls back to `get_bloginfo('name')`).

## Import System Architecture (RationalSEO)

### Overview
Plugin-agnostic import framework for migrating SEO data from Yoast, Rank Math, AIOSEO, SEOPress, and Redirection.

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
    public function is_available() { /* check for source data */ }
    public function get_importable_items() { /* return counts */ }
    public function preview( $item_types ) { /* return preview */ }
    public function import( $item_types, $options ) { /* perform import */ }
}

add_action( 'rationalseo_register_importers', function( $manager ) {
    $manager->register( new RationalSEO_Yoast_Importer() );
} );
```

### Import Tab
Located at: RationalWP > SEO > Import tab. Modal workflow: Preview → Select types → Import → Results.

### Implemented Importers

All SEO importers handle: post meta, redirects, and settings. All use batch processing (100 posts per batch) and support `skip_existing` option.

| Importer | Source Plugin | Variable Syntax | Key Gotchas |
|----------|-------------|----------------|-------------|
| Yoast | Yoast SEO / Premium | `%%var%%` | Redirects from 3 possible option keys; separator uses `sc-*` codes |
| Rank Math | Rank Math | `%var%` | Option names use **hyphens** (`rank-math-options-titles`); robots stored as array |
| AIOSEO | All in One SEO | `#var` | Post data in `aioseo_posts` table (not post meta); options stored as JSON |
| SEOPress | SEOPress | `%%var%%` | Noindex stored as `'yes'` string; redirects stored as post meta |
| Redirection | Redirection | N/A | Redirects only; `action_data` can be plain URL, JSON, or serialized PHP |

**Home title/description import:** All SEO importers write to front page post meta (`_rationalseo_title`, `_rationalseo_desc` on `page_on_front`) rather than plugin settings.

### Post Meta Mapping (all SEO importers follow this pattern)

| RationalSEO Key | Purpose |
|-----------------|---------|
| `_rationalseo_title` | Custom SEO title |
| `_rationalseo_desc` | Custom meta description |
| `_rationalseo_canonical` | Custom canonical URL |
| `_rationalseo_noindex` | Noindex flag (stores `'1'`) |
| `_rationalseo_og_image` | Custom social image URL |

See each importer class for source plugin key mappings and variable conversion details.

## Term Meta System

SEO fields are available on all public taxonomy term edit screens (Categories, Tags, custom taxonomies).

### Term Meta Keys

| Key | Purpose |
|-----|---------|
| `_rationalseo_term_title` | Custom SEO title for term archive |
| `_rationalseo_term_desc` | Custom meta description |
| `_rationalseo_term_canonical` | Custom canonical URL |
| `_rationalseo_term_noindex` | Noindex flag (stores `'1'`) |
| `_rationalseo_term_og_image` | Custom social image URL |

### Implementation Details

- **Class:** `RationalSEO_Term_Meta` (`includes/class-term-meta.php`)
- **Hooks:** `{$taxonomy}_edit_form_fields`, `{$taxonomy}_add_form_fields`, `edited_{$taxonomy}`, `created_{$taxonomy}`
- **Frontend:** `class-frontend.php` checks term meta before falling back to defaults in `get_title()`, `get_description()`, `get_robots()`, `get_canonical()`, and `get_social_image()`
