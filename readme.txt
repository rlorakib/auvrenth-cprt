=== Munafa – COD Profit & Return Tracker for WooCommerce ===
Contributors: auvrenthstudio
Tags: woocommerce, cash on delivery, profit, returns, courier
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See the real net profit of every cash on delivery order: product cost, courier charge, fees and return loss.

== Description ==

Revenue is not profit. A cash on delivery (COD) order that is returned costs you the courier charge both ways, and often the packaging too. Munafa ("profit" in Bangla) shows what each order really earned, and what each return really cost.

It is built for COD sellers in Bangladesh and the rest of South Asia, but nothing is tied to one country. Courier zones, payment method fees, return reasons and currency are all your own settings.

= What you get =

* **Net profit on every order.** Revenue minus product cost, courier charge, packaging, COD charge and gateway fee. Shown in a small box on the order page.
* **Return loss.** Mark an order as "Returned" (single order or bulk action) and Munafa counts the courier charge you pay for the trip out and back, plus the fee. Revenue and product cost drop to zero, and the stock goes back.
* **Dashboard.** Revenue, net profit, margin, return rate and return loss with comparison to the previous period. A profit trend chart, your most profitable and loss-making products, and returns by area and by reason.
* **Product cost.** Uses the WooCommerce cost of goods field when your store has it, otherwise a "Cost price" field on simple and variable products. Orders with a missing cost are flagged so you do not trust an overstated profit.
* **Your own courier zones.** Name a zone, pick the states or countries it covers, and set the delivery charge and the return charge percentage. Everything else falls into "Everywhere else". You can also override the courier charge on a single order.
* **Return reasons you control.** Customer refused, wrong address, phone off, or your own list. Renaming a reason also renames it on old orders.
* **Old orders included.** "Recalculate" works through your existing orders in the background, in small batches.
* **Simple checkout module (optional, off by default).** Make last name, company, address line 2, city and postcode required, optional or hidden, in both the Checkout block and the classic checkout. Check phone numbers against your own pattern, for example `01#########`. Bangla and Hindi digits, spaces and dashes are cleaned up.

= Works with =

* WooCommerce 9.0 or newer, including High-Performance Order Storage (HPOS).
* The Cart and Checkout blocks, and the classic checkout.
* Right-to-left languages.

= Privacy =

Munafa does not send any data anywhere and does not load anything from outside your site. Profit figures are stored in two tables in your own database. If you turn on "Delete all Munafa data when the plugin is deleted" in the settings, deleting the plugin removes those tables and its settings. Your orders and products are never touched, only the data Munafa added to them.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Install Munafa from the Plugins screen, or upload the `munafa` folder to `/wp-content/plugins/`, then activate it.
3. Go to WooCommerce → Munafa Settings and set your courier zones, payment method fees and the statuses you count in reports.
4. Add a cost price to your products.
5. Open WooCommerce → Munafa for your dashboard. Existing orders are calculated in the background.

== Frequently Asked Questions ==

= Does it only work in Bangladesh? =

No. There are no country names, gateways or currencies built in. You set your own courier zones, fees and return reasons, so it fits any COD market.

= Where do I enter what my products cost me? =

On the product edit screen, in the General tab ("Cost price"). If your WooCommerce already has its own cost of goods field, Munafa uses that instead. Variations can have their own cost, or use the parent product's.

= How is a returned order counted? =

As a loss: the courier charge for going out and coming back, plus the gateway fee and packaging. The revenue and the product cost are both zero because the goods come back to your shelf.

= Will it change my orders or products? =

No. Munafa only adds its own data next to them. Your orders, products and customers are never modified, except that the "Returned" status is added and the order statuses you pick are yours to change.

= Can I use it with the block checkout? =

Yes. Both the Cart and Checkout blocks and the classic checkout are supported.

= Is there a Pro version? =

Not yet. Ad spend import and courier payment reconciliation are planned for later.

== Screenshots ==

1. The profit dashboard: KPI cards, trend chart and top products.
2. Returns by area and by reason.
3. The Munafa box on an order with its profit breakdown.
4. Courier zones in Munafa Settings.
5. The cost price field on a product.

== Changelog ==

= 0.1.0 =
* First release.

== Upgrade Notice ==

= 0.1.0 =
First release.
