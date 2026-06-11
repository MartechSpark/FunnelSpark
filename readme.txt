=== MarTech Spark Conversion Funnel Mapper ===
Contributors: paulneumyer, martechspark
Tags: sales funnel, funnel builder, GA4, conversion tracking, marketing funnel, funnel visualization, analytics
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, visualize, and track your marketing funnels with live GA4 conversion data — right inside WordPress.

== Description ==

**MarTech Spark Conversion Funnel Mapper** lets you visually map your sales funnel and overlay real GA4 conversion data on every step — so you can see exactly where leads are dropping off.

No more guessing. No more switching between tools. Your funnel. Your data. One screen.

= What This Plugin Does =

* **Visual Funnel Builder** — drag-and-drop funnel steps (opt-in pages, sales pages, upsells, thank-you pages) onto an intuitive canvas. Connect them with directional arrows to map your customer journey.
* **Live GA4 Data Overlay** — connect your GA4 property and see real sessions, conversion rates, and drop-off percentages on each funnel step automatically.
* **Funnel Management Dashboard** — create and manage multiple funnels for different campaigns, products, or clients.
* **Export-Ready** — export your funnel map as a PNG to share with clients or your team.
* **Lightweight & Fast** — zero external dependencies. No page builders required. Works with any WordPress theme.

= Perfect For =

* Small business owners who want to understand their marketing funnel without expensive SaaS tools
* Marketing consultants managing client funnels
* Coaches, course creators, and service providers running lead generation campaigns

= Built By MarTech Spark =

