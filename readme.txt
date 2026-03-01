=== FizWatch ===
Contributors: fizteqsolutions
Tags: monitoring, updates, notifications, fizwatch, error-tracking
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: MIT

Monitor WordPress plugin lifecycle events, available updates, and PHP errors with FizWatch.

== Description ==

FizWatch connects your WordPress site to your self-hosted FizWatch instance for monitoring:

* **Plugin lifecycle events** - Get notified when plugins are installed, activated, deactivated, or uninstalled.
* **Available updates** - Daily checks for pending updates to plugins, WordPress core, themes, and translations.
* **Auto-resolve** - Issues are automatically resolved when updates are applied.
* **PHP error reporting** - Capture uncaught exceptions and fatal PHP errors with full stacktrace and request context.

= Requirements =

* A running FizWatch instance
* A project configured in FizWatch with an API key

== Installation ==

1. Upload the `fizwatch` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Settings > FizWatch
4. Enter your FizWatch URL and API key
5. Click "Test Connection" to verify
6. Optionally enable "PHP Error Reporting" to capture uncaught exceptions and fatal errors

== Changelog ==

= 1.2.0 =
* Added actor context (username and IP address) to plugin lifecycle events for attribution
* Includes previously uncommitted v1.1.0 error reporter feature

= 1.1.0 =
* Added PHP error reporting — captures uncaught exceptions and fatal errors
* Sends errors to FizWatch with stacktrace, request context, and environment info
* Sensitive fields (passwords, tokens, cookies) are automatically filtered
* Enable via Settings > FizWatch > "Enable PHP Error Reporting"

= 1.0.0 =
* Initial release
* Plugin lifecycle event tracking
* Daily update monitoring
* Auto-resolve for completed updates
