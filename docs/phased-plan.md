---
plan: RationalSEO 1.0.6 Extensibility Pass
target_plugin: /Volumes/Passport/local-sites/development/app/public/wp-content/plugins/rationalseo
target_branch_base: main
target_version: 1.0.6
status: approved
---

# RationalSEO 1.0.6 — Theme/Plugin Extensibility Pass

## Phase Status

| Phase | Status | Completed |
|-------|--------|-----------|
| 1. Structural correctness + initial short-circuit filters | pending | — |
| 2. Per-value filters, skip filters, meta-source short-circuits | pending | — |
| 3. Action injection points, version bump, documentation | pending | — |

## Why this exists

A theme had to fork `class-frontend.php` behavior to fix social-card output and inject custom tags. That kind of override blocks automatic plugin updates. Goal: ship the structural correctness fixes the theme actually needed, plus a documented hook surface so future integrations are theme-only — no plugin code changes required.

## Resolved open questions (decided 2026-04-27)

1. **Front-page detection in `get_social_image_data()`:** Keep current behavior — no explicit `is_front_page()` branch. Falls through to `is_singular()` for static front pages, or to settings defaults otherwise. Phase 1 must be byte-equivalent on default paths; an explicit branch would change behavior.
2. **`$context['queried_object']` shape:** Pass through `get_queried_object()` raw. Value is `WP_Post` / `WP_Post_Type` / `WP_User` / `WP_Term` / `null` depending on route. Themes already handle the raw shape; normalization adds permanent surface area.
3. **`rationalseo_skip_robots` and `rationalseo_skip_schema`:** Defer. Every shipped hook is a contract we can't break. Easy to add later, hard to remove.

## Source of truth

All extension-point names, signatures, and decisions in this plan are normative. Sub-agents follow this document and only deviate on user instruction.

## Conventions

- **WordPress coding standards** per `~/.claude-personal/rules/wordpress.md` (snake_case, tabs, Yoda, full PHP tags, sanitize-on-input/escape-on-output).
- **Filter naming**: `rationalseo_<thing>` for value transforms, `rationalseo_skip_<block>` for whole-block opt-outs, `rationalseo_before_<block>` / `rationalseo_after_<block>` for action injection points.
- **Short-circuit filters** (`rationalseo_og_image_data`, `rationalseo_meta_description`, `rationalseo_post_seo_meta`, `rationalseo_term_seo_meta`): return non-null to bypass internal resolution; return null to fall through. Keep current names — do not rename to `pre_*`. Document the contract loudly in PHPDoc + readme.
- **Per-value filter signature**: `apply_filters( 'rationalseo_<tag>', $value, $context )` where `$context` is an associative array providing the queried object, post/term IDs, and the resolution mode (`'singular'`, `'home'`, `'archive_term'`, `'archive_post_type'`, `'archive_author'`, `'archive_date'`, `'search'`, `'404'`, `'front_page'`, `'fallback'`).
- **Skip filter signature**: `apply_filters( 'rationalseo_skip_<block>', false, $context )`. Short-circuit when truthy.
- **Action signature**: `do_action( 'rationalseo_before_<block>', $context )` / `do_action( 'rationalseo_after_<block>', $context )`.
- **Caching**: Filters fire after cache lookup but before cache store. The cached value reflects the filtered result so per-request callbacks run once.
- **Acceptance**: every phase ends with the existing site rendering identically when no filters/actions are registered (default-path equivalence). Every phase commits cleanly on `main` with a descriptive message.

## Out of scope

- A `pre_html` / "rewrite my entire output" hook (footgun; per-value + skip filters cover legitimate needs).
- Schema (`output_schema()`) extension surface — separate future pass.
- Sitemap extensibility — separate future pass.
- `rationalseo_skip_robots` and `rationalseo_skip_schema` — deferred per Q3 above.
- Renaming existing filters (none currently exist; the four short-circuit filters are introduced in Phases 1-2, not renamed).

---

## Phase 1 — Structural correctness + initial short-circuit filters

**Goal:** Ship the social-card output a theme would otherwise have to fork, plus the two short-circuit filters mentioned in the feedback.

### Files

- `includes/class-frontend.php`

### Tasks

