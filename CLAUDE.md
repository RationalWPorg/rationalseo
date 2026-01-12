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
│   └── class-*.php            # Plugin classes (if needed)
├── assets/
│   └── css/admin.css          # Admin styles only
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
