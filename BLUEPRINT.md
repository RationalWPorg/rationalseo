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

### Phase 2: Open Graph & Twitter Cards (Next)

**Objectives:**
- Add Social tab to settings page
- Output Open Graph meta tags (og:locale, og:type, og:title, og:url, og:site_name, og:image)
- Output Twitter Card meta tags (twitter:card, twitter:title, twitter:description, twitter:image)
- Add settings: Default fallback image URL, Twitter card type toggle

### Phase 3: Post Meta & Editor Integration (Planned)

### Phase 4: JSON-LD Schema (Planned)

### Phase 5: Sitemaps (Planned)

### Phase 6: Redirects (Planned)