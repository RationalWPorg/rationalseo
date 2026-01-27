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
RationalSEO includes a plugin-agnostic import framework for migrating SEO data from other plugins (Yoast, RankMath, AIOSEO, SEOPress).

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
| `_yoast_wpseo_title` | `_rationalseo_title` | With variable conversion |
| `_yoast_wpseo_metadesc` | `_rationalseo_desc` | With variable conversion |
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
| `wpseo_titles` | `title-home-wpseo` | Front page `_rationalseo_title` post meta (with variable conversion) |
| `wpseo_titles` | `metadesc-home-wpseo` | Front page `_rationalseo_desc` post meta (with variable conversion) |
| `wpseo_social` | `og_default_image` | `social_default_image` |
| `wpseo_social` | `twitter_card_type` | `twitter_card_type` |
| `wpseo_titles` | `company_logo` | `site_logo` |
| `wpseo` | `googleverify` | `verification_google` |
| `wpseo` | `msverify` | `verification_bing` |

**Yoast Variable Conversion:**
Since RationalSEO doesn't support template variables, Yoast variables are converted during import for both settings AND post meta:

*Site-wide variables:*
- `%%sitename%%`, `%%sitetitle%%` → Site name
- `%%sitedesc%%`, `%%tagline%%` → Site tagline
- `%%sep%%`, `%%separator%%` → Title separator
- `%%currentyear%%`, `%%current_year%%` → Current year
- `%%currentmonth%%`, `%%current_month%%` → Current month
- `%%page%%`, `%%pagenumber%%` → Empty (for pagination)

*Post-specific variables (for post meta import):*
- `%%title%%`, `%%post_title%%` → Post title
- `%%excerpt%%`, `%%excerpt_only%%` → Post excerpt
- `%%category%%`, `%%primary_category%%` → Primary category
- `%%author%%`, `%%name%%`, `%%post_author%%` → Author display name
- `%%date%%`, `%%post_date%%` → Post date
- `%%modified%%`, `%%post_modified%%` → Modified date
- `%%pt_single%%`, `%%pt_plural%%` → Post type labels
- Unrecognized variables are stripped from the output

**Batch Processing:** Post meta imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.

### Rank Math Importer (Implemented)
The Rank Math importer (`class-rankmath-importer.php`) handles:

**Post Meta Import:**
| Rank Math Key | RationalSEO Key | Notes |
|---------------|-----------------|-------|
| `rank_math_title` | `_rationalseo_title` | With variable conversion |
| `rank_math_description` | `_rationalseo_desc` | With variable conversion |
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
| `rank-math-options-titles` | `homepage_title` | Front page `_rationalseo_title` post meta (with variable conversion) |
| `rank-math-options-titles` | `homepage_description` | Front page `_rationalseo_desc` post meta (with variable conversion) |
| `rank-math-options-titles` | `open_graph_image` | `social_default_image` |
| `rank-math-options-titles` | `twitter_card_type` | `twitter_card_type` |
| `rank-math-options-titles` | `knowledgegraph_logo` | `site_logo` |
| `rank-math-options-general` | `google_verify` | `verification_google` |
| `rank-math-options-general` | `bing_verify` | `verification_bing` |

**Rank Math Variable Conversion:**
Rank Math uses `%variable%` (single percent) syntax. Variables are converted during import for both settings AND post meta:

*Site-wide variables:*
- `%sitename%`, `%site_title%` → Site name
- `%sitedesc%` → Site tagline
- `%sep%`, `%separator%` → Title separator
- `%currentyear%`, `%currentmonth%`, `%currentday%`, `%currentdate%` → Date values
- `%currenttime%`, `%currenttime(format)%` → Time values (custom PHP format supported)
- `%org_name%`, `%org_url%`, `%org_logo%` → Organization info from Local SEO settings

