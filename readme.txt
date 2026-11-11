=== Codegea ERP ===
Contributors: codegea
Tags: woocommerce, erp, inventory, orders, invoicing
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your WooCommerce store with the Codegea ERP: orders arrive as sales (draft, confirmed or invoiced) and stock stays in sync with an ERP warehouse.

== Description ==

Codegea ERP links a WooCommerce store with the Codegea ERP (https://codegea.com). It is useful only together with a Codegea ERP account.

* **Orders**: every paid order is sent to the ERP as a sale. Choose whether it arrives as a draft, a confirmed sale (stock is deducted) or a confirmed and invoiced sale. Taxes, coupons and shipping are sent as charged, so the ERP sale matches what the customer paid.
* **Stock**: the store shows the stock of one ERP warehouse. Optionally, stock changes made by hand in WooCommerce are passed to the ERP.
* **Reliable**: sends run in the background with Action Scheduler (bundled with WooCommerce) and are retried if the ERP does not answer. Every order can be re-sent from its screen (Order actions → Send to ERP).
* **Newsletter**: an optional "subscribe to our newsletter" checkbox at checkout (classic and block checkout) and the `[codegea_erp_newsletter]` shortcode with a signup form. The ERP sends a confirmation email (double opt-in) before anyone is subscribed.
* **Log**: the last 50 sends, to diagnose problems without server access.

Products, prices, photos, brands, categories and stock are sent from the ERP to the store through the WooCommerce REST API; this plugin covers the other direction.

The settings screens are in Spanish.

== External services ==

This plugin connects to the **Codegea ERP** instance configured by the store owner (WooCommerce → Codegea ERP → Conexión). Nothing is sent until an ERP address and secret are saved.

* **What is sent and when**
  * When an order reaches one of the selected statuses: order number, status, payment method, currency, billing name, email, phone and tax ID, line items (SKU, quantity, prices, discount, tax rate), coupons used, shipping and total, and the customer note.
  * When stock is changed by hand in WooCommerce (only if enabled): product SKU and new stock quantity.
  * When a customer ticks the newsletter checkbox at checkout or submits the newsletter form (only if used): email, name, the consent text they accepted and their IP address.
  * When testing the connection: the store URL and the plugin version.
* **Where**: `{ERP address}/api/plugins/wordpress/webhook/{ping|orders|stock|newsletter}`, signed with HMAC-SHA256 using the shared secret.
* **Service provider**: Codegea — terms of service: https://codegea.com/en/terms-of-service — privacy policy: https://codegea.com/en/privacy-policy

== Installation ==

1. Install and activate the plugin (WooCommerce must be active).
2. In the Codegea ERP, open Configuración → Plugins → WordPress / WooCommerce → Pedidos and copy the ERP address and the secret.
3. In WordPress, go to WooCommerce → Codegea ERP → Conexión, paste them and click «Conectar con el ERP».
4. In the Pedidos and Stock tabs, choose how orders arrive and which ERP warehouse feeds the store.

== Frequently Asked Questions ==

= Does it work without the Codegea ERP? =

No. It only sends data to a Codegea ERP account.

= Can an order be duplicated in the ERP? =

No. The ERP identifies each order by its WooCommerce ID; re-sending it is safe. If an order was sent unpaid (e.g. bank transfer on hold) and is paid later, the ERP confirms that same sale.

= Are order stock reductions sent twice? =

No. Stock reductions caused by orders are not sent: the ERP deducts them when it receives the order. Changes made by the ERP itself are not sent back either.

== Changelog ==

= 1.1.0 =
* Newsletter: optional checkbox at checkout (classic and block checkout) and the `[codegea_erp_newsletter]` signup form shortcode, both with double opt-in through the ERP.
* Orders now include the coupons used and their discount, so the ERP can count coupon uses (Loyalty plugin). Coupons created in the ERP are added to WooCommerce through its REST API.

= 1.0.0 =
* First release: orders (draft, confirm, invoice), stock in both directions, connection test, warehouse selection and send log.
