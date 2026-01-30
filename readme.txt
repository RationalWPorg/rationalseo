=== RationalSEO ===
Contributors: rationalwp
Tags: seo, meta tags, sitemap, schema
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Technical SEO essentials with zero bloat. No dashboards, analytics, content scoring, or frontend assets.

== Description ==

Rational SEO provides 100% of the technical SEO requirements for modern sites with 0% of the database bloat, UI clutter, or performance drag.

= Philosophy =

Rational SEO rigorously rejects features that do not directly influence search engines or social crawlers:

* **No Content Scoring** - No traffic lights, readability checks, or keyword density counters
* **No Dashboard** - No "SEO Overview" widget or React-powered admin home
* **No Analytics** - No ranking tracking or Google Search Console API integrations
* **No Frontend Assets** - Zero CSS or JS files loaded on the frontend
* **No 404 Logging** - Never writes 404 hits to the database

= Features =

**Meta Tags**

* Custom title tags with configurable separator
* Meta descriptions (manual or auto-generated from excerpt/content)
* Robots meta (index/noindex control)
* Canonical URLs
* Google and Bing verification meta tags

**Social Media**

* Open Graph tags (og:title, og:description, og:image, og:url, og:type, og:locale, og:site_name)
* Twitter Cards (summary or summary_large_image)
* Per-post social image override

**Structured Data**

* JSON-LD schema output using @graph format
* Organization or Person schema (configurable)
* WebSite schema with publisher linking
* WebPage schema
* Article schema on posts/pages with author, dates, and images

**XML Sitemaps**

* Sitemap index at /sitemap.xml
* Per-post-type sitemaps with pagination
* Transient caching with stale-while-revalidate
* Content freshness filtering (exclude old content)
* Post type exclusions

**Editor Integration**

* Meta box on all public post types
* Custom SEO title and description fields
* Noindex toggle per post
* Canonical URL override
* Social image override

== Installation ==

1. Upload the `rationalseo` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings at Settings > Rational SEO

== Frequently Asked Questions ==

= Does this plugin work with other SEO plugins? =

We recommend using only one SEO plugin at a time to avoid duplicate meta tags and schema markup. Deactivate other SEO plugins before activating Rational SEO.

= Will I lose my SEO data if I deactivate the plugin? =

No. Your post meta data (titles, descriptions, etc.) is preserved even after deactivation or uninstallation.

= Does this plugin add any frontend assets? =

No. Rational SEO loads zero CSS or JavaScript files on the frontend. All output is server-rendered HTML.

= How do I migrate from another SEO plugin? =

Go to Settings > Rational SEO > Import tab. Rational SEO can import SEO titles, descriptions, and settings from Yoast, Rank Math, AIOSEO, and SEOPress.

= Where are the settings? =

Navigate to Settings > Rational SEO in your WordPress admin. Settings are organized into four tabs: General, Social, Sitemaps, and Import.

== Screenshots ==

1. General settings tab with site identity and homepage SEO options
2. Post editor meta box for customizing SEO per post
3. XML sitemap output

== Changelog ==

= 1.0.0 =
* Initial release
* Meta tags output (title, description, robots, canonical)
* Open Graph and Twitter Card support
* JSON-LD structured data with @graph format
* XML sitemaps with caching and content freshness filtering
* Post editor meta box integration
* Import tools for Yoast, Rank Math, AIOSEO, and SEOPress

== Upgrade Notice ==

= 1.0.0 =
Initial release of Rational SEO.