*Post-specific variables (for post meta import):*
- `%title%`, `%post_title%`, `%seo_title%` → Post title
- `%excerpt%`, `%seo_description%` → Post excerpt
- `%category%`, `%categories%`, `%primary_category%` → Primary category
- `%focuskw%`, `%focus_keyword%`, `%keywords%` → Focus keyword
- `%author%`, `%name%`, `%post_author%` → Author display name
- `%date%`, `%post_date%` → Post date
- `%customfield(name)%` → Custom field value
- `%count(type)%` → Stripped (count variables)
- Unrecognized variables are stripped from the output

**Important:** Rank Math option names use **hyphens** (`rank-math-options-titles`), not underscores.

**Batch Processing:** Post meta imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.

### AIOSEO Importer (Implemented)
The AIOSEO importer (`class-aioseo-importer.php`) handles:

**Important:** AIOSEO stores post data in custom table `aioseo_posts`, NOT post meta.

**Post Meta Import (from `aioseo_posts` table):**
| AIOSEO Column | RationalSEO Key | Notes |
|---------------|-----------------|-------|
| `title` | `_rationalseo_title` | With variable conversion |
| `description` | `_rationalseo_desc` | With variable conversion |
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
| `searchAppearance.global.siteTitle` | Front page `_rationalseo_title` post meta (with variable conversion) |
| `searchAppearance.global.metaDescription` | Front page `_rationalseo_desc` post meta (with variable conversion) |
| `social.facebook.general.defaultImagePosts` | `social_default_image` |
| `social.twitter.general.defaultCardType` | `twitter_card_type` |
| `searchAppearance.global.schema.organizationLogo` | `site_logo` |
| `webmasterTools.google` | `verification_google` |
| `webmasterTools.bing` | `verification_bing` |

**AIOSEO Variable Conversion:**
AIOSEO uses `#variable` (hash prefix) syntax. Variables are converted during import for both settings AND post meta:

*Site-wide variables:*
- `#site_title` → Site name
- `#tagline` → Site tagline
- `#separator_sa`, `#separator` → Title separator
- `#current_year`, `#current_month`, `#current_day`, `#current_date` → Date values
- `#page_number` → Empty (for pagination)

*Post-specific variables (for post meta import):*
- `#post_title` → Post title
- `#post_excerpt`, `#post_excerpt_only`, `#post_content` → Post excerpt/content
- `#categories`, `#category`, `#category_title` → Primary category
- `#author_name`, `#author_first_name`, `#author_last_name` → Author info
- `#author_bio`, `#author_url` → Author meta
- `#post_date`, `#post_day`, `#post_month`, `#post_year` → Post dates
- `#focus_keyphrase` → Focus keyphrase from `keyphrases` JSON column
- `#custom_field-FIELDNAME` → Custom field value (pattern matching)
- Unrecognized variables are stripped from the output

**Important:** AIOSEO options are stored as JSON in `aioseo_options`. Use `get_option_value()` helper with dot notation to access nested values.

**Batch Processing:** Post imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.

### SEOPress Importer (Implemented)
The SEOPress importer (`class-seopress-importer.php`) handles:

**Post Meta Import:**
| SEOPress Key | RationalSEO Key | Notes |
|--------------|-----------------|-------|
| `_seopress_titles_title` | `_rationalseo_title` | With variable conversion |
| `_seopress_titles_desc` | `_rationalseo_desc` | With variable conversion |
| `_seopress_robots_canonical` | `_rationalseo_canonical` | Direct copy |
| `_seopress_robots_index` | `_rationalseo_noindex` | 'yes'→'1' |
| `_seopress_social_fb_img` | `_rationalseo_og_image` | Direct copy |

**Redirects Import:**
- Source: Post meta keys (`_seopress_redirections_*`) - unique approach
- SEOPress stores redirects as post meta, not in a separate table
- Source URL is the post's permalink
- Status codes: 301, 302, 307

