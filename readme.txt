=== YukDigitalz Login ===
Contributors: Shihela
Tags: woocommerce, google login, social login, express checkout, digital products
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure Google login & registration for WooCommerce customers. Enterprise-grade security, optimized for instant digital product checkouts.

== Description ==

YukDigitalz Login is an enterprise-grade social login extension designed specifically to streamline the customer checkout flow. It is highly optimized for stores selling digital products, where checkout speed and conversion rates are critical.

By allowing buyers to authenticate instantly with their Google accounts, the plugin automatically populates billing email, first name, and last name at the checkout page, eliminating manual input.

This plugin connects to Google OAuth Services to authenticate users. By using this plugin, you agree to Google's terms and privacy policies.

Privacy Policy: https://google.com
Terms of Service: https://google.com

=== Key Enterprise Security Features ===

* **Anti-CSRF Session Footprint**: Token state validation combined with browser user-agent integrity checks.
* **Database Leak Protection**: The Google Client Secret is encrypted in the database using modern AES-256-CTR encryption utilizing secret salts defined in `wp-config.php`.
* **Anti-Hijacking Account Verification**: Require confirmation via verification email before linking a Google login with an existing manual account.
* **Corporate Domain Restriction**: Easily restrict registration and login to specific domains (e.g., only allow logins from `@yourcompany.com`).
* **Audit Trail Logging**: Logs all authentication events, verification attempts, and CSRF alerts directly to WooCommerce status logs.
* **Google Branding Compliant**: Button visual designs comply with official Google Sign-In design guidelines.

== Installation ==

1. Upload the `yukdigitalz-login` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **WooCommerce > Google Login Settings** in your WordPress dashboard.
4. Input your Google Client ID and Google Client Secret (refer to the built-in guide on the settings page to obtain keys).
5. Tick the "Enable Google Login & Registration" option and Save Changes.

== Frequently Asked Questions ==

= Where can I get Google API credentials? =
Please refer to the Google API Console Guide card inside the plugin settings page. You will need to create a project in Google Cloud Console, obtain Client Credentials, and register the displayed Redirect URI callback.

= Can I use this for corporate employees only? =
Yes, you can input allowed corporate domains (e.g., `company.com`) in the "Domain Restriction" settings field in the admin panel to restrict login access.

== Changelog ==

= 1.0.0 =
* Initial release. Fully OOP modular architecture.
* Enterprise Database Cryptographic Encryption.
* Anti-hijacking secure linking verification email flow.
* Express checkout promotional banner on checkout page.
