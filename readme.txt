=== FunnelSpark — Visual Funnel Builder & GA4 Tracker ===
Contributors: paulneumyer
Tags: sales funnel, funnel builder, GA4, conversion tracking, marketing funnel, funnel visualization, analytics
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build, visualize, and track your marketing funnels with live GA4 conversion data — right inside WordPress.

== Description ==

**FunnelSpark** is the only WordPress plugin that lets you visually map your sales funnel AND overlay real GA4 conversion data on every step — so you can see exactly where leads are dropping off.

No more guessing. No more switching between tools. Your funnel. Your data. One screen.

= What FunnelSpark Does =

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

FunnelSpark is built and maintained by [MarTech Spark](https://martechspark.com) — a fractional marketing consultancy helping small businesses build smarter marketing systems.

**Struggling with a leaky funnel?** Get a [Seven-Point Website Lead Audit](https://martechspark.com/lp/homepage-audit/) for just $27 — includes a personal video walkthrough of your site with specific fixes.

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

= Does FunnelSpark slow down my site? =
No. FunnelSpark is admin-only. It adds zero code to your public-facing site.

= Is my GA4 data stored on your servers? =
Never. All GA4 data is fetched directly from Google's API to your WordPress site. MarTech Spark does not store or access your analytics data.

= How many funnels can I create? =
Unlimited.

= Does it work with WooCommerce? =
Yes. Map your WooCommerce pages (shop, product, cart, checkout, thank you) as funnel steps and track conversion rates with GA4.

== Screenshots ==

1. FunnelSpark canvas — drag-and-drop funnel builder with live GA4 data badges
2. Dashboard — manage all your funnels in one place
3. Settings — connect your GA4 property in minutes
4. Live data overlay — sessions and conversion rates on every funnel step

== Changelog ==

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

= 1.2.4 =
Fixes the connection × delete button not appearing on hover. Upgrade recommended.

= 1.2.3 =
Fixes Ad/Traffic and Landing Page steps overwriting each other when multiple are added to the canvas. Upgrade recommended.
