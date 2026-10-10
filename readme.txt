=== Menda Ads Manager ===
Contributors: ahmadhasandev711-web
Tags: ads, advertising, ad manager, interstitial, sponsored posts, analytics
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.3.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete ad management for WordPress: banners, interstitial ads, sticky video, website skin/takeover, sponsored articles, and analytics dashboard.

== Description ==

**Menda Ads Manager** gives you full control over every type of advertising on your WordPress site — all from one plugin, with no third-party ad network required.

= Features =

**Banner Ads**
* Upload your own images or paste custom HTML/embed codes
* Place ads via shortcode `[menda_ad placement="homepage-top"]` or widget
* Schedule start/end dates per ad
* Target by device (mobile / desktop)

**Interstitial Ads**
* Full-screen overlay ad between page navigations
* Configurable skip delay (default 5 seconds)
* Frequency control: once per session, once per day, or every page

**Sticky Video Ad**
* Floating video player pinned to any corner of the screen
* Supports MP4 (self-hosted), YouTube, and Vimeo
* One-click close button for the visitor

**Website Skin / Takeover**
* Changes the page background to the advertiser's image or color
* Narrows the content area to reveal the skin on both sides
* Schedule by date range — perfect for special events or product launches
* Click-tracking on the skin background

**Sponsored Articles**
* Dedicated post type with "Sponsored" badge
* Sponsor name, logo, and URL fields
* Fully integrated with categories and tags

**Analytics Dashboard**
* Tracks views and clicks per ad using IntersectionObserver (only counts real views)
* Calculates CTR automatically
* 7 / 14 / 30 / 60 / 90-day filters
* Weekly email report every Monday

= Security =
* All REST endpoints are nonce-verified
* IP addresses stored as SHA-256 hashes only (GDPR-friendly)
* All inputs sanitized and escaped with WordPress core functions
* Capability-based access control (`manage_menda_ads`)
* No external scripts or tracking pixels

= Translation =
* Full `.pot` file included
* Ships with Arabic (`ar`) translation
* Compatible with WPML, Polylang, TranslatePress, and Loco Translate

= Shortcode =

`[menda_ad placement="homepage-top"]`
`[menda_ad id="42"]`

== Installation ==

1. Upload the `menda-ads-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Menda Ads** in the admin sidebar
4. Create your first ad under **Add New Ad**

== Frequently Asked Questions ==

= How do I add a banner to my theme? =

Use the shortcode `[menda_ad placement="your-placement-slug"]` in any post, page, or widget. First create a placement under Ad → Placements and assign it to your ad.

= Does this slow down my site? =

No. Analytics tracking uses `navigator.sendBeacon` (fire-and-forget) and `IntersectionObserver` so it never blocks page rendering.

= Is it compatible with caching plugins? =

Yes. The tracking is done via JavaScript after page load, so it works regardless of full-page caching.

= How do I display ads in Arabic? =

Activate the plugin and switch your WordPress language to Arabic. The full translation is included.

= Does it work on Arabic + English sites? =

Yes. Target All languages, or only Arabic / only English (WPML, Polylang, TranslatePress). Sponsored articles can be translated; ads stay one record with language targeting.

= Which currencies are supported? =

Site default plus per-ad override: USD, EUR, GBP, EGP, SAR, AED, QAR, KWD, BHD, OMR, JOD, MAD. Analytics never adds dollars and riyals into one total.

= Who can open Menda Ads? =

Administrators always can. Editors and Authors can still write normal posts; they do not see the ads screens unless an Administrator raises **Settings → Who can manage ads**.

== Screenshots ==

1. Main dashboard with KPI cards and top-performing ads table
2. Add New Ad screen with all settings
3. Analytics page with period filters
4. Settings page — interstitial, sticky video, and skin configuration
5. Website skin active on front-end
6. Interstitial ad with skip countdown

== Changelog ==

= 1.1.5 =
* Named inventory: header, in-article, sidebar, below-article, mobile sticky 320×50
* Auto-insert after paragraph 2/4 and below articles; optional archive injection
* Gutenberg, Elementor, and classic widget pick placements from a dropdown
* Mobile sticky footer is CSS-hidden on desktop (cache-safe)
* Interstitial is site-wide, skips the homepage, waits N pageviews (default 3)
* Admin: metabox tabs, category checkboxes, list columns, rotation weight
* Shared front.js, “Ad” label, reserved slot size to reduce layout shift
* Geo-targeted ads stay hidden when the country header is missing (configurable)

= 1.1.3 =
* New: site currency (USD, EGP, SAR, AED, and more) plus per-ad currency — reports no longer hardcode $
* New: language targeting (All / Arabic / English / active WPML-Polylang-TranslatePress languages)
* Fix: WPML page targeting used type "page" for every ID; translations of posts now resolve
* Compatibility: wpml-config.xml — ads stay one object with language targeting; sponsored articles are translatable

= 1.1.2 =
* Fix: Administrators can open Ads and Sponsored Articles even when the custom capability was missing from the database
* New: Settings control for whether Editors / Authors may manage ads (default: Administrators only)

= 1.1.1 =
* Fix: admin menu and Settings 403 when manage_menda_ads was not granted on activate

= 1.1.0 =
* Security: custom HTML no longer allows script tags without unfiltered_html; CPT caps mapped to manage_menda_ads
* Security: country targeting uses CDN geo headers only (no ipapi.co, no X-Forwarded-For)
* Compatibility: Gutenberg block (menda/unit) and Elementor widget
* Compatibility: website skin uses side rails + html padding instead of shrinking theme wrappers
* Performance: full-page cache is bypassed only when an overlay will actually render
* Performance: analytics log retention (default 90 days)
* Fix: CSV export URL, A/B variant cookie, adblock-resistant class names, inline front CSS

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade needed.
