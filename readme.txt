=== Nimbus ERP for WooCommerce ===
Contributors: codegea
Tags: woocommerce, erp, inventory, orders, invoicing
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Your WooCommerce store and Nimbus ERP, always in sync: orders become sales, stock stays aligned and buyers join your newsletter.

== Description ==

**Nimbus ERP for WooCommerce** connects your online store with [Nimbus ERP](https://codegea.com), the business management system by Codegea. Every paid order arrives in the ERP as a sale — ready to confirm or already invoiced — and the store always shows the real stock of your warehouse. No more typing orders twice, no more selling what you do not have.

Products, prices, photos, brands, categories and stock travel from the ERP to the store through the WooCommerce REST API. This plugin covers the other direction: what happens in the store goes back to the ERP.

= Orders that arrive as sales =

* Every paid order is sent to the ERP as a sale. You choose how it arrives: **as a draft** to review, **as a confirmed sale** (stock is deducted) or **confirmed and invoiced**.
* Taxes (VAT), coupons and shipping are sent exactly as charged, so the ERP sale adds up to what the customer paid.
* Choose which order statuses send the order (Processing, Completed, On hold). An order placed by bank transfer arrives as a draft and is confirmed when it is paid — never duplicated.
* Re-send any order from its screen: *Order actions → Send to ERP*.

= Stock you can trust =

* The store shows the stock of the ERP warehouse you pick (main store, branch…).
* Sales, purchases and adjustments in the ERP reach the store within a minute.
* Optionally, stock changes made by hand in WooCommerce are passed back to the ERP. Changes made by the ERP are never sent back, so there are no loops.

= Newsletter signups =

* Optional "subscribe to our newsletter" checkbox at checkout — works with the classic and the block checkout.
* `[nimbus_erp_newsletter]` shortcode with a signup form for any page, post or widget.
* Double opt-in: the ERP sends a confirmation email before anyone is subscribed, and stores the consent text the customer accepted.

= Built to be reliable =

* Sends run in the background with Action Scheduler (bundled with WooCommerce) and are retried automatically if the ERP does not answer.
* Every request is signed with HMAC-SHA256 using a secret shared with your ERP.
* A send log with the last 50 operations helps you diagnose problems without server access.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).

= Requirements =

* WooCommerce 8.0 or newer.
* A Nimbus ERP account with the WordPress / WooCommerce plugin enabled. The plugin does nothing on its own.

The settings screens are in Spanish.

== External services ==

This plugin connects to the **Nimbus ERP** instance configured by the store owner (WooCommerce → Nimbus ERP → Conexión). Nothing is sent until an ERP address and secret are saved.

* **What is sent and when**
  * When an order reaches one of the selected statuses: order number, status, payment method, currency, billing name, email, phone and tax ID, line items (SKU, quantity, prices, discount, tax rate), coupons used, shipping and total, and the customer note.
  * When stock is changed by hand in WooCommerce (only if enabled): product SKU and new stock quantity.
  * When a customer ticks the newsletter checkbox at checkout or submits the newsletter form (only if used): email, name, the consent text they accepted and their IP address.
  * When testing the connection: the store URL and the plugin version.
* **Where**: `{ERP address}/api/plugins/wordpress/webhook/{ping|orders|stock|newsletter}`, signed with HMAC-SHA256 using the shared secret.
* **Service provider**: Codegea — terms of service: https://codegea.com/en/terms-of-service — privacy policy: https://codegea.com/en/privacy-policy

== Installation ==

1. Install and activate the plugin (WooCommerce must be active).
2. In Nimbus ERP, open *Configuración → Plugins → WordPress / WooCommerce → Pedidos* and copy the ERP address and the secret.
3. In WordPress, go to *WooCommerce → Nimbus ERP → Conexión*, paste them and click «Conectar con el ERP».
4. In the *Pedidos* and *Stock* tabs, choose how orders arrive and which ERP warehouse feeds the store.

That's it: the next paid order shows up in the ERP.

== Frequently Asked Questions ==

= Does it work without Nimbus ERP? =

No. It only sends data to a Nimbus ERP account. Contact [Codegea](https://codegea.com) to get one.

= Can an order be duplicated in the ERP? =

No. The ERP identifies each order by its WooCommerce ID, so re-sending it is safe. If an order was sent unpaid (e.g. bank transfer on hold) and is paid later, the ERP confirms that same sale.

= Are stock reductions sent twice? =

No. Stock reductions caused by orders are not sent: the ERP deducts them when it receives the order. Changes made by the ERP itself are not sent back either.

= What happens if the ERP is offline? =

Orders and stock changes wait in the queue and are retried automatically. You can see every attempt in the *Registro* tab.

= Can the ERP issue the electronic invoice? =

Yes. With the «Confirmar y facturar» mode the ERP confirms the sale and issues the invoice, as long as the customer entered a tax ID (cédula/RUC) and invoicing is set up in the ERP.

= I used the plugin when it was called «Codegea ERP». Do I lose my settings? =

No. Version 1.2.0 copies the connection, warehouse and send log of the old plugin, keeps orders already sent from being sent again, and the old `[codegea_erp_newsletter]` shortcode still works. You can delete the old plugin afterwards.

== Screenshots ==

1. Connection: paste the ERP address and secret once and see which company and warehouse the store works with.
2. Orders: choose whether a paid order arrives as a draft, a confirmed sale or a confirmed and invoiced sale, and which statuses send it.
3. Stock: pick the ERP warehouse that feeds the store and decide whether manual changes go back to the ERP.
4. Newsletter: offer a signup checkbox at checkout and a shortcode form, with double opt-in through the ERP.
5. Log: the last sends to the ERP with their result, to diagnose problems at a glance.

== Changelog ==

= 1.2.1 =
* Settings screens with the Nimbus look: logo, orange accent on tabs, buttons and options.

= 1.2.0 =
* Renamed from «Codegea ERP» to «Nimbus ERP for WooCommerce». Settings, send log and sent orders of an existing Codegea ERP install are kept, and the old `[codegea_erp_newsletter]` shortcode still works.

= 1.1.0 =
* Newsletter: optional checkbox at checkout (classic and block checkout) and the `[codegea_erp_newsletter]` signup form shortcode, both with double opt-in through the ERP.
* Orders now include the coupons used and their discount, so the ERP can count coupon uses (Loyalty plugin). Coupons created in the ERP are added to WooCommerce through its REST API.

= 1.0.0 =
* First release: orders (draft, confirm, invoice), stock in both directions, connection test, warehouse selection and send log.

== Upgrade Notice ==

= 1.2.0 =
The plugin is now called Nimbus ERP for WooCommerce. If you had «Codegea ERP» installed, activate this one: your settings are copied over and you can delete the old plugin.
