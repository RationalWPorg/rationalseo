=== RationalSEO ===
Contributors: rationalwp
Tags: seo, meta tags, sitemap, schema
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
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

== Hooks for developers ==

RationalSEO exposes a complete set of filters and actions so themes and plugins can customize or extend output without forking the plugin. All hooks were introduced in version 1.0.6.

= Short-circuit filters =

**Return non-null to short-circuit default resolution; return null to fall through to internal logic.**

These filters let you override the data that RationalSEO resolves internally. Return a non-null value to bypass the built-in logic entirely, or return null to let RationalSEO proceed as normal.

* `rationalseo_og_image_data` — Return a (partial) image data array; missing keys are filled with safe defaults. Signature: `($pre, $context)`.
* `rationalseo_meta_description` — Return a string to override the meta description. An empty string `''` means "explicitly no description" and suppresses the tag. Signature: `($pre, $context)`.
* `rationalseo_post_seo_meta` — Return an array of post SEO meta to override the database lookup. Signature: `($pre, $post_id)`.
* `rationalseo_term_seo_meta` — Return an array of term SEO meta to override the database lookup. Signature: `($pre, $term_id)`.

= Per-value filters =

These filters run on the resolved value just before it is output. All use the signature `($value, $context)`.

* `rationalseo_document_title` — string. The `<title>` tag value before output.
* `rationalseo_canonical_url` — string. The `rel=canonical` URL before output.
* `rationalseo_robots` — array. The robots directives array (joined with `, ` for output).
* `rationalseo_og_locale` — string. `og:locale` value before output.
* `rationalseo_og_type` — string. `og:type` value (e.g. `website` / `article`) before output.
* `rationalseo_og_title` — string. `og:title` value before output.
* `rationalseo_og_description` — string. `og:description` value before output.
* `rationalseo_og_url` — string. `og:url` value before output.
* `rationalseo_og_site_name` — string. `og:site_name` value before output.
* `rationalseo_twitter_card_type` — string. `twitter:card` value before output.
* `rationalseo_twitter_title` — string. `twitter:title` value before output.
* `rationalseo_twitter_description` — string. `twitter:description` value before output.

= Skip filters =

Return a truthy value to suppress an entire output block. Signature: `($skip, $context)`.

* `rationalseo_skip_meta_description` — Skip the meta description tag entirely.
* `rationalseo_skip_canonical` — Skip the canonical link tag entirely.
* `rationalseo_skip_open_graph` — Skip all Open Graph tags entirely.
* `rationalseo_skip_twitter_cards` — Skip all Twitter Card tags entirely.

Example — disable Open Graph on the entire site:

    add_filter( 'rationalseo_skip_open_graph', '__return_true' );

= Schema (JSON-LD) filters =

RationalSEO emits a single JSON-LD `@graph` containing the sitewide Organization (or Person), WebSite, and WebPage nodes, plus an Article node on singular views. Two filters let you reshape it:

* `rationalseo_singular_schema_type` — string. The type resolved for a singular post type: `article` (default) keeps the Article entity, `none` omits the per-page entity while leaving the sitewide graph intact. Mirrors the per-post-type setting on the Schema tab. Signature: `($type, $post_type, $context)`.
* `rationalseo_schema` — array. The complete schema array (`@context` + `@graph`) just before output. Add, modify, or remove `@graph` nodes here. Returning an array with an empty `@graph` suppresses output entirely. Signature: `($schema, $context)`.

The `$context` array passed to both filters contains: `mode` (e.g. `singular`, `front_page`, `archive_term`), `queried_object`, `post_id` (the queried post ID on singular views, else 0), and `term_id`.

Example — output a Google-valid in-person Event for an `event_instance` post type from your post meta.

First, set that post type to "None" on the Schema tab so RationalSEO does not also emit an Article node. Then build the Event node from your own meta keys and append it to the graph (the node joins the same `@graph`, so the page still has a single JSON-LD script and keeps the sitewide Organization/WebSite/WebPage data).

Google requires `name`, `startDate`, and a `location` with both a `name` and a full `address` for a physical event; `image`, `endDate`, `eventAttendanceMode`, `description`, `organizer`, and `offers` are recommended. The example includes the required fields unconditionally and adds the recommended ones only when the underlying data exists:

    add_filter( 'rationalseo_schema', function ( $schema, $context ) {
        // Only act on single views of the target post type.
        if ( 'singular' !== $context['mode'] ) {
            return $schema;
        }
        $post = get_post( $context['post_id'] );
        if ( ! $post || 'event_instance' !== $post->post_type ) {
            return $schema;
        }

        // Replace every meta key below with the ones your post type actually uses.
        // Dates must be ISO 8601, e.g. 2026-09-01T09:00:00-07:00 (include the timezone).
        $start = get_post_meta( $post->ID, '_event_start', true );
        if ( empty( $start ) ) {
            return $schema; // startDate is required — no valid Event without it.
        }

        // Required: name, startDate, and a Place location with name + full address.
        $event = array(
            '@type'               => 'Event',
            '@id'                 => get_permalink( $post ) . '#event',
            'name'                => get_the_title( $post ),
            'startDate'           => $start,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'location'            => array(
                '@type'   => 'Place',
                'name'    => get_post_meta( $post->ID, '_venue_name', true ),
                'address' => array(
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => get_post_meta( $post->ID, '_venue_street', true ),
                    'addressLocality' => get_post_meta( $post->ID, '_venue_city', true ),
                    'addressRegion'   => get_post_meta( $post->ID, '_venue_region', true ),
                    'postalCode'      => get_post_meta( $post->ID, '_venue_postal', true ),
                    'addressCountry'  => get_post_meta( $post->ID, '_venue_country', true ),
                ),
            ),
        );

        // Recommended: add each only when its data is present.
        $end = get_post_meta( $post->ID, '_event_end', true );
        if ( ! empty( $end ) ) {
            $event['endDate'] = $end;
        }

        $image = get_the_post_thumbnail_url( $post, 'full' );
        if ( $image ) {
            $event['image'] = array( $image );
        }

        if ( has_excerpt( $post ) ) {
            $event['description'] = wp_strip_all_tags( get_the_excerpt( $post ) );
        }

        $organizer = get_post_meta( $post->ID, '_event_organizer', true );
        if ( ! empty( $organizer ) ) {
            $event['organizer'] = array(
                '@type' => 'Organization',
                'name'  => $organizer,
            );
        }

        // Admission/ticketing, if the event sells entry. Free events can omit this.
        $price = get_post_meta( $post->ID, '_event_price', true );
        if ( '' !== $price ) {
            $event['offers'] = array(
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => 'USD',
                'availability'  => 'https://schema.org/InStock',
                'url'           => get_permalink( $post ),
            );
        }

        $schema['@graph'][] = $event;
        return $schema;
    }, 10, 2 );