1. **Replace `get_social_image()` with `get_social_image_data()`** that returns a structured array:
   ```php
   array(
       'url'        => string, // canonical URL (https when site is https)
       'secure_url' => string, // https URL (matches url when site is https)
       'type'       => string, // mime type, e.g. 'image/jpeg'
       'width'      => int,    // 0 when unknown
       'height'     => int,    // 0 when unknown
       'alt'        => string, // attachment alt or '' when unknown
       'id'         => int,    // attachment ID or 0 when image is a raw URL
   )
   ```
   - When source is an attachment ID (featured image): resolve via `wp_get_attachment_metadata()` for width/height/file, `wp_get_attachment_url()` for URL, `get_post_meta( $id, '_wp_attachment_image_alt', true )` for alt, `get_post_mime_type()` for mime.
   - When source is a settings URL string (`og_image` post meta, default social image, site logo): keep `url` populated; leave width/height = 0, type = '', alt = '' unless we can derive (don't fetch remote — too expensive).
   - When site scheme is `https`, `secure_url === url`. When `http`, set `secure_url` to the `https://` variant of the same URL using `set_url_scheme( $url, 'https' )`. When the URL has no clear https variant (rare), omit `og:image:secure_url`.
   - Cache as `$this->cached_social_image_data` (replacing `$this->cached_social_image`).
   - Preserve the existing default-path resolution order: `is_home()` blog page meta → `is_singular()` post meta + featured → term `og_image` → settings default → site logo. Per Q1 above, do **not** add an explicit `is_front_page()` branch — current fall-through behavior is intentional.

2. **Add `rationalseo_og_image_data` short-circuit filter** at the top of `get_social_image_data()`:
   ```php
   $pre = apply_filters( 'rationalseo_og_image_data', null, $this->build_context() );
   if ( null !== $pre ) {
       $this->cached_social_image_data = $this->normalize_image_data( $pre );
       return $this->cached_social_image_data;
   }
   ```
   - `normalize_image_data()` ensures all keys exist with safe defaults so a partial array from a theme doesn't crash the printer.

3. **Add `rationalseo_meta_description` short-circuit filter** at the top of `get_description()`:
   ```php
   $pre = apply_filters( 'rationalseo_meta_description', null, $this->build_context() );
   if ( null !== $pre ) {
       $this->cached_description = (string) $pre;
       return $this->cached_description;
   }
   ```

4. **Update `output_open_graph()`** to emit:
   - `og:image` (URL — unchanged)
   - `og:image:secure_url` (when available)
   - `og:image:type` (when known)
   - `og:image:width` (when > 0)
   - `og:image:height` (when > 0)
   - `og:image:alt` (when non-empty)

   Continue to skip the entire `og:image:*` block when `url` is empty.

5. **Update `output_twitter_cards()`** to emit `twitter:image:alt` when alt is non-empty. Twitter image URL still pulled from the same image data structure.

6. **Add private helper `build_context()`** that returns the standard `$context` array used across all filters/actions:
   ```php
   array(
       'mode'           => string, // 'front_page' | 'home' | 'singular' | 'archive_term' | 'archive_post_type' | 'archive_author' | 'archive_date' | 'search' | '404' | 'fallback'
       'queried_object' => mixed,  // result of get_queried_object() at resolution time, or null — passed through raw per Q2
       'post_id'        => int,    // 0 when not applicable
       'term_id'        => int,    // 0 when not applicable
   )
   ```
   This is the same shape passed to every filter/action introduced later, so theme developers see one signature.

### Acceptance criteria

- `<meta property="og:image">` still emitted when an image resolves; new tags emitted alongside it when their values are known.
- Default rendering on a site with no filters registered emits the new tags only when the source data supports them (no empty/zero values printed).
- `wp_head` output passes Facebook Sharing Debugger and Twitter Card Validator without warnings about missing image metadata, on a singular post with a featured image.
- Returning a partial array from `rationalseo_og_image_data` (e.g. only `url`) does not warning/notice/fatal — `normalize_image_data()` fills defaults.
- Returning `''` from `rationalseo_meta_description` suppresses the `<meta name="description">` tag (empty string is treated as "explicitly no description"). Returning `null` falls through to default resolution.
- All existing `is_*()` branches in `get_social_image()` (home page meta → singular post meta + featured → term og_image → settings default → site logo) remain functionally equivalent in `get_social_image_data()`.

### Sub-agent handoff (Sonnet)

> Edit `/Volumes/Passport/local-sites/development/app/public/wp-content/plugins/rationalseo/includes/class-frontend.php` only. Implement Phase 1 tasks 1-6 above. Follow the WordPress coding standard (tabs, Yoda, full PHP tags, snake_case, sanitize-on-input/escape-on-output). Preserve the existing default-path resolution order. Do not modify any other file. Do not bump version. Return the diff and confirm against each acceptance criterion.

---

## Phase 2 — Per-value filters, skip filters, and meta-source short-circuits

**Goal:** Every value the printer emits passes through one filter, every block can be skipped, and themes that synthesize SEO data can override post/term meta resolution wholesale.

### Files

- `includes/class-frontend.php`

### Tasks

1. **Per-value filters** — add `apply_filters()` immediately before the `printf()` (or before the value is returned from a getter) for each:

   *Document title*
   - `rationalseo_document_title` on the resolved title returned by `get_title()`.

   *Meta tags (printer level)*
   - `rationalseo_canonical_url` on resolved canonical URL in `get_canonical()`.
   - `rationalseo_robots` on the array of robots directives in `get_robots()` (filter receives the array, not the joined string).

   *Open Graph*
   - `rationalseo_og_locale`
   - `rationalseo_og_type`
   - `rationalseo_og_title`
   - `rationalseo_og_description`
   - `rationalseo_og_url`
   - `rationalseo_og_site_name`

   *Twitter*
   - `rationalseo_twitter_card_type`
   - `rationalseo_twitter_title`
   - `rationalseo_twitter_description`

   All filters use the `($value, $context)` signature with `$context` from `build_context()`.

2. **Skip filters** — at the top of each `output_*` method, short-circuit when truthy:
   - `rationalseo_skip_meta_description` in `output_description()`.
   - `rationalseo_skip_canonical` in `output_canonical()`.
   - `rationalseo_skip_open_graph` in `output_open_graph()`.
   - `rationalseo_skip_twitter_cards` in `output_twitter_cards()`.

   Default value `false`. Per Q3 above, `rationalseo_skip_robots` and `rationalseo_skip_schema` are deferred.

3. **`rationalseo_post_seo_meta` short-circuit** at the top of `get_post_seo_meta()`:
   ```php
   $pre = apply_filters( 'rationalseo_post_seo_meta', null, $post_id );
   if ( null !== $pre && is_array( $pre ) ) {
       $this->post_meta_cache = wp_parse_args( $pre, array(
           'title'         => '',
           'desc'          => '',
           'noindex'       => '',
           'canonical'     => '',
           'og_image'      => '',
           'focus_keyword' => '',
       ) );
       return $this->post_meta_cache;
   }
   ```
   This is the cleanest path for themes that synthesize SEO data: one callback, all values, one place — instead of registering a callback per per-value filter.

4. **`rationalseo_term_seo_meta` short-circuit** at the top of `get_term_seo_meta()`. Same pattern, with the term meta key set:
   ```php
   wp_parse_args( $pre, array(
       'title'     => '',
       'desc'      => '',
       'noindex'   => '',
       'canonical' => '',
       'og_image'  => '',
   ) );
   ```

### Acceptance criteria

- With no filters registered: byte-for-byte identical `wp_head` output as before Phase 2 (verified via diff against a captured Phase 1 baseline).
- A trivial filter (e.g. `add_filter( 'rationalseo_og_title', fn( $t ) => 'TEST', 10, 2 )`) replaces the OG title and only the OG title — `<title>`, `og:description`, `twitter:title` unchanged.
- `add_filter( 'rationalseo_skip_open_graph', '__return_true' )` removes all `og:*` tags and nothing else.
- `add_filter( 'rationalseo_post_seo_meta', fn( $pre, $post_id ) => array( 'title' => 'X', 'desc' => 'Y' ), 10, 2 )` produces title `X` and description `Y` for that post regardless of what's stored in `_rationalseo_*` meta keys, and missing keys (`og_image` etc.) fall through to defaults rather than warning.

### Sub-agent handoff (Sonnet)

> Edit `/Volumes/Passport/local-sites/development/app/public/wp-content/plugins/rationalseo/includes/class-frontend.php` only. Implement Phase 2 tasks 1-4 above. Filters use `($value, $context)` signature with `$context` from the existing `build_context()` helper added in Phase 1. Skip filters short-circuit at the top of each `output_*` method. Preserve byte-for-byte equivalence when no filters are registered. Return the diff and confirm against each acceptance criterion.

---

## Phase 3 — Action injection points, version bump, documentation

**Goal:** Stop forcing themes to hook `wp_head` at priority 3 and pray about ordering. Ship the version, document the contract, register the change.

### Files

- `includes/class-frontend.php`
- `rationalseo.php` (header version, `RATIONALSEO_VERSION` constant)
- `readme.txt` (stable tag, changelog, new "Hooks for developers" section)

### Tasks

1. **Action injection points** — wrap each block:
   - `do_action( 'rationalseo_before_open_graph', $context )` immediately before the first OG `printf()` in `output_open_graph()`, `do_action( 'rationalseo_after_open_graph', $context )` immediately after the last.
   - Same for `output_twitter_cards()` (`rationalseo_before_twitter_cards` / `rationalseo_after_twitter_cards`).
   - Skip-filter short-circuit happens before the `_before_` action — if the block is skipped, the actions don't fire (the block doesn't exist).