This plugin is built and maintained by [MarTech Spark](https://martechspark.com) — a fractional marketing consultancy helping small businesses build smarter marketing systems.

== Installation ==

1. Upload the `funnelspark` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **FunnelSpark → Settings** and follow the GA4 setup guide
4. Go to **FunnelSpark → New Funnel** to start building

= GA4 Setup (5 minutes) =

1. Go to [Google Cloud Console](https://console.cloud.google.com/) and create or select a project
2. Enable the **Google Analytics Data API**
3. Go to **APIs & Services → OAuth consent screen** and configure it
4. Go to **Credentials → Create Credentials → OAuth 2.0 Client ID** (type: Web application)
5. Add the redirect URI shown in FunnelSpark → Settings under Authorized redirect URIs
6. Copy the **Client ID** and **Client Secret** into FunnelSpark → Settings and save
7. Click **Connect Google Analytics** and authorize with your Google account
8. Copy your **GA4 Property ID** from GA4 → Admin → Property Settings

== Frequently Asked Questions ==

= Does this work without GA4? =
Yes. You can use the visual funnel builder without GA4. Live data overlay requires a GA4 connection.

= Does this plugin slow down my site? =
No. The plugin is admin-only. It adds zero code to your public-facing site.

= Is my GA4 data stored on your servers? =
Never. All GA4 data is fetched directly from Google's API to your WordPress site. MarTech Spark does not store or access your analytics data.

= How many funnels can I create? =
Unlimited.

= Does it work with WooCommerce? =
Yes. Map your WooCommerce pages (shop, product, cart, checkout, thank you) as funnel steps and track conversion rates with GA4.

== External Services ==

FunnelSpark connects to two external services, both only from the WordPress admin area. No external connections are made on your public-facing site.

= Google Analytics Data API =

When you connect a GA4 property, FunnelSpark communicates with the following Google endpoints:

* `https://accounts.google.com` — OAuth 2.0 authorization
* `https://oauth2.googleapis.com` — token exchange and refresh
* `https://analyticsdata.googleapis.com` — fetching GA4 report data

These requests are made only after you explicitly authorize your Google account through the OAuth flow in FunnelSpark → Settings. No GA4 data is transmitted to MarTech Spark or any third party — data flows directly from Google to your WordPress site.

* Google Privacy Policy: https://policies.google.com/privacy
* Google Terms of Service: https://policies.google.com/terms

= MarTech Spark Promo Feed =

FunnelSpark periodically fetches a small JSON file from `https://martechspark.com/funnelspark-promo.json` to display promotional content in the plugin editor sidebar. This request is made only on admin pages and is cached for 1 hour. The request includes the plugin version and your site URL in the HTTP user-agent string (`FunnelSpark/x.x.x`). No personal data or analytics data is transmitted.

* MarTech Spark Privacy Policy: https://martechspark.com/privacy-policy/

== Screenshots ==

1. FunnelSpark canvas — drag-and-drop funnel builder with live GA4 data badges
2. Dashboard — manage all your funnels in one place
3. Settings — connect your GA4 property in minutes
4. Live data overlay — sessions and conversion rates on every funnel step

== Changelog ==

= 1.3.1 =
* Fix: editor and settings pages loaded without styles/scripts — admin asset hook names are now taken from the add_menu_page()/add_submenu_page() return values instead of hardcoded strings, which broke when the menu title changed to "Funnel Mapper"

= 1.3.0 =
* Security/Review: renamed all short "fs_"/"FS" prefixes (classes, constants, AJAX actions, nonce, post type, post/user meta keys, transients, script/style handles, and the localized JS object) to the unique "funnelspark_" prefix per WordPress.org guidelines — includes an automatic one-time data migration for existing installs
* Security: removed the nonce-less ?fs_promo_refresh URL parameter (promo refresh is now AJAX-only with nonce + capability checks)
* Security: canvas JSON is now strictly validated — nodes/connections must be lists of arrays; unknown keys and malformed entries are discarded before saving
* Security: save/delete/duplicate funnel AJAX handlers now verify the post type and per-post capabilities (edit_post / delete_post)
* Security: added wp_unslash() + sanitization to all remaining $_GET/$_POST inputs (OAuth callback, settings, GA4 requests)
* Fix: post type labels now use the correct text domain

= 1.2.11 =
* Rename plugin to MarTech Spark Conversion Funnel Mapper; update slug/text domain
* Fix: replace inline <style> echo with wp_add_inline_style() for editor submenu hiding
* Fix: sanitize canvas_data POST input and restrict canvas JSON to known keys/fields
* Fix: sanitize $_GET['fs_ga4_error'] before output in settings template
* Fix: lower admin menu position from 4 to 58
* readme: remove unsupported "the only" promotional claim and upsell link
* readme: add martechspark to Contributors list

= 1.2.10 =
* Fix: funnel steps with a URL configured but zero GA4 sessions now show a "0 Sessions" badge instead of no badge

= 1.2.9 =
* Fix: GA4 query now uses EXACT path matching (both with and without trailing slash) instead of CONTAINS — prevents the home page "/" filter from pulling in all site traffic and stops unrelated pages from being matched to funnel nodes
* Fix: removed loose startsWith fuzzy path matching in the overlay — nodes with no exact GA4 match now show no badge instead of showing sessions from a wrong page

= 1.2.8 =
* Fix: conversion-step nodes now correctly show sessions as conversions and calculate CVR against the predecessor step's traffic (e.g. 2 sessions / 9 predecessor sessions = 22.2% CVR)

= 1.2.7 =
* Update: promo JSON cache reduced from 24 hours to 1 hour
* Update: Promo Sidebar section removed from Settings page

= 1.2.6 =
* Fix: adding nodes to an existing (saved) funnel no longer overwrites earlier nodes — nodeCounter now tracks the highest loaded ID so new nodes always get a unique ID

= 1.2.5 =
* New: click any connection arrow to select it — the right panel shows From/To labels and a Delete Connection button; clicking empty canvas deselects

= 1.2.4 =
* Fix: connection × delete button now reliably appears when hovering over a connection — replaced SVG pointer-events approach (broken in all browsers when parent SVG has pointer-events:none) with an HTML button overlay and canvas-level proximity detection

= 1.2.3 =
* Fix: dropping a node from the palette now auto-selects it so the inspector always reflects the new node — prevents Ad/Traffic and Landing Page steps from overwriting each other's settings

= 1.2.2 =
* New: Settings page shows connected GA4 property name, Property ID, and all web data streams (including Measurement ID) when GA4 is connected

= 1.2.1 =
* Fix: connection arrow hover and × delete button now work correctly — SVG was behind the canvas div; fixed with z-index, explicit pointer-events, and mouseover/mouseout bubbling

= 1.2.0 =
* New: drag-to-connect — press and hold the ⊕ button and drag to a target node to create a connection (dashed preview shown while dragging)
* New: connection arrows show a red × button at their midpoint on hover; click to delete the connection
* Fix: inspector data is auto-saved when switching between nodes or clicking away, so step settings are never lost without clicking "Update Step"

= 1.1.6 =
* Fix: GA4 OAuth flow improvements and token handling

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.6 =
Fixes adding nodes to a saved funnel overwriting existing nodes. Upgrade recommended.

= 1.2.5 =
Click any connection arrow to select it and delete it from the inspector panel.

= 1.2.4 =
Fixes the connection × delete button not appearing on hover. Upgrade recommended.

= 1.2.3 =
Fixes Ad/Traffic and Landing Page steps overwriting each other when multiple are added to the canvas. Upgrade recommended.
