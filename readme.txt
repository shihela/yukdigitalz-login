=== YukDigitalz Login ===
Contributors: Shihela
Tags: woocommerce, google login, social login, google one tap, block checkout
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, zero-bloat Sign in with Google & Google One Tap for WooCommerce. Fully compatible with Block Checkout, Classic Checkout, and HPOS.

== Description ==

**Reduce cart abandonment and speed up WooCommerce checkout with 1-click Google Sign-In and Google One Tap.**

YukDigitalz Login is a lightweight, security-first Google authentication plugin built specifically for modern WooCommerce stores. Unlike bloated all-in-one social login plugins that load dozens of heavy scripts and unused social networks, YukDigitalz Login focuses exclusively on Google—with **zero third-party PHP SDKs and zero jQuery dependencies**.

When a customer signs in with Google, their account is created (or safely linked) and their billing email, first name, and last name are automatically filled in so they can complete checkout in seconds.

=== Why Store Owners & Developers Choose YukDigitalz Login ===

* **Built for Modern WooCommerce (Block Checkout & HPOS):** Works natively with WooCommerce Cart/Checkout Blocks, Classic Checkout, and High-Performance Order Storage without layout glitches or compatibility warnings.
* **Google One Tap & Silent Auto-Login:** Display the high-converting Google One Tap prompt on My Account and Checkout pages to log returning customers in effortlessly.
* **Zero-Bloat Performance:** Pure, modular PHP and vanilla JS. Protects your Core Web Vitals and checkout page speed.
* **B2B & Corporate Domain Controls:** Restrict registration to specific company domains (e.g., `company.com`) and automatically assign custom roles (like *Wholesale Customer*) based on the user's email domain.
* **Customizable Express Checkout Banner:** Highlight Google Sign-In above checkout forms with custom text, official Google button themes (Light, Dark, Neutral), or limit it strictly to virtual/digital carts.
* **Customer Self-Service & Avatar Sync:** Automatically syncs the customer's Google profile picture and lets them view or disconnect their linked Google account from **My Account > Account Details**.
* **Enterprise-Grade Security:** Protected by OAuth 2.0 + PKCE (`S256`), cookie-bound state (`HttpOnly; SameSite=Lax`), AES-256-GCM encrypted Client Secret storage, SHA-256 email verification before linking existing accounts, and automatic Admin login blocking (`manage_options`).

=== Shortcode ===

Place a Google Sign-In button anywhere on your site (such as custom landing pages, Elementor/Gutenberg popups, or custom checkout templates):

`[ydgl_google_login]`

Optional attributes:

`[ydgl_google_login text="Continue with Google" class="my-custom-class"]`

The shortcode automatically hides itself when a visitor is already logged in.

=== External Services ===

This plugin connects to Google OAuth 2.0 and Google Identity Services (`https://accounts.google.com`, `https://oauth2.googleapis.com`, and `https://www.googleapis.com/oauth2/v3/userinfo`) to authenticate users when they click "Sign in with Google" or use Google One Tap.

* **Data sent:** OAuth authorization code, PKCE code verifier, site Redirect URI, Client ID/Secret (server-to-server), or Google One Tap ID token.
* **Data received:** Google user identifier (`sub`), verified email address, first name, last name, display name, and profile photo URL (when Avatar Sync is enabled).
* **Google Privacy Policy:** https://policies.google.com/privacy
* **Google Terms of Service:** https://policies.google.com/terms

== Installation ==

1. Ensure WooCommerce is installed and active.
2. Upload the `yukdigitalz-login` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress Plugins screen.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Go to **WooCommerce > Google Login** in your WordPress dashboard.
5. Follow the built-in Google Cloud Console guide to enter your Google Client ID and Client Secret.
6. Enable **Enable Google Login & Registration** and click **Save Changes**.

== Frequently Asked Questions ==

= Where can I get Google API credentials? =
Refer to the Google API Console Guide card inside **WooCommerce > Google Login**. Create an OAuth 2.0 Web Application credential in Google Cloud Console and add the displayed Authorized Redirect URI (and Authorized JavaScript origin if using Google One Tap).

= Does this work with WooCommerce Block Checkout and HPOS? =
Yes. YukDigitalz Login declares full compatibility with HPOS (`custom_order_tables`) and Cart/Checkout Blocks (`cart_checkout_blocks`), and renders the Express Checkout banner in both Classic and Block Checkout.

= Can I restrict login to corporate employees or assign B2B roles automatically? =
Yes. Use **Domain Restriction** to allow only specific email domains (e.g., `company.com`), and use **Dynamic Domain Role Mapping** to automatically assign non-privileged roles (such as Wholesale Customer) to new users registering from partner domains.

= Can customers disconnect their Google account? =
Yes. Logged-in customers with a linked Google account will see a "Google Account Connected" card under **My Account > Account Details** with a one-click Disconnect button.

= Is it safe for administrator accounts? =
Yes. By default, Google Sign-In is blocked for administrator accounts (`manage_options`) so store admins always authenticate via WordPress password and 2FA.

== Changelog ==

= 1.2.0 =
* Added optional **Google One Tap Sign-In** & **Silent Auto-Login** for WooCommerce Account & Checkout pages with server-side ID token verification (`aud`, `iss`, `exp`, `email_verified`).
* Added **Google Avatar Sync** (validated HTTPS `googleusercontent.com` profile photo synced automatically on login & registration).
* Added **Dynamic Domain Role Mapping** for B2B/partner domains with built-in privilege escalation protection (blocks administrative roles).
* Added official **Google Button Theme Presets** (Light, Dark, Neutral).
* Added `uninstall.php` for complete cleanup of plugin options, user meta, and transients on uninstall.
* Updated Google External Services disclosure with official Privacy Policy & Terms of Service URLs.

= 1.1.0 =
* Added **WooCommerce Block Checkout** support and declared `cart_checkout_blocks` compatibility.
* Added **Checkout Express Banner** controls (toggle on/off, digital/virtual carts only, custom title & description).
* Added **Connected Google Account** card with Disconnect button in **My Account > Account Details**.
* Preserved original redirect URL (such as Checkout) across the email account-linking verification flow.

= 1.0.1 =
* Security: Bound OAuth `state` to an `HttpOnly; SameSite=Lax` browser cookie to prevent Login CSRF and added PKCE (`S256`).
* Security: Upgraded Client Secret encryption to AES-256-GCM (with HMAC-CBC fallback and seamless v1 migration) and stopped outputting the secret in admin HTML.
* Security: Switched security tokens to `random_bytes()`, hashed email-linking tokens with SHA-256, masked emails in audit logs, and blocked Administrator Google Sign-In by default.
* Fix: Added WooCommerce dependency check (`Requires Plugins: woocommerce`) and declared HPOS compatibility.
* Fix: Removed duplicate `woocommerce_created_customer` action call (preventing duplicate welcome emails).
* Fix: Fixed username generation bug caused by `wp_unique_id()` and populated `billing_email` meta on registration.
* Fix: Updated Audit Log reader for WooCommerce 8.6+ FileV2 with bounded tail reading, moved admin CSS/JS to enqueued files, and added `is_user_logged_in()` check to `[ydgl_google_login]`.

= 1.0.0 =
* Initial release. Modular OOP architecture, Google OAuth 2.0 login/registration, domain restriction, and account linking verification.

== Upgrade Notice ==

= 1.2.0 =
Major security and WooCommerce modernization update: adds Block Checkout & HPOS compatibility, PKCE + AES-256-GCM security hardening, Google One Tap, Avatar Sync, and Dynamic Domain Role Mapping.