2. **Bump version** in three places (must match):
   - `rationalseo.php` plugin header `Version: 1.0.6`
   - `rationalseo.php` `define( 'RATIONALSEO_VERSION', '1.0.6' );`
   - `readme.txt` `Stable tag: 1.0.6`

3. **`readme.txt` changelog entry** under `== Changelog ==`:
   ```
   = 1.0.6 =
   * Open Graph: emit og:image:secure_url, og:image:type, og:image:width, og:image:height, og:image:alt when source data is available.
   * Twitter Cards: emit twitter:image:alt when source data is available.
   * Add filter and action hooks so themes and plugins can customize or extend output without forking the plugin. See the "Hooks for developers" section below.
   ```

4. **`readme.txt` "Hooks for developers" section** — new section before `== Changelog ==`. Document:
   - Per-value filters (table: filter name → $value type → when it fires).
   - Short-circuit filters (`rationalseo_og_image_data`, `rationalseo_meta_description`, `rationalseo_post_seo_meta`, `rationalseo_term_seo_meta`) with explicit "return null to fall through, return a value to short-circuit" contract — call this out in bold or its own paragraph.
   - Skip filters with `__return_true` example.
   - Action injection points with one short example (`add_action( 'rationalseo_after_open_graph', function( $ctx ) { /* echo extra og:* tags */ } )`).
   - The `$context` array shape (mode, queried_object, post_id, term_id).
   - One worked example: "Mark a CPT's OG type as `event` instead of `article`" using `rationalseo_og_type`.

