# 🧠 RationalSEO: Project Blueprint

**Project Name:** RationalSEO
**Plugin Slug:** `rationalseo`
**Mission:** To provide 100% of the technical SEO requirements for modern WordPress sites with 0% of the database bloat, UI clutter, or performance drag.

---

## I. The "Rational" Philosophy (Boundaries)

We rigorously reject features that do not directly influence search engines or social crawlers.

* **⛔ No Content Scoring:** No traffic lights, readability checks, or keyword density counters.
* **⛔ No Dashboard:** No "SEO Overview" widget or React-powered admin home.
* **⛔ No Analytics:** We do not track rankings or connect to Google Search Console APIs.
* **⛔ No Frontend Assets:** Zero CSS or JS files loaded on the frontend (admin only).
* **⛔ No 404 Logging:** We never write 404 hits to the database.

---

## II. Technical Architecture

### 1. Database Schema

We use a "light footprint" approach to minimize SQL impact.

* **Global Settings:** Stored in a *single* row in `wp_options`.
* **Option Name:** `rationalseo_settings`
* **Format:** Serialized Array (JSON).


* **Post Meta:** Standard `post_meta` keys for portability.
* **Keys:** `_rationalseo_title`, `_rationalseo_desc`, `_rationalseo_canonical`, `_rationalseo_noindex`, `_rationalseo_og_image`.


* **Redirects:** A custom micro-table to bypass `wp_posts` lookups.
* **Table Name:** `wp_rationalseo_redirects`
* **Schema:**
* `id` (BIGINT, Auto Increment)
* `url_from` (VARCHAR 255, **Indexed**)
* `url_to` (TEXT)
* `status_code` (INT, Default 301)
* `count` (INT, Default 0)





### 2. Performance & Caching Strategy

* **Sitemaps (Stale-While-Revalidate):**
* **Engine:** Uses WordPress Transients (`rationalseo_sitemap_post_1`).
* **Logic:** If cache is expired, serve the "stale" version immediately, then schedule a background event (`wp_schedule_single_event`) to rebuild it.
* **Headers:** Sends `Cache-Control: max-age=3600` and `Last-Modified` to leverage Nginx/Varnish caching.


* **Redirects (The "Fast" Hook):**
* Executes on `template_redirect` (or early `init`).
* Performs **one** direct SQL query on the indexed `url_from` column.



---

## III. User Interface Specification

### 1. The Settings Panel

**Location:** `Settings > RationalSEO` (Single page, native WP UI).
**Navigation:** Top-level Query Parameter Tabs (e.g., `&tab=social`).

| Tab | Key Features |
| --- | --- |
| **General** | **Identity:** Organization/Person dropdown, Name, Logo URL.<br>

<br>**Webmaster:** Verification codes (GSC/Bing).<br>

<br>**Home:** Title/Desc overrides. |
| **Content** | **Accordion List:** Posts, Pages, Products, Custom Post Types.<br>

