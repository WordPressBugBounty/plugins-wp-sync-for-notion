=== WP Sync for Notion - Notion to WordPress ===
Author: WP connect
Author URI: https://wpconnect.co/
Contributors: wpconnectco, staurand
Tags: wpconnect, notion, api, automation, synchronization
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Notion and send data to WordPress with the WP Sync for Notion plugin!

== Description ==

With our Notion to WordPress integration, WP Sync for Notion, you can publish content from Notion directly to your WordPress website and keep your pages in sync without Zapier or Make.

The free version allows you to synchronize Notion pages to WordPress with their content and structure preserved.

For advanced use cases, Notion WP Sync Pro+ unlocks powerful features such as database synchronization, field mapping, Custom Post Types, Advanced Custom Fields (ACF) support, and SEO integrations — ideal for professional and content-driven websites.

Learn more and compare features with the
[Notion WP Sync Pro+ version](https://wpconnect.co/notion-wordpress-integration/#compare-plans).

== Features ==

= Connect Notion pages =
* Sync Notion pages to WordPress
* Keep page content and supported blocks in sync
* Manual or automatic synchronization

= Connect Notion databases (Pro+ only) =
* Sync structured Notion databases to WordPress
* Map Notion database properties to WordPress fields
* Create content from databases instead of static pages
* Set up and publish unlimited connections

Database synchronization and property mapping are available in the
[Pro+ version](https://wpconnect.co/notion-wordpress-integration/#compare-plans).

= Display Notion content in WordPress =
* Publish content as Posts or Pages
* Display content in Custom Post Types (Pro+)
* Assign Post Status and Author (Pro+)
* Advanced Custom Fields (ACF) support (Pro+)

These features make Pro+ ideal for headless CMS and editorial workflows.
[See Pro+ features](https://wpconnect.co/notion-wordpress-integration/#compare-plans).

= Keep your Notion design or customize it =
* Supports most Notion blocks (text, lists, tables, images, columns…)
* Display content via Gutenberg block
* Use shortcodes with Elementor, Divi or any page builder (Pro+)
* Dedicated “Notion Content” Custom Post Type (Pro+ only)

= Advanced synchronization & automation =
* Manual or automatic synchronization
* Webhook-triggered synchronization (Pro+)
* Control sync behavior (add / update / delete)
* Designed for large-scale content imports (Pro+)

Advanced automation and scalability are available in the
[Pro+ version](https://wpconnect.co/notion-wordpress-integration/#compare-plans).

[youtube https://www.youtube.com/watch?v=2EBm_q_isC0&list=PLVcMc55QQRBPnlOXfT3kN_7kRF5hwgtwt]

== Installation ==

1. From your WordPress Dashboard, go to "Plugins > Add New".
2. Look for our plugin into the search bar: WP Sync for Notion.
3. Click on the 'Install Now' button of the plugin, and wait a few seconds.
4. Click on the "Activate" button (also available in "Plugins > Installed Plugins").
5. That's it, WP Sync for Notion is ready to use, find it in the sidebar.

== How to unleash your plugin's full potential? ==

WP Sync for Notion works great for syncing pages, but the Pro+ version unlocks its full power for professional use cases.

With Pro+, you can:
* Sync Notion databases instead of only pages
* Map database properties to WordPress fields
* Use Custom Post Types and Advanced Custom Fields
* Improve SEO with Yoast and upcoming SEO integrations
* Handle large imports and complex content structures

Compare Free and Pro+ features on the
[official comparison page](https://wpconnect.co/notion-wordpress-integration/#compare-plans).

== Frequently Asked Questions ==

= What is Notion? =
Notion is an all-in-one digital workplace combining note-taking, task management, project management and document collaboration.

= Why do I need a Notion account? =
WP Sync for Notion uses Notion’s API to send data. Creating a Notion account is free. You can generate an Internal Integration Token from your Notion integrations page.

= Can I use the plugin with a free Notion plan? =
Yes. Notion offers a free plan that allows unlimited pages and blocks and provides API access.

= How are my pages synchronized? =
Once you publish a connection, synchronization runs automatically based on your settings. You can also manually trigger a sync at any time using the "Sync Now" button.

= What's the difference between WP Sync for Notion (Free) and Notion WP Sync Pro+? =
WP Sync for Notion (Free) allows you to synchronize Notion pages to WordPress.

Notion WP Sync Pro+ adds advanced capabilities such as:
* Database synchronization
* Property mapping to WordPress fields
* Custom Post Types
* Advanced Custom Fields (ACF)
* SEO integrations
* Improved performance and scalability

See the full comparison on the
[Notion WP Sync Pro+ page](https://wpconnect.co/notion-wordpress-integration/#compare-plans).

= I can't see my pages =
Make sure your integration is shared with your Notion pages. You can follow the official Notion instructions to grant access to your integration.

= How can I get support? =
If you need assistance, open a ticket on the WordPress support forum.

== External services ==

This plugin connects to the **Notion API** to synchronize content into WordPress.

**Service endpoint:** `https://api.notion.com`

**What is sent and when:**

* When a connection is created, edited, or tested: the Notion integration token (API key) and the requested page or database ID are sent to the Notion API to retrieve metadata.
* On each manual or scheduled sync: the Notion integration token, the requested page/database IDs, and any configured query parameters (filters, pagination cursors) are sent to the Notion API. The API returns the page or database content (titles, properties, blocks) which is then stored in WordPress.

**Notion Terms of Service:** https://www.notion.so/notion/Terms-and-Privacy-28ffdd083dc3473e9c2da6ec011b58ac
**Notion Privacy Policy:** https://www.notion.so/notion/Privacy-Policy-3468d120cf614d4c9014c09f6adc9091

== Screenshots ==

1. Edit connection
2. Field Mapping
3. Configure synchronization
4. Notion content block & Shortcode

== Changelog ==

= 1.7.2 =
*Release Date: 14th August 2026*

* Compatibility with WordPress 7.0
* Improvement: Document the Notion API as an external service in the readme
* Improvement: Remove the WP Connect logo from the admin header and enlarge the plugin title
* Fix: Prevent content from being deleted when a sync is interrupted or a new sync starts before the previous one finishes

= 1.7.1 =
*Release Date: 20th Jan. 2026*

* Security: Fix Broken Access Control ; add missing user capability check on ajax request

[Full changelog](https://wpconnect.co/changelog/changelog-wp-sync-for-notion-free-version/)

== Troubleshooting ==

If you don't see your pages, make sure they are shared with your Notion integration.
If needed, logs are available via FTP in the following folder:
/wp-content/uploads/notionwpsync-logs

== Support ==

Open a ticket via the [WordPress support forum](https://wordpress.org/support/plugin/wp-sync-for-notion/)
