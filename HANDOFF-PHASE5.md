# Phase 5 Handoff: RationalSEO Import System

## What Was Accomplished (Phase 4)

### Rank Math Importer Implementation
Created a fully functional Rank Math importer at `includes/import/importers/class-rankmath-importer.php` that:

1. **Imports Post Meta** - Maps Rank Math meta keys to RationalSEO equivalents:
   - `rank_math_title` → `_rationalseo_title`
   - `rank_math_description` → `_rationalseo_desc`
   - `rank_math_canonical_url` → `_rationalseo_canonical`
   - `rank_math_robots` (checks for 'noindex') → `_rationalseo_noindex`
   - `rank_math_facebook_image` → `_rationalseo_og_image`

2. **Imports Redirects** - Reads from `wp_rank_math_redirections` database table:
   - Supports comparison types: exact, regex, contains, start, end
   - Converts contains/start/end patterns to regex automatically
   - Handles 301, 302, 307, 410 status codes

3. **Imports Settings** (8 total):
   - `title_separator` → `separator`
   - `homepage_title` → `home_title` (with variable conversion)
   - `homepage_description` → `home_description` (with variable conversion)
   - `open_graph_image` → `social_default_image`
   - `twitter_card_type` → `twitter_card_type`
   - `knowledgegraph_logo` → `site_logo`
   - `google_verify` → `verification_google`
   - `bing_verify` → `verification_bing`

4. **Converts Rank Math Variables** - Single percent syntax (`%variable%`):
   - Site info: `%sitename%`, `%site_title%`, `%sitedesc%`
   - Separator: `%sep%`, `%separator%`
   - Date/time: `%currentyear%`, `%currentmonth%`, `%currentday%`, `%currentdate%`, `%currenttime%`, `%currenttime(format)%`
   - Organization: `%org_name%`, `%org_url%`, `%org_logo%`
   - Pagination: `%page%`, `%pagenumber%`, `%pagetotal%` (empty for static)

### Yoast Importer Enhancements
Updated the existing Yoast importer with:

1. **Additional Settings** (4 new):
   - `twitter_card_type` from `wpseo_social`
   - `company_logo` → `site_logo` from `wpseo_titles`
   - `googleverify` → `verification_google` from `wpseo`
   - `msverify` → `verification_bing` from `wpseo`

2. **Fixed Settings Count** - Now shows actual count instead of hardcoded `1`

### Bug Fixes
- **Rank Math option names**: Uses hyphens (`rank-math-options-titles`) not underscores
- **Settings count display**: Both importers now show accurate item counts in the UI

## Current Project State

### File Structure
```
rationalseo/
├── includes/
│   ├── import/
│   │   ├── interface-importer.php      # Importer contract
│   │   ├── class-import-result.php     # Fluent result object
│   │   ├── class-import-manager.php    # Registry & orchestration
│   │   ├── class-import-admin.php      # Admin UI & AJAX handlers
│   │   └── importers/
│   │       ├── class-yoast-importer.php    # ✅ COMPLETE (8 settings)
│   │       ├── class-rankmath-importer.php # ✅ COMPLETE (8 settings)
│   │       ├── class-aioseo-importer.php   # ✅ COMPLETE (8 settings)
│   │       └── class-seopress-importer.php # ✅ COMPLETE (8 settings)
│   ├── class-redirects.php             # Redirects manager
│   └── class-settings.php              # Settings storage
├── assets/css/
│   ├── admin.css                       # General admin styles
│   └── import.css                      # Import tab styles
└── rationalseo.php                     # Main plugin file
```

### Import System Architecture
- **Registration**: Importers register via `rationalseo_register_importers` action
- **Manager**: `RationalSEO_Import_Manager` handles registration and orchestration
- **Admin**: `RationalSEO_Import_Admin` provides AJAX handlers and modal UI
- **Result**: `RationalSEO_Import_Result` is a fluent object for returning import results

### RationalSEO Settings Keys
The plugin supports these settings (importable from Yoast/RankMath):
- `separator` - Title separator character
- `home_title` - Homepage SEO title
- `home_description` - Homepage meta description
- `social_default_image` - Default OG image
- `twitter_card_type` - summary or summary_large_image
- `site_logo` - Site/organization logo URL
- `verification_google` - Google Search Console verification
- `verification_bing` - Bing Webmaster verification

