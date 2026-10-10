# Menda Ads Manager 📢

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg?logo=php&logoColor=white)](https://php.net)
[![Elementor](https://img.shields.io/badge/Elementor-Compatible-92003B.svg?logo=elementor&logoColor=white)](https://elementor.com)
[![Gutenberg](https://img.shields.io/badge/Gutenberg-Block%20Ready-black.svg?logo=wordpress&logoColor=white)](https://wordpress.org/gutenberg/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![GDPR Compliant](https://img.shields.io/badge/GDPR-Compliant%20(SHA--256)-success.svg)](https://gdpr.eu/)

**Menda Ads Manager** is an enterprise-grade, self-hosted advertising, sponsorship, and monetization engine built specifically for WordPress publishers and news portals. 

Engineered with modern object-oriented PHP (PSR-4), strict WordPress security standards, and zero-layout-shift front-end architecture, it delivers high-impact ad formats without compromising site speed or Core Web Vitals.

---

## 🌟 Key Features & Ad Formats

### 1. High-Impact Monitization Formats
* **Website Skin / Wallpaper Takeover:** Full-page background takeover with side-rail gutter tracking (`click_left`, `click_right`, `click_top`), fixed viewport positioning, and automatic wrapper padding.
* **Interstitial Overlay Ads:** Full-screen interstitial ads displayed between page navigations. Features configurable skip timers (e.g., 5s countdown) and frequency capping (once per session, once per day, or pageview intervals).
* **Floating Sticky Video Ads:** Pinned picture-in-picture video ad in any corner of the viewport. Supports self-hosted MP4, YouTube, and Vimeo with responsive dismiss buttons.
* **Standard Display Banners:** Pre-configured IAB inventory (Leaderboards 728×90, Medium Rectangles 300×250, Half Page 300×600, Mobile Sticky 320×50).
* **Sponsored Articles Engine:** Dedicated Custom Post Type (`menda_sponsored`) with advertiser attribution, disclosure badges, sponsor logos, and category/archive integration.

### 2. Intelligent Placement & Automated Injection
* **Automated Article Injection:** Injects ads seamlessly into post content (e.g., after paragraph 2, after paragraph 4, below article, or between archive query loops).
* **Gutenberg Custom Block:** Native `menda/unit` block built with `@wordpress/blocks` and `@wordpress/block-editor` for the Block Editor and Full Site Editing (FSE).
* **Elementor Custom Widget:** Custom Elementor widget integration for visual page building.
* **Shortcodes & Classic Widgets:** Supports flexible shortcodes (`[menda_ad placement="..."]` or `[menda_ad id="..."]`) and legacy sidebar widgets.

### 3. Real-Time Analytics & Anti-AdBlock
* **True Viewability (IntersectionObserver):** Impressions are only recorded when ads enter the user's active viewport (threshold-based), eliminating false impression counts.
* **Non-Blocking Beacons:** Utilizes `navigator.sendBeacon` for zero-impact, asynchronous front-end event logging.
* **AdBlock Detection:** Built-in client-side ad-block detection calculates actual ad-block percentages across your audience.
* **Multi-Currency Revenue Tracking:** Native support for international and regional currencies (`USD`, `EUR`, `GBP`, `EGP`, `SAR`, `AED`, `QAR`, `KWD`, `BHD`, `OMR`, `JOD`, `MAD`) with isolated calculations.
* **Automated Weekly Email Digests:** Scheduled via `WP-Cron` to deliver performance metrics directly to administrators.

---

## ⚡ Performance & Core Web Vitals

* **Zero Cumulative Layout Shift (CLS):** Pre-allocates fixed dimensional CSS containers before media assets load to safeguard Google Core Web Vitals.
* **Full-Page Cache Compatible:** Front-end tracking and dynamic rotation logic execute client-side via REST endpoints, ensuring complete compatibility with WP Rocket, LiteSpeed Cache, Cloudflare, and server-side Varnish/Nginx caches.
* **Minimal Footprint:** Vanilla JavaScript runtime with zero external dependencies (no jQuery dependency on front-end delivery).

---

## 🔒 Security & Privacy Engineering

* **GDPR & Privacy Compliant:** Zero external tracking pixels or third-party servers. All visitor IPs are anonymized using **SHA-256 hashing with daily salting** before database storage.
* **Context-Aware Escaping & Sanitization:** Strict usage of WordPress security primitives: `sanitize_text_field`, `absint`, `esc_html`, `esc_attr`, and `esc_url`.
* **REST API Nonce Verification:** Front-end tracking endpoints validate nonces and verify request integrity.
* **Granular Role-Based Access Control (RBAC):** Custom capability `manage_menda_ads` dynamically mapped via the `user_has_cap` filter, allowing administrators to delegate ad management to Editors/Authors securely.
* **SQL Injection Prevention:** All database operations utilize `$wpdb->prepare()` and schema modifications are handled through WordPress core `dbDelta`.

---

## 🏗 System Architecture & Directory Structure

Menda Ads Manager is structured following modern WordPress coding standards and clean architectural patterns:

```
menda-ads-manager/
├── admin/
│   └── views/                  # Admin UI templates (Dashboard, Analytics, Settings)
├── assets/
│   ├── css/                    # Admin & Front-end styles (RTL-ready)
│   └── js/                     # Modular Front-end observer & Admin JS
├── blocks/
│   └── unit/editor.js          # Gutenberg custom block implementation
├── includes/
│   ├── class-admin.php         # Admin menu, settings API, metaboxes
│   ├── class-ads-widget.php    # Classic WordPress widget integration
│   ├── class-analytics.php     # REST API tracking, reporting, Cron scheduler
│   ├── class-cache.php         # Object caching & full-page cache handlers
│   ├── class-context.php       # Environment & REST namespace routing
│   ├── class-display.php       # Ad rendering engine, rotation & targeting
│   ├── class-elementor-widget.php # Elementor Widget integration
│   ├── class-i18n.php          # Internationalization & text domains
│   ├── class-insert.php        # In-content automated paragraph injection
│   ├── class-installer.php     # Activation hooks, DB migrations (dbDelta), caps
│   ├── class-integrations.php  # Gutenberg & Elementor registry
│   ├── class-interstitial.php  # Full-screen overlay ad controller
│   ├── class-post-types.php    # Custom Post Types ('menda_ad', 'menda_sponsored')
│   ├── class-skin.php          # Background wallpaper takeover controller
│   ├── class-slots.php         # Inventory registry & taxonomy manager
│   └── class-sticky-video.php  # Floating video controller
├── languages/                  # Translation catalogs (.pot, .po, .mo)
├── menda-ads-manager.php       # Plugin bootstrap, constants & PSR-4 autoloader
├── readme.txt                  # WordPress.org standard plugin documentation
├── uninstall.php               # Clean uninstallation & DB cleanup
└── wpml-config.xml             # WPML language configuration mapping
```

### PSR-4 Autoloading Pattern
```php
spl_autoload_register( function ( string $class ): void {
    $prefix = 'Menda_Ads\\';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = str_replace( [ $prefix, '\\' ], [ '', DIRECTORY_SEPARATOR ], $class );
    $file     = MENDA_ADS_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
```

---

## 🌐 Multilingual & RTL Ready

* **Internationalization:** Fully localized using standard gettext functions (`__()`, `_e()`, `esc_html__()`).
* **Ready Translations:** Ships with full Arabic (`ar`) translations (`.po` / `.mo`).
* **Multi-Language Plugins:** Seamless compatibility with **WPML**, **Polylang**, **TranslatePress**, and **Loco Translate**. Includes `wpml-config.xml` to synchronize settings and isolate language-specific targeting.

---

## 🚀 Installation & Usage

### Installation
1. Clone or download the repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/ahmadhasandev711-web/Menda_Ads.git menda-ads-manager
   ```
2. Activate the plugin via **Plugins → Installed Plugins** in the WordPress admin dashboard.
3. Access **Menda Ads** from the WordPress admin menu to configure your ad inventory and placements.

### Shortcode Examples
* Display an ad by placement slug:
  ```html
  [menda_ad placement="header-728x90"]
  ```
* Display a specific ad by ID:
  ```html
  [menda_ad id="142"]
  ```

---

## 👨‍💻 Developer Information

Developed and maintained by **Ahmad Hasan**  
* GitHub: [@ahmadhasandev711-web](https://github.com/ahmadhasandev711-web)

## 📄 License

This project is open-source software licensed under the **GNU General Public License v2.0 or later** (GPL-2.0-or-later). See the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
