=== Oyster WhatsApp for WooCommerce ===
Contributors: oysterskin
Tags: woocommerce, whatsapp, interakt, skincare, notifications
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Oyster skin scan results and product recommendations to shoppers on WhatsApp, through your own Interakt account.

== Description ==

Shoppers who take a skin scan on your store normally get their results by email. This
plugin sends them on WhatsApp instead, or as well, using your own Interakt account.

When a scan finishes, and again when its product recommendations are ready, this plugin
records the event against that shopper in Interakt. You decide what happens next: build an
automation in Interakt and write the message in your own words, or name one of your
approved templates here and have it sent directly.

Sending a template directly is the only way to attach the PDF report, which arrives as a
document in the chat. There is no results page for the shopper to visit.

Requires **Oyster for WooCommerce**, connected to your Oyster vendor account. This plugin
adds nothing to your storefront and changes nothing about how scans work.

= What you need =

* An Oyster vendor account with Oyster for WooCommerce connected.
* An Interakt account with WhatsApp Business approved.
* At least one approved WhatsApp template, if you want messages sent directly.

== Privacy ==

**This plugin sends personal data to a third party.** When a scan completes at your store,
it sends the shopper's **name, email address and phone number** to Interakt
(https://www.interakt.ai), along with a summary of their scan: the one-line headline, the
names of their recommended products, a link to their PDF report and a link that adds those
products to their cart.

It does **not** send the skin analysis itself. Scores, detected concerns and the detail
behind them stay in Oyster and are never copied into Interakt.

The report link is short-lived and expires shortly after it is issued. Interakt fetches
the PDF once while sending the message.

Your shopper's recorded marketing preferences are sent to Interakt as customer attributes
so you can segment on them. They do not decide whether the result itself is sent: a scan
result is something the shopper asked for by scanning, so it is delivered as part of that.
Anything promotional you build on top is yours to gate, in Interakt.

WhatsApp requires businesses to have opt-in before sending template messages. That is
enforced by WhatsApp against your own business account, and collecting it is your
responsibility as the sender.

Nothing is sent until you add your Interakt key and enable an event.

== External services ==

This plugin connects to two services.

**Oyster** (https://api.oysterskin.com). Read through Oyster for WooCommerce, using the
credential that plugin already holds for your store, to get the contact details, report
link, checkout link and recommended products for a scan. This plugin holds no Oyster
credential of its own. See https://oysterskin.com/privacy for Oyster's privacy policy and
https://oysterskin.com/terms for its terms of service.

**Interakt** (https://api.interakt.ai). Called with your Interakt API key to record the
shopper and the scan event, and to send a WhatsApp template when you have named one. See
https://www.interakt.shop/privacy-policy/ for Interakt's privacy policy and
https://www.interakt.shop/terms-of-service/ for its terms of service. Interakt is the
sender of record for these messages, under your own WhatsApp Business account.

Your Interakt API key is stored encrypted on your site and is never displayed again after
you save it.

== Installation ==

1. Install and connect **Oyster for WooCommerce**.
2. Install and activate this plugin.
3. In Interakt, copy your API key from **Developer Settings**.
4. Go to **Settings → Oyster WhatsApp**, paste it, and enable the events you want.
5. To have messages sent directly, create and get approval for a WhatsApp template, then
   name it on that screen. The variables it must use are listed there.

== Frequently Asked Questions ==

= Do I have to turn off result emails? =

No. The two are independent. Turn emails off in your Oyster dashboard if you want WhatsApp
to be the only channel.

= What happens if Interakt is down? =

Sending is queued and retried with a growing delay for several attempts. A failure never
affects the scan itself, and never interrupts the events Oyster sends to your store.

= Can I send one message instead of two? =

Yes. Enable only "When recommendations are ready". It carries the same report and checkout
links, and it arrives after the routine has been generated.

= Why did a shopper not get a message? =

Most often there is no phone number on record for them. A number is only collected when
phone collection is switched on for your Oyster account.

== Changelog ==

= 0.1.0 =
* First release. Sends scan and recommendation events to Interakt, with an optional direct
  template send carrying the PDF report and a cart link. Reads your scans through Oyster
  for WooCommerce, so there is no second Oyster key to create.