## AIOSEO Importer (Phase 5 Implementation)

Created `class-aioseo-importer.php` with full import functionality:

### Post Meta Import (from `aioseo_posts` table)
| AIOSEO Column | RationalSEO Key | Notes |
|---------------|-----------------|-------|
| `title` | `_rationalseo_title` | Direct copy |
| `description` | `_rationalseo_desc` | Direct copy |
| `canonical_url` | `_rationalseo_canonical` | Direct copy |
| `robots_noindex` | `_rationalseo_noindex` | 1 → '1' |
| `og_image_custom_url` | `_rationalseo_og_image` | Direct copy |

**Important**: AIOSEO stores post data in custom table `aioseo_posts`, NOT post meta.

### Settings Import (from `aioseo_options` JSON)
| AIOSEO Path | RationalSEO Key |
|-------------|-----------------|
| `searchAppearance.global.separator` | `separator` |
| `searchAppearance.global.siteTitle` | `home_title` |
| `searchAppearance.global.metaDescription` | `home_description` |
| `social.facebook.general.defaultImagePosts` | `social_default_image` |
| `social.twitter.general.defaultCardType` | `twitter_card_type` |
| `searchAppearance.global.schema.organizationLogo` | `site_logo` |
| `webmasterTools.google` | `verification_google` |
| `webmasterTools.bing` | `verification_bing` |

### Redirects Import (from `aioseo_redirects` table)
- **Pro/Premium feature** - table may not exist in lite installations
- Columns: `source_url`, `target_url`, `type`, `regex`, `enabled`

### AIOSEO Variable Conversion (`#variable` syntax)
| Variable | Replacement |
|----------|-------------|
| `#site_title` | Site name |
| `#tagline` | Site tagline |
| `#separator_sa` | Title separator |
| `#current_year` | Current year |
| `#current_month` | Current month |
| `#current_day` | Current day |
| `#current_date` | Formatted date |
| `#page_number` | Empty (static) |

Post-specific variables (`#post_title`, `#post_excerpt`, etc.) cause value to be skipped.

---

## SEOPress Importer (Phase 5 Implementation)

Created `class-seopress-importer.php` with full import functionality:

### Post Meta Import (from `_seopress_*` post meta)
| SEOPress Key | RationalSEO Key | Notes |
|--------------|-----------------|-------|
| `_seopress_titles_title` | `_rationalseo_title` | Direct copy |
| `_seopress_titles_desc` | `_rationalseo_desc` | Direct copy |
| `_seopress_robots_canonical` | `_rationalseo_canonical` | Direct copy |
| `_seopress_robots_index` | `_rationalseo_noindex` | 'yes' → '1' |
| `_seopress_social_fb_img` | `_rationalseo_og_image` | Direct copy |

### Settings Import (from 3 separate options)

**From `seopress_titles_option_name`:**
| SEOPress Key | RationalSEO Key |
|--------------|-----------------|
| `seopress_titles_sep` | `separator` |
| `seopress_titles_home_site_title` | `home_title` (with variable conversion) |
| `seopress_titles_home_site_desc` | `home_description` (with variable conversion) |

**From `seopress_social_option_name`:**
| SEOPress Key | RationalSEO Key |
|--------------|-----------------|
| `seopress_social_facebook_img` | `social_default_image` |
| `seopress_social_twitter_card_img_size` | `twitter_card_type` |
| `seopress_social_knowledge_img` | `site_logo` |

**From `seopress_advanced_option_name`:**
| SEOPress Key | RationalSEO Key |
|--------------|-----------------|
| `seopress_advanced_advanced_google` | `verification_google` |
| `seopress_advanced_advanced_bing` | `verification_bing` |

### Redirects Import (from post meta - unique to SEOPress)
SEOPress stores redirects as **post meta**, not in a separate table:
| SEOPress Meta Key | RationalSEO Field |
|-------------------|-------------------|
| `_seopress_redirections_value` | `url_to` (target URL) |
| `_seopress_redirections_type` | `status_code` (301, 302, 307) |
| `_seopress_redirections_enabled` | Check if redirect is active |

**Note**: Source URL is derived from the post's permalink. Regex redirects not supported in free version.