5. **PHPDoc on every new filter/action** in `class-frontend.php`. WordPress core convention: `@since 1.0.6`, `@param` for each, and a one-line description. The four short-circuit filters get an explicit "Return non-null to short-circuit default resolution." line in their PHPDoc — this is the contract the feedback flagged.

### Acceptance criteria

- `wp_head` of a site with no theme overrides emits identical tags to Phase 2 baseline.
- A theme registering `add_action( 'rationalseo_after_open_graph', $cb )` sees `$cb` invoked once per request, after the last OG `printf` and before the closing `<!-- /RationalSEO -->` comment.
- `RATIONALSEO_VERSION === '1.0.6'`, plugin header version is `1.0.6`, readme stable tag is `1.0.6` — all three match (mismatch is a release blocker per the recent `cb9deba fix: sync readme.txt stable tag with plugin version` lesson).
- `readme.txt` "Hooks for developers" section lists every filter and action introduced in Phases 1-3, with the short-circuit contract called out explicitly.
- `git log` shows three phase commits with descriptive messages tying back to this plan.

### Sub-agent handoff (Sonnet)

> Edit the three files listed in Phase 3. Add the action injection points, bump version in all three locations, write the changelog entry and "Hooks for developers" section in `readme.txt`. Add `@since 1.0.6` PHPDoc to every filter/action introduced in Phases 1-3 (re-visit Phase 1/2 additions if PHPDoc was thin). Return the diff and confirm against each acceptance criterion.

---

## QA between phases

- After each phase, capture `wp_head` of a representative front-page + singular-post + category-archive on the local dev site and diff against the prior baseline. Phase 1 baseline = current `main`. Phase 2 baseline = post-Phase-1. Phase 3 baseline = post-Phase-2.
- Use Chrome DevTools MCP for visual verification on a real page (View Source → confirm new tags present, no PHP warnings/notices in error log).
- Sub-agent runs the captures; Opus reviews diffs and decides pass/fail. No phase moves forward with regressions.

## Test plan summary

Manual checks per phase tied to acceptance criteria above. Automated tests are out of scope — the plugin doesn't currently have a PHPUnit suite, and adding one is its own project.

## Rollout

- Three commits on `main`, one per phase.
- Tag `1.0.6` after Phase 3 commit lands and QA passes.
- Deploy via existing `build.sh` / SVN process (`rationalwp-deploy:deploy-wp-plugin` skill).
- No data migration needed — purely additive hook surface and tag emission.
