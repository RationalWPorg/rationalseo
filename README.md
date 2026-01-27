# RationalSEO

A lightweight WordPress SEO plugin that provides 100% of the technical SEO requirements for modern sites with 0% of the database bloat, UI clutter, or performance drag.

## Philosophy

RationalSEO rigorously rejects features that do not directly influence search engines or social crawlers:

- **No Content Scoring** - No traffic lights, readability checks, or keyword density counters
- **No Dashboard** - No "SEO Overview" widget or React-powered admin home
- **No Analytics** - No ranking tracking or Google Search Console API integrations
- **No Frontend Assets** - Zero CSS or JS files loaded on the frontend
- **No 404 Logging** - Never writes 404 hits to the database

## Features

### Meta Tags
- Custom `<title>` with configurable separator
- Meta descriptions (manual or auto-generated from excerpt/content)
- Robots meta (index/noindex control)
- Canonical URLs
- Google and Bing verification meta tags

### Social Media
- Open Graph tags (og:title, og:description, og:image, og:url, og:type, og:locale, og:site_name)
- Twitter Cards (summary or summary_large_image)
- Per-post social image override

### Structured Data
- JSON-LD schema output using `@graph` format
- Organization or Person schema (configurable)
- WebSite schema with publisher linking
- WebPage schema
- Article schema on posts/pages with author, dates, and images

### XML Sitemaps
- Sitemap index at `/sitemap.xml`
- Per-post-type sitemaps with pagination
- Transient caching with stale-while-revalidate
- Content freshness filtering (exclude old content)
- Post type exclusions

### URL Redirects
- 301, 302, 307 redirects and 410 (Gone) responses
- Auto-redirect when post slugs change
- Hit counter tracking
- Fast execution via indexed database lookups

### Editor Integration
- Meta box on all public post types
- Custom SEO title and description fields
- Noindex toggle per post
- Canonical URL override
- Social image override

## Settings

Located at **Settings > RationalSEO** with four tabs:

| Tab | Settings |
|-----|----------|
| **General** | Site type, name, logo, title separator, verification codes |
| **Social** | Default social image, Twitter card type |
| **Sitemaps** | Enable/disable, content freshness, post type exclusions |
| **Redirects** | Auto-redirect on slug change, redirect manager |

## Technical Documentation

### Database Schema

**Single Option Storage:**
- Option name: `rationalseo_settings`
- Format: Serialized array

**Post Meta Keys:**
| Key | Purpose |
|-----|---------|
| `_rationalseo_title` | Custom SEO title |
| `_rationalseo_desc` | Custom meta description |
| `_rationalseo_canonical` | Custom canonical URL |
| `_rationalseo_noindex` | Exclude from search (stores '1') |
| `_rationalseo_og_image` | Custom social image URL |

**Redirects Table:** `{prefix}_rationalseo_redirects`
| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Auto increment primary key |
| `url_from` | VARCHAR(255) | Source path (indexed) |
| `url_to` | TEXT | Destination URL |
| `status_code` | INT | 301, 302, 307, or 410 |
| `count` | INT | Hit counter |

### Settings Schema

```php
array(
    'separator'              => '|',
    'site_type'              => 'organization',
    'site_name'              => get_bloginfo('name'),
    'site_logo'              => '',
    'verification_google'    => '',
    'verification_bing'      => '',
    'social_default_image'   => '',
    'twitter_card_type'      => 'summary_large_image',
    'sitemap_enabled'        => true,
    'sitemap_max_age'        => 0,
    'sitemap_exclude_types'  => array(),
    'redirect_auto_slug'     => true,
)
```

### Sitemap URLs

- `/sitemap.xml` - Index listing all sub-sitemaps
- `/sitemap-post.xml` - Posts sitemap
- `/sitemap-page.xml` - Pages sitemap
- `/sitemap-{post_type}-{page}.xml` - Paginated sitemaps for custom post types

### Frontend Output Examples

**Meta Tags:**
```html
<title>Page Title | Site Name</title>
<meta name="description" content="Page description here." />
<meta name="robots" content="index, follow, max-image-preview:large" />
<link rel="canonical" href="https://example.com/page/" />
```

**Open Graph:**
```html
<meta property="og:locale" content="en_US" />
<meta property="og:type" content="article" />
<meta property="og:title" content="Page Title" />
<meta property="og:description" content="Page description here." />
<meta property="og:url" content="https://example.com/page/" />
<meta property="og:site_name" content="Site Name" />
<meta property="og:image" content="https://example.com/image.jpg" />
```

**JSON-LD Schema:**
```html
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "Organization",
            "@id": "https://example.com/#organization",
            "name": "Site Name"
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
            "headline": "Page Title",
            "mainEntityOfPage": { "@id": "https://example.com/page/#webpage" },
            "publisher": { "@id": "https://example.com/#organization" }
        }
    ]
}
</script>
```

## Lifecycle

### Activation
- Creates redirects database table
- Sets default options

### Deactivation
- Flushes rewrite rules
- Clears sitemap cache transients

### Uninstall
- Drops redirects table
- Deletes settings option
- Clears all transients
- **Preserves post meta** (user's SEO data is kept)

## Requirements

- PHP 7.4+
- WordPress 5.0+