### SEOPress Variable Conversion (`%%variable%%` syntax)
SEOPress uses the same double-percent syntax as Yoast:
| Variable | Replacement |
|----------|-------------|
| `%%sitetitle%%`, `%%sitename%%` | Site name |
| `%%tagline%%`, `%%sitedesc%%` | Site tagline |
| `%%sep%%` | Title separator |
| `%%currentyear%%` | Current year |
| `%%currentmonth%%` | Current month |
| `%%currentday%%` | Current day |
| `%%currentdate%%` | Formatted date |
| `%%page%%`, `%%current_pagination%%` | Empty (static) |

Post-specific variables (`%%title%%`, `%%excerpt%%`, etc.) cause value to be skipped.

---

## Suggested Next Phase Options

### Option A: Import System Enhancements
- Add progress indicator for large imports (batch status)
- Add rollback/undo capability
- Add import history/log
- Add selective import (choose specific posts)
- Add term meta import (category/tag SEO data)

### Option D: Other RationalSEO Features
- Enhance sitemap functionality
- Add breadcrumb support
- Add schema markup features
- Add more SEO analysis tools

## Important Context & Gotchas

1. **Option Name Formats**: Each SEO plugin uses different naming conventions:
   - Yoast: `wpseo`, `wpseo_titles`, `wpseo_social` (underscores, prefixed)
   - Rank Math: `rank-math-options-titles`, `rank-math-options-general` (hyphens!)
   - AIOSEO: `aioseo_options` (single JSON option)
   - SEOPress: `seopress_titles_option_name`, `seopress_social_option_name`, `seopress_advanced_option_name` (3 separate options)

2. **Variable Syntax**: Each plugin has different template variable syntax:
   - Yoast: `%%variable%%` (double percent)
   - Rank Math: `%variable%` (single percent)
   - AIOSEO: `#variable` (hash prefix)
   - SEOPress: `%%variable%%` (double percent, same as Yoast)

3. **Redirect Storage**: Different plugins store redirects differently:
   - Yoast: wp_options (`wpseo-premium-redirects-base`)
   - Rank Math: Custom table (`wp_rank_math_redirections`)
   - AIOSEO: Custom table (`aioseo_redirects`) - Pro feature only
   - SEOPress: Post meta (`_seopress_redirections_*`) - unique approach

4. **Post Data Storage**: Different plugins store post SEO data differently:
   - Yoast: Post meta (`_yoast_wpseo_*`)
   - Rank Math: Post meta (`rank_math_*`)
   - AIOSEO: Custom table (`aioseo_posts`) - NOT post meta
   - SEOPress: Post meta (`_seopress_*`)

5. **Modal Notice Classes**: Add `.notice` classes via JavaScript (not in HTML) to prevent WordPress moving notices to the page top

6. **Settings Storage**: RationalSEO stores settings in `rationalseo_settings` option. Use `$this->settings->set_multiple()` for imports.

7. **Batch Processing**: For post meta imports, use batches of 100 with `$wpdb->get_results()` and LIMIT/OFFSET

8. **Settings Count**: Use `get_settings_count()` method (not `has_importable_settings()` with hardcoded 1) to show accurate counts in UI

## Testing the Import System

1. Navigate to RationalWP > SEO > Import tab
2. Available importers appear as cards if their data exists
3. Click "Import Data" to open modal with preview
4. Verify settings count matches preview table rows
5. Select item types and click "Import Selected"
6. Verify success message shows correct counts

## Dependencies in Place
- Import framework fully functional
- Yoast importer complete with 8 settings
- Rank Math importer complete with 8 settings
- AIOSEO importer complete with 8 settings
- SEOPress importer complete with 8 settings
- Modal UI working correctly
- AJAX handlers operational
- CSS styles in place

The codebase is ready for additional importers following the established pattern.

## How to Add a New Importer

1. Create file: `includes/import/importers/class-[slug]-importer.php`
2. Implement `RationalSEO_Importer_Interface`
3. Key methods to implement:
   - `get_slug()` - unique ID
   - `get_name()` - display name
   - `get_description()` - what it imports
   - `is_available()` - check for source data
   - `get_importable_items()` - return counts (use `get_settings_count()`)
   - `preview($item_types)` - return preview data
   - `import($item_types, $options)` - perform import
4. Add require statement to `rationalseo.php`
5. Register in `class-import-manager.php` `register_core_importers()` method
