=== FizWatch ===
Contributors: fizteqsolutions
Tags: monitoring, updates, notifications, fizwatch
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT

Monitor WordPress plugin lifecycle events and available updates with FizWatch.

== Description ==

FizWatch connects your WordPress site to your self-hosted FizWatch instance for monitoring:

* **Plugin lifecycle events** - Get notified when plugins are installed, activated, deactivated, or uninstalled.
* **Available updates** - Daily checks for pending updates to plugins, WordPress core, themes, and translations.
* **Auto-resolve** - Issues are automatically resolved when updates are applied.

= Requirements =

* A running FizWatch instance
* A project configured in FizWatch with an API key

== Installation ==

1. Upload the `fizwatch` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Settings > FizWatch
4. Enter your FizWatch URL and API key
5. Click "Test Connection" to verify

== Changelog ==

= 1.0.0 =
* Initial release
* Plugin lifecycle event tracking
* Daily update monitoring
* Auto-resolve for completed updates
