=== AZ NotifyRules for WooCommerce ===
Contributors: sexoblogue
Tags: woocommerce, pushover, order notifications, email alerts, product categories
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route WooCommerce new-order alerts by product or category to Pushover and additional email recipients.

== Description ==

AZ NotifyRules adds a rule-based notification layer to WooCommerce. It helps store teams receive the right new-order alert without replacing WooCommerce's standard order email.

= Features =

* Match every order, selected product categories, or individual products and variations.
* Send Pushover notifications to one or more independently configured users, groups, or devices.
* Add recipients to the To, CC, or BCC headers of the WooCommerce New order email.
* Combine multiple notification connections in one rule.
* Configure the Pushover title, priority, sound, device, and optional order details.
* Encrypt Pushover application tokens and user or group keys at rest using WordPress salts and Sodium or OpenSSL.
* Prevent duplicate Pushover notifications for the same order and connection.
* Record accepted or failed Pushover requests in WooCommerce order notes and logs.
* Retry unsent non-email alerts from the WooCommerce order actions menu.
* Work with WooCommerce High-Performance Order Storage (HPOS).
* Extend connection providers and matching behavior through documented WordPress hooks.

WooCommerce is required. A Pushover account and Pushover application token are required only when using the Pushover connection type.

This plugin is not affiliated with or endorsed by WooCommerce, Automattic, or Pushover.

== External services ==

= Pushover =

The plugin connects to the Pushover service only after an administrator configures a Pushover connection.

It contacts Pushover in these circumstances:

* When an administrator edits a configured Pushover connection, the application token and user or group key are sent to the Pushover validation endpoint to retrieve active device names.
* When an administrator explicitly clicks Test, a test message based on the latest WooCommerce order is sent to Pushover.
* When an enabled rule matches a new order, a notification is sent to Pushover.

Data sent to Pushover can include the application token, user or group key, selected device, message title, order number, order creation timestamp, an administrative order URL, and the message body. Depending on connection settings, the message body may also include product names and quantities, the formatted order amount, and the customer's billing name. Product details, amount, and customer name are disabled by default for newly created connections.

Pushover service: https://pushover.net/

Pushover API documentation: https://pushover.net/api

Pushover terms of use: https://pushover.net/terms

Pushover privacy policy: https://pushover.net/privacy

The plugin does not send telemetry or usage analytics.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate AZ NotifyRules.
3. Go to WooCommerce > Order alerts > Connections.
4. Add an email or Pushover connection. For Pushover, create an application at Pushover and enter its application token and a user or group key.
5. Open the Rules tab and associate one or more connections with all orders, product categories, or individual products.

== Frequently Asked Questions ==

= Does this replace the WooCommerce New order email? =

No. Email connections add recipients to the existing WooCommerce New order email. Pushover connections send a separate push notification.

= When are alerts evaluated? =

Alerts are evaluated when WooCommerce triggers its New order notification during the standard transitions from pending, failed, or cancelled to processing, completed, or on-hold.

= Can one order trigger several connections? =

Yes. Every matching enabled rule contributes its enabled connections. Duplicate connection identifiers are removed before dispatch.

= Are Pushover credentials stored securely? =

The plugin encrypts credentials before storing them in the WordPress options table. It uses Sodium when available and otherwise AES-256-GCM through OpenSSL. Encryption is derived from WordPress authentication salts unless the site defines a dedicated `AZ_WOO_ALERTS_ENCRYPTION_KEY` constant.

= What customer data is sent to Pushover? =

The order number, timestamp, administrative order URL, and message title are part of the request. Product names and quantities, order amount, and billing name are optional and disabled by default on new connections. See the External services section for full details.

= Does the plugin support variable products? =

Yes. Product rules can match product or variation identifiers. Category rules use the parent product categories for variations.

= Does the plugin support HPOS? =

Yes. The plugin declares compatibility with WooCommerce High-Performance Order Storage and uses WooCommerce order APIs.

= What is removed when the plugin is deleted? =

Configured connections, encrypted credentials, and rules are removed. Existing WooCommerce order notes, WooCommerce log entries, and per-order delivery metadata remain as part of the order history.

== Privacy ==

The plugin stores notification connections and rules in the WordPress options table. Pushover credentials are encrypted at rest. Successful Pushover delivery identifiers and timestamps are stored as WooCommerce order metadata, and delivery results can be stored in order notes and WooCommerce logs.

The plugin provides suggested text for the site's WordPress privacy policy. Administrators should review that text and describe their own notification configuration and legal basis for transmitting order data to Pushover or additional email recipients.

== Developer hooks ==

The following hooks are available for custom integrations:

* `az_woo_alerts_channel_providers`
* `az_woo_alerts_matching_channel_ids`
* `az_woo_alerts_rule_matches`
* `az_woo_alerts_order_matched`
* `az_woo_alerts_before_dispatch`
* `az_woo_alerts_after_dispatch`
* `az_woo_alerts_pushover_payload`
* `az_woo_alerts_channel_admin_fields_{provider_type}`
* `az_woo_alerts_channel_admin_summary_{provider_type}`

Custom providers must implement the `AZ_Woo_Alerts_Channel_Provider` interface.

== Changelog ==

= 1.0.1 =

* Moved the public download page and update service to code.zeler.fr.
* Confirmed compatibility with WooCommerce 11.1.

= 1.0.0 =

* Initial public release.
* Added rule-based Pushover and WooCommerce email routing.
* Added encrypted credentials, AJAX administration, HPOS compatibility, delivery history, and extension hooks.

== Updates ==

This plugin is distributed independently from WordPress.org. WordPress checks https://code.zeler.fr/aznrwc/update.json for new versions. Update packages are downloaded over HTTPS and verified against the published SHA-256 checksum before installation.