Online or hybrid events differ: set `eventAttendanceMode` accordingly and provide the stream URL as a `VirtualLocation` (`array( '@type' => 'VirtualLocation', 'url' => ... )`) instead of (or alongside) the physical `Place`.

The same pattern works for Product, Recipe, FAQPage, or any other Schema.org type — build the node from your meta and append it to `$schema['@graph']`. Setting the post type to "None" first prevents a duplicate Article node; leaving it on "Article" keeps both. Always confirm the final output on a live URL with Google's Rich Results Test, since it cannot read a local-only site.

= Action injection points =

These actions fire immediately before and after each social meta block is emitted. They fire only when the block is not skipped via the corresponding skip filter. Signature: `do_action( '...', $context )`.

* `rationalseo_before_open_graph` — Fires before the Open Graph tags are output.
* `rationalseo_after_open_graph` — Fires after the Open Graph tags are output.
* `rationalseo_before_twitter_cards` — Fires before the Twitter Card tags are output.
* `rationalseo_after_twitter_cards` — Fires after the Twitter Card tags are output.

Example — append a custom og:* tag after the standard Open Graph block:

    add_action( 'rationalseo_after_open_graph', function( $ctx ) {
        // Append extra og:* tags here.
        echo "<meta property=\"og:custom\" content=\"value\" />\n";
    } );

= The $context array =

All per-value filters, skip filters, and action hooks receive a `$context` array as their last argument. It is built by the internal `build_context()` method and always contains the following keys:

* `mode` — string. One of: `front_page`, `home`, `singular`, `archive_term`, `archive_post_type`, `archive_author`, `archive_date`, `search`, `404`, `fallback`.
* `queried_object` — mixed. Result of `get_queried_object()` at resolution time. May be `WP_Post`, `WP_Term`, `WP_Post_Type`, `WP_User`, or `null`.
* `post_id` — int. Queried post ID when on a singular view; `0` otherwise.
* `term_id` — int. Queried term ID when on a taxonomy archive; `0` otherwise.

= Worked example =

Override `og:type` to `event` for a custom post type, leaving all other post types unchanged:

    add_filter( 'rationalseo_og_type', function( $type, $context ) {
        if ( 'singular' === $context['mode'] && 'tribe_events' === get_post_type( $context['post_id'] ) ) {
            return 'event';
        }
        return $type;
    }, 10, 2 );

(`tribe_events` is illustrative — works for any CPT slug.)

== Changelog ==

= 1.1.0 =
* Added: Per-post-type schema control under a new Schema tab. Set any public post type to "None" to suppress RationalSEO's per-page Article entity when your theme or another plugin already outputs structured data for it. The sitewide Organization, WebSite, and WebPage data is always preserved.
* Added: `rationalseo_singular_schema_type` filter to override the resolved schema type per request.
* Added: `rationalseo_schema` filter to add, modify, or remove JSON-LD `@graph` nodes before output — e.g. to inject an Event or Product entity from post meta. See the "Schema (JSON-LD) filters" section for a worked Event example.
* Backward compatible: post types with no saved mapping continue to output the Article entity as before.

= 1.0.7 =
* Fixed: PHP 8.4 deprecation notice for an implicitly nullable constructor parameter.
* Improved: Replaces the core WordPress sitemap with RationalSEO's sitemap and advertises it in robots.txt (only when the RationalSEO sitemap is enabled).
* Tested up to WordPress 7.0.

= 1.0.6 =
* Open Graph: emit og:image:secure_url, og:image:type, og:image:width, og:image:height, og:image:alt when source data is available.
* Twitter Cards: emit twitter:image:alt when source data is available.
* Add filter and action hooks so themes and plugins can customize or extend output without forking the plugin. See the "Hooks for developers" section for the full reference.

= 1.0.5 =
* Improved: "Suggest All" now builds title and description around existing focus keyword when one is set
* Improved: AI response parsing handles markdown code fences from API
* Fixed: Sitemap URLs no longer redirect with trailing slash (breaks XML parsing)
* Fixed: Sitemaps now work even when rewrite rules are not flushed

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

= 1.0.5 =
AI "Suggest All" improvements and sitemap redirect fix.

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