**Settings Import (from 3 separate options):**
| SEOPress Source | SEOPress Key | RationalSEO Key |
|-----------------|--------------|-----------------|
| `seopress_titles_option_name` | `seopress_titles_sep` | `separator` |
| `seopress_titles_option_name` | `seopress_titles_home_site_title` | Front page `_rationalseo_title` post meta (with variable conversion) |
| `seopress_titles_option_name` | `seopress_titles_home_site_desc` | Front page `_rationalseo_desc` post meta (with variable conversion) |
| `seopress_social_option_name` | `seopress_social_facebook_img` | `social_default_image` |
| `seopress_social_option_name` | `seopress_social_twitter_card_img_size` | `twitter_card_type` |
| `seopress_social_option_name` | `seopress_social_knowledge_img` | `site_logo` |
| `seopress_advanced_option_name` | `seopress_advanced_advanced_google` | `verification_google` |
| `seopress_advanced_option_name` | `seopress_advanced_advanced_bing` | `verification_bing` |

**SEOPress Variable Conversion:**
SEOPress uses `%%variable%%` (double percent) syntax, same as Yoast. Variables are converted during import for both settings AND post meta:

*Site-wide variables:*
- `%%sitetitle%%`, `%%sitename%%` → Site name
- `%%tagline%%`, `%%sitedesc%%` → Site tagline
- `%%sep%%` → Title separator
- `%%currentyear%%`, `%%currentmonth%%`, `%%currentday%%`, `%%currentdate%%` → Date values
- `%%page%%`, `%%current_pagination%%` → Empty (for pagination)

*Post-specific variables (for post meta import):*
- `%%post_title%%`, `%%title%%` → Post title
- `%%post_excerpt%%`, `%%excerpt%%`, `%%post_content%%` → Post excerpt/content
- `%%post_category%%`, `%%_category_title%%` → Primary category
- `%%post_tag%%`, `%%tag%%` → Post tags
- `%%post_author%%`, `%%author%%` → Author display name
- `%%author_first_name%%`, `%%author_last_name%%`, `%%author_nickname%%` → Author meta
- `%%post_date%%`, `%%date%%`, `%%post_modified_date%%` → Post dates
- `%%target_keyword%%` → Target keyword from `_seopress_analysis_target_kw`
- `%%_cf_FIELDNAME%%` → Custom field value (pattern matching)
- `%%_ct_TAXONOMY%%` → Custom taxonomy term (pattern matching)
- Unrecognized variables are stripped from the output

**Important:** SEOPress stores noindex as 'yes' string (not '1' like other plugins).

**Batch Processing:** Post meta imports in batches of 100 to avoid timeouts.

**Options:** `skip_existing` - Skip posts/redirects that already have RationalSEO data.

### Redirection Plugin Importer (Implemented)
The Redirection importer (`class-redirection-importer.php`) handles redirects from the Redirection plugin by John Godley.

**Important:** This is a redirects-only importer. The Redirection plugin doesn't store SEO data (titles, descriptions) - only redirects.

**Redirects Import:**
- Source: `wp_redirection_items` database table
- Only imports `action_type='url'` redirects (actual redirects, not error pages)
- Only imports `match_type='url'` redirects (simple URL matching)
- Complex match types (referrer, agent, login, header, cookie, role, IP, etc.) are skipped
- Only imports `status='enabled'` redirects

**Database Schema:**
| Column | Purpose |
|--------|---------|
| `url` | Source URL to match |
| `action_data` | Target URL in various formats (see below) |
| `action_code` | HTTP status code (301, 302, 307, 410) |
| `regex` | 0 = literal match, 1 = regex pattern |
| `status` | 'enabled' or 'disabled' |
| `action_type` | 'url' for redirects, 'error' for error pages |
| `match_type` | 'url' for simple matching, others for conditional |

**action_data Formats:**
The `action_data` column stores the target URL in multiple possible formats:
- Plain URL string: `/new-page` (most common)
- JSON object: `{"url":"/new-page"}`
- Serialized PHP array: `a:1:{s:3:"url";s:7:"/new-page";}`

The importer's `parse_action_data()` method handles all three formats.

**Status Code Handling:**
| Redirection Code | RationalSEO Import |
|------------------|-------------------|
| 301 | Imported as 301 |
| 302 | Imported as 302 |
| 307 | Imported as 307 |
| 308 | Converted to 301 (308 not supported) |
| 410 | Imported as 410 |
| 404 | Skipped (error type, not redirect) |

**Options:** `skip_existing` - Skip redirects that already exist in RationalSEO.