<br>**Per Type:** Toggle `noindex`, define Title Template (e.g., `%title% |
| **Social** | **Default Image:** Fallback URL if no featured image exists.<br>

<br>**Twitter:** Toggle Summary vs. Large Image. |
| **Sitemaps** | **Toggle:** Enable/Disable.<br>

<br>**Freshness:** "Exclude content older than [ X ] months" (0 = All).<br>

<br>**Exclusions:** Checkbox list of Post Types to hide. |
| **Redirects** | **The Watchdog:** Toggle "Auto-redirect on slug change".<br>

<br>**Manager:** Simple "Add New" row + List of active redirects (Delete button). |

### 2. The Editor Experience

**Location:** Gutenberg Sidebar (PluginDocumentSettingPanel) or Classic Meta Box.

* **Visual Preview:** A live CSS-only rendering of the Google snippet (Blue link, green URL).
* **Input Fields:**
* **SEO Title:** Placeholder shows the calculated default.
* **Description:** Helper text: "Leave empty to use excerpt."


* **Advanced Section (Collapsed):**
* `[ ] Exclude from Search Results (noindex)`
* `Canonical URL` input.
* `Social Image Override` (Media selector).



---

## IV. Frontend Output (The "Invisible" Code)

We hook into `wp_head` to output clean, validated tags.

### 1. Meta Tags

```html
<title>RationalSEO Strategy | My Agency</title>
<meta name="description" content="A no-nonsense guide to building SEO plugins." />
<meta name="robots" content="index, follow, max-image-preview:large" />
<link rel="canonical" href="https://example.com/rational-seo/" />

```

### 2. Social Graph (Open Graph)

```html
<meta property="og:locale" content="en_US" />
<meta property="og:type" content="article" />
<meta property="og:title" content="RationalSEO Strategy" />
<meta property="og:url" content="https://example.com/rational-seo/" />
<meta property="og:site_name" content="My Agency" />
<meta property="og:image" content="https://example.com/wp-content/uploads/hero.jpg" />

```

### 3. Smart Schema (JSON-LD)

A single `@graph` object linking the Article to the Site and Organization.

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://example.com/#organization",
      "name": "My Agency",
      "logo": { "@type": "ImageObject", "url": "..." }
    },
    {
      "@type": "WebPage",
      "@id": "https://example.com/rational-seo/#webpage",
      "url": "https://example.com/rational-seo/",
      "isPartOf": { "@id": "https://example.com/#website" }
    },
    {
      "@type": "Article",
      "headline": "RationalSEO Strategy",
      "mainEntityOfPage": { "@id": "https://example.com/rational-seo/#webpage" },
      "publisher": { "@id": "https://example.com/#organization" },
      "image": { "@id": "https://example.com/rational-seo/#primaryimage" }
    }
  ]
}
</script>

```

---

## V. Lifecycle Protocols

### 1. Activation

* Create `wp_rationalseo_redirects` table (IF NOT EXISTS).
* Set default options in `rationalseo_settings` (e.g., Separator: `|`).

### 2. Deactivation

* Flush rewrite rules.
* Stop any pending sitemap cron events.

### 3. Uninstall (The "Clean Exit")

* **Drop Table:** `wp_rationalseo_redirects`.
* **Delete Option:** `rationalseo_settings`.
* **Delete Transients:** `DELETE FROM wp_options WHERE option_name LIKE '_transient_rationalseo_%'`.
* *(Note: We leave `post_meta` intact to preserve the user's hard work).*

---

## VI. Implementation Status

### Phase 1: Foundation & Frontend Meta Tags ✅ COMPLETE

**Completed Features:**
- Plugin bootstrap with constants and hooks (`rationalseo.php`)
- Singleton main class (`includes/class-rationalseo.php`)
- Settings management with single option storage (`includes/class-settings.php`)
- Frontend meta tag output (`includes/class-frontend.php`):
  - `<title>` with template: `%post_title% | %site_name%`
  - `<meta name="description">` (excerpt or content-generated)
  - `<meta name="robots">` (index,follow default, noindex for search/404/paged)
  - `<link rel="canonical">` (current URL, clean)
  - Google/Bing verification meta tags
- Admin settings page (`includes/class-admin.php`):
  - Identity section: Site Type, Site Name, Logo URL, Separator
  - Webmaster section: Google/Bing verification codes
  - Homepage section: Custom title/description overrides
- Activation hooks (`includes/class-activator.php`)
- Admin CSS (`assets/css/admin.css`)

**Settings Schema (Phase 1):**
```php
array(
    'separator'           => '|',
    'site_type'           => 'organization',
    'site_name'           => get_bloginfo('name'),
    'site_logo'           => '',
    'verification_google' => '',
    'verification_bing'   => '',
    'home_title'          => '',
    'home_description'    => '',
)
```

### Phase 2: Open Graph & Twitter Cards ✅ COMPLETE

**Completed Features:**
- Tab navigation added to admin settings (General / Social tabs)
- Social tab with settings:
  - Default Social Image URL (fallback when no featured image)
  - Twitter Card Type dropdown (Summary / Summary with Large Image)
- Open Graph meta tag output in frontend:
  - `og:locale` - Site locale
  - `og:type` - "website" for homepage, "article" for singular content
  - `og:title` - Reuses existing title logic
  - `og:description` - Reuses existing description logic
  - `og:url` - Reuses canonical URL logic
  - `og:site_name` - From settings
  - `og:image` - Featured image → default social image → site logo
- Twitter Card meta tag output:
  - `twitter:card` - Configurable (summary_large_image default)
  - `twitter:title` - Same as og:title
  - `twitter:description` - Same as og:description
  - `twitter:image` - Same as og:image

**Settings Schema (Phase 2 additions):**
```php
array(
    // ... Phase 1 settings ...
    'social_default_image' => '',
    'twitter_card_type'    => 'summary_large_image',
)
```

**Files Modified:**
- `includes/class-settings.php` - Added new defaults
- `includes/class-admin.php` - Added tab navigation, Social section, fields, sanitization
- `includes/class-frontend.php` - Added `output_open_graph()`, `output_twitter_cards()`, `get_social_image()`

### Phase 3: Post Meta & Editor Integration ✅ COMPLETE

**Completed Features:**
- Meta box class (`includes/class-meta-box.php`):
  - Registers on all public post types (posts, pages, custom post types)
  - Nonce verification and capability checks for security
  - Proper sanitization of all inputs
- Meta box fields:
  - SEO Title: Text input with placeholder showing calculated default
  - Meta Description: Textarea with helper text
  - Advanced Section (collapsed by default):
    - Exclude from Search Results (noindex checkbox)
    - Canonical URL override input
    - Social Image Override URL input
- Meta box CSS (`assets/css/meta-box.css`)
- Frontend integration:
  - `get_social_image()` updated with new priority: custom OG image → featured image → default social image → site logo

**Post Meta Keys:**
```php
'_rationalseo_title'     // Custom SEO title
'_rationalseo_desc'      // Custom meta description
'_rationalseo_canonical' // Custom canonical URL
'_rationalseo_noindex'   // Boolean: exclude from search (stores '1' or deleted)
'_rationalseo_og_image'  // Custom social sharing image URL
```

**Files Created:**
- `includes/class-meta-box.php` - Meta box registration, rendering, and saving
- `assets/css/meta-box.css` - Meta box styling

**Files Modified:**
- `rationalseo.php` - Added require for meta box class
- `includes/class-rationalseo.php` - Added meta box property and instantiation
- `includes/class-frontend.php` - Added `_rationalseo_og_image` check in `get_social_image()`

### Phase 4: JSON-LD Schema ✅ COMPLETE

**Completed Features:**
- JSON-LD structured data output (`includes/class-frontend.php`):
  - Single `@graph` array linking all entities via `@id` references
  - Uses `wp_json_encode()` with pretty print for valid output
- Schema entities:
  - **Organization/Person**: Based on `site_type` setting, includes optional logo ImageObject
  - **WebSite**: Always included, links to publisher via `@id`
  - **WebPage**: Always included, links to WebSite via `isPartOf`
  - **Article**: On singular posts/pages (not front page), includes:
    - `headline` - From `get_title()`
    - `description` - From `get_description()`
    - `datePublished` / `dateModified` - ISO 8601 format
    - `author` - Person type with display name
    - `image` - ImageObject when social image available
    - `mainEntityOfPage` - Links to WebPage
    - `publisher` - Links to Organization/Person

**Example Output:**
```html
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "Organization",
            "@id": "https://example.com/#organization",
            "name": "Site Name",
            "logo": { "@type": "ImageObject", "@id": "https://example.com/#logo", "url": "..." }
        },
        {
            "@type": "WebSite",
            "@id": "https://example.com/#website",
            "url": "https://example.com/",
            "name": "Site Name",
            "publisher": { "@id": "https://example.com/#organization" }
        },
        {
            "@type": "WebPage",
            "@id": "https://example.com/page/#webpage",
            "url": "https://example.com/page/",
            "isPartOf": { "@id": "https://example.com/#website" }
        },
        {
            "@type": "Article",
            "@id": "https://example.com/page/#article",
            "headline": "Page Title",
            "mainEntityOfPage": { "@id": "https://example.com/page/#webpage" },
            "publisher": { "@id": "https://example.com/#organization" },
            "datePublished": "2024-01-15T10:00:00+00:00",
            "dateModified": "2024-01-16T14:30:00+00:00",
            "description": "...",
            "author": { "@type": "Person", "name": "Author Name" },
            "image": { "@type": "ImageObject", "@id": "https://example.com/page/#primaryimage", "url": "..." }
        }
    ]
}
</script>
```

**Files Modified:**
- `includes/class-frontend.php` - Added `output_schema()` method (lines 487-596)

### Phase 5: XML Sitemaps ✅ COMPLETE

**Completed Features:**
- Sitemap class (`includes/class-sitemap.php`):
  - Rewrite rules for `/sitemap.xml` and `/sitemap-{post_type}.xml`
  - Sitemap index generation listing all post type sitemaps
  - Per-post-type sitemaps with pagination (1000 URLs max per file)
  - Transient caching with stale-while-revalidate pattern
  - Background rebuild via `wp_schedule_single_event`
  - Cache invalidation on post save/delete
  - Proper XML headers and `Cache-Control: max-age=3600` headers
  - Respects `_rationalseo_noindex` post meta
  - Excludes private/draft posts
- Sitemaps tab in admin settings:
  - Enable/disable toggle
  - Content freshness dropdown (exclude content older than X months)
  - Post type exclusion checkboxes
- Activation/deactivation hooks:
  - Flush rewrite rules on activation
  - Clear sitemap transients on deactivation

**Settings Schema (Phase 5 additions):**
```php
array(
    // ... Previous settings ...
    'sitemap_enabled'       => true,
    'sitemap_max_age'       => 0,      // 0 = all content, or 6/12/24/36 months
    'sitemap_exclude_types' => array(), // Post types to exclude
)
```

**Sitemap URLs:**
- `https://example.com/sitemap.xml` - Index listing all sub-sitemaps
- `https://example.com/sitemap-post.xml` - Posts sitemap
- `https://example.com/sitemap-page.xml` - Pages sitemap
- `https://example.com/sitemap-{cpt}-{page}.xml` - Paginated custom post type sitemaps

**Files Created:**
- `includes/class-sitemap.php` - Sitemap generation and caching

**Files Modified:**
- `rationalseo.php` - Added require for sitemap class
- `includes/class-rationalseo.php` - Added sitemap property and instantiation
- `includes/class-settings.php` - Added sitemap defaults
- `includes/class-admin.php` - Added Sitemaps tab with settings fields
- `includes/class-activator.php` - Added rewrite flush and cache clearing

### Phase 6: Redirects (Next)