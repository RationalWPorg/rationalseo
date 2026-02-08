=== RationalSEO ===
Contributors: rationalwp
Tags: seo, meta tags, sitemap, schema
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.4
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
* Focus keyword field with real-time presence indicators (title, description, first paragraph, URL slug)
* Noindex toggle per post
* Canonical URL override
* Social image override
* Taxonomy term SEO fields (title, description, noindex, canonical)

**Post Type Archives**

* Custom SEO title and description for each post type archive
* Configure via Settings > Rational SEO > Archives tab

**AI Assistant (optional)**

* AI-powered focus keyword suggestions
* AI-generated SEO titles and meta descriptions
* One-click "Suggest All" for keyword, title, and description together
* Uses OpenAI GPT-4o-mini (requires your own API key)
* Data sent only when you click — never in the background

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

Navigate to Settings > Rational SEO in your WordPress admin. Settings are organized into tabs: General, Social, Sitemaps, Archives, and Import.

== Screenshots ==

1. General settings tab with site identity and homepage SEO options
2. Post editor meta box for customizing SEO per post
3. XML sitemap output

== External Services ==

This plugin connects to the following external services:

= RationalWP Plugin Directory =

This plugin fetches a list of available RationalWP plugins from [rationalwp.com](https://rationalwp.com/) to display in the WordPress admin menu. Only the menu file version number is sent as a cache-busting query parameter. No user data is transmitted. The response is cached locally for 24 hours.

* Service URL: [https://rationalwp.com/plugins.json](https://rationalwp.com/plugins.json)
* Terms of Service: [https://rationalwp.com/terms/](https://rationalwp.com/terms/)
* Privacy Policy: [https://rationalwp.com/privacy/](https://rationalwp.com/privacy/)

= OpenAI API (optional) =

This plugin provides optional AI-powered SEO suggestions using the [OpenAI API](https://openai.com/). When a site administrator configures an OpenAI API key in Settings > Rational SEO > General, users can click "Suggest" or "Generate" buttons in the post editor to receive AI-generated focus keywords, SEO titles, and meta descriptions.

Data is only sent when a user explicitly clicks an AI suggestion button. The data sent includes the post title and up to 2000 characters of post content (stripped of HTML), along with the user's own API key for authentication. No data is sent automatically or in the background.

* Service URL: [https://api.openai.com/v1/chat/completions](https://api.openai.com/v1/chat/completions)
* Terms of Use: [https://openai.com/policies/terms-of-use/](https://openai.com/policies/terms-of-use/)
* Privacy Policy: [https://openai.com/policies/privacy-policy/](https://openai.com/policies/privacy-policy/)

== Changelog ==

= 1.0.4 =
* Fixed: Readme stable tag now matches plugin version

= 1.0.3 =
* Fixed: Extracted import inline script to enqueued external JS file
* Fixed: Addressed WordPress.org plugin review feedback

= 1.0.2 =
* Added: Custom SEO title and description for post type archives (new Archives tab)

= 1.0.1 =
* Added: Focus keyword field for posts and taxonomy terms
* Added: Real-time keyword presence indicators (title, description, first paragraph, URL slug)
* Added: AI-powered keyword suggestions, title generation, and description generation
* Added: One-click "Suggest All" for keyword, title, and description together
* Added: Encrypted OpenAI API key storage
* Added: Focus keyword import support for Yoast, Rank Math, AIOSEO, and SEOPress
* Added: Auto-generated descriptions prefer sentences containing the focus keyword
* Fixed: API key decryption handles WordPress salt changes gracefully

= 1.0.0 =
* Initial release
* Meta tags output (title, description, robots, canonical)
* Open Graph and Twitter Card support
* JSON-LD structured data with @graph format
* XML sitemaps with caching and content freshness filtering
* Post editor meta box integration
* Taxonomy term SEO fields
* Import tools for Yoast, Rank Math, AIOSEO, and SEOPress

== Upgrade Notice ==

= 1.0.4 =
Readme and version sync fix.

= 1.0.3 =
Plugin review compliance fixes.

= 1.0.2 =
New Archives tab for post type archive SEO settings.

= 1.0.1 =
Focus keyword field, AI-powered SEO suggestions, and keyword import support.

= 1.0.0 =
Initial release of Rational SEO.
