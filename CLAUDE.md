# RationalSEO

Lightweight WordPress SEO plugin. No bloat, no frontend assets, no content scoring.

## Local Development Environment

- **URL:** https://development.local/wp-admin/
- **Username:** `claude`
- **Password:** `!JJ8itliI3%#YC)NRnQl2*)M`
- All `npm`/`npx` commands: prefix with `NODE_ENV=development`

## Key Classes

| Class | File | Responsibility |
|-------|------|----------------|
| `RationalSEO` | `rationalseo.php` | Singleton, hooks, option loading |
| `RationalSEO_Frontend` | `class-frontend.php` | All `<head>` output (meta, OG, schema) |
| `RationalSEO_Sitemap` | `class-sitemap.php` | XML sitemap generation |
| `RationalSEO_Settings` | `class-settings.php` | Admin UI, Settings API |
| `RationalSEO_Meta_Box` | `class-meta-box.php` | Post editor SEO fields |
| `RationalSEO_Term_Meta` | `class-term-meta.php` | Taxonomy term SEO fields |
| `RationalSEO_Import_Manager` | `import/class-import-manager.php` | Importer registry |
| `RationalSEO_AI_Assistant` | `class-ai-assistant.php` | AI keyword/description generation |

## Database Schema

**Options:** `rationalseo_settings` (serialized array)

**Post Meta Keys:**
| Key | Purpose |
|-----|---------|
| `_rationalseo_title` | Custom SEO title |
| `_rationalseo_desc` | Custom meta description |
| `_rationalseo_canonical` | Custom canonical URL |
| `_rationalseo_noindex` | Noindex flag (stores `'1'`) |
| `_rationalseo_og_image` | Custom social image URL |
| `_rationalseo_focus_keyword` | Focus keyword for content optimization |

**Term Meta Keys:** Same as post meta but prefixed `_rationalseo_term_*`

## Settings Defaults

```php
array(
    'separator'            => '|',
    'site_type'            => 'organization',
    'site_name'            => get_bloginfo('name'),
    'site_logo'            => '',
    'verification_google'  => '',
    'verification_bing'    => '',
    'social_default_image' => '',
    'twitter_card_type'    => 'summary_large_image',
    'sitemap_enabled'      => true,
    'sitemap_max_age'      => 0,
    'sitemap_exclude_types'=> array(),
    'openai_api_key'       => '',  // Stored encrypted (AES-256-CBC)
)
```

**Encrypted Settings:** The `openai_api_key` setting is stored encrypted using AES-256-CBC with `wp_salt('auth')`. Use `$settings->get_decrypted('openai_api_key')` to retrieve the plaintext value.

## AI Assistant

The AI Assistant provides optional AI-powered SEO features using OpenAI's GPT-4o-mini model.

**AJAX Actions:**
| Action | Purpose |
|--------|---------|
| `rationalseo_suggest_keyword` | Analyze content and suggest a focus keyword |
| `rationalseo_generate_description` | Generate meta description incorporating keyword |

**Usage:** AI buttons appear in the meta box only when `openai_api_key` is configured. Both actions require the `rationalseo_meta_box` nonce and `edit_posts` capability.

**Behavior:** Empty strings in saved settings are filtered out before merging with defaults. This ensures blank admin fields fall back correctly.

## Frontend Title Defaults

| Context | Title Default | Description Default |
|---------|--------------|-------------------|
| Front page | `{site name} {sep} {site description}` | Site tagline |
| Blog page | `Blog {sep} {site name}` | Site tagline |
| Singular | `{post title} {sep} {site name}` | Excerpt or auto-generated |
| Taxonomy archive | `{term name} {sep} {site name}` | Term description |
| Post type archive | `{post type name} {sep} {site name}` | — |
| Search | `Search Results for "query" {sep} {site name}` | — |
| 404 | `Page Not Found {sep} {site name}` | — |

**Important:** Front page and blog page check `page_on_front`/`page_for_posts` post meta before fallback. No homepage settings in admin—set on the page itself.

## Import System

Plugin-agnostic framework for migrating SEO data from Yoast, Rank Math, AIOSEO, and SEOPress.

**Registration:**
```php
add_action( 'rationalseo_register_importers', function( $manager ) {
    $manager->register( new RationalSEO_Yoast_Importer() );
} );
```

**Importer Interface:** `get_slug()`, `get_name()`, `get_description()`, `is_available()`, `get_importable_items()`, `preview()`, `import()`

| Importer | Variable Syntax | Key Gotchas |
|----------|----------------|-------------|
| Yoast | `%%var%%` | Separator uses `sc-*` codes |
| Rank Math | `%var%` | Options use hyphens (`rank-math-options-titles`) |
| AIOSEO | `#var` | Data in `aioseo_posts` table, not post meta |
| SEOPress | `%%var%%` | Noindex stored as `'yes'` string |

All importers: batch process 100 posts, support `skip_existing`, write home title/desc to front page post meta (not admin settings).

## Build

Run `./build.sh` to create distribution zip (excludes dev files via `.distignore`).
