# WooCommerce Subscribe All the Things

WooCommerce Subscribe All the Things is an experimental mini-extension for [WooCommerce Subscriptions](http://woocommerce.com/products/woocommerce-subscriptions/) that allows you to add subscription options to non-subscription product types, such as Simple and Variable products.

The plugin has been tested and can be used to add subscription options to [Product Bundles](http://woocommerce.com/products/product-bundles/), [Composite Products](http://woocommerce.com/products/composite-products/) and [Mix and Match Products](http://woocommerce.com/products/woocommerce-mix-and-match-products/).

![Simple Product with Subscription Options](https://cloud.githubusercontent.com/assets/235523/11986954/8a6cd3d2-a98b-11e5-9bf8-77f2c31480b8.png)

## Cart Subscriptions

In addition to adding subscription options to individual products, Subscribe All the Things can be used to **offer options for subscribing to an entire cart** before checkout.

![Example Cart Subscription Options](https://cldup.com/brEjbe3wDX.png)

# Guide

### Requirements

In order to use the extension, you will need:

* WooCommerce Subscriptions 2.1 or newer.
* WooCommerce 3.0 or newer.
* A sense of adventure.

**Note:** We do not recommend using Subscribe All the Things on live sites. While we do our best to add new features, squash bugs, and keep the plugin up-to-date, **commercial use of the plugin is strongly discouraged**: Support inquiries may not be answered in a timely manner and critical issues may not be resolved promptly, as all development/support time is currently being donated.

### Installation

1. Upload the plugin's files to the `/wp-content/plugins/` directory of your WordPress site.
1. Activate the plugin through the **Plugins** menu in WordPress.

## Usage: Product Subscription Schemes

To add subscription options to a non-subscription product:

1. Go to the **WooCommerce > Product > Add/Edit Product** administration screen.
1. Enter product details after choosing a supported product type (subscription product types are unsupported).
1. Navigate to **Product Data > Subscriptions**.
1. Add subscription options.
1. **Optional**: Choose whether the product should default to a one-time or recurring purchase.
1. **Optional**: Enter custom prompt, this is the text displayed above the subscription options to the customer on the product page.

![Example Subscription Options on Simple Product](https://cloud.githubusercontent.com/assets/235523/11986952/860ba32c-a98b-11e5-84c5-b1035d4d3be1.png)

#### Subscription Discounts

You can optionally offer a discounted price unique to each subscription option. This is a great way to provide an incentive for the customer to subscribe to a product.

To offer subscription discounts:

1. Go to the **WooCommerce > Product > Add/Edit Product** administration screen.
1. Click the **Subscriptions** tab in the **Product Data** meta box.
1. Click **Subscription Price** select.
1. Select the **Inherit from product** or **Override product** option:
	* If you choose **Inherit from product**, enter a discounted amount as a percentage (without the `%` symbol), for example, to offer a price discounted by 10%, enter `10`.
	* If you choose **Override product**, enter a new price and optional sale price.

![Example Custom Prices for Subscription Options on a Simple Product](https://cldup.com/a_dlYS0yFr.png)

## Usage: Subscribe to Cart

To offer cart subscription options:

1. Go to the **WooCommerce > Settings** administration screen.
1. Click the **Subscriptions** tab to open the subscription settings page.
1. Scroll down to the **Subscribe to Cart** section.
1. Add subscription options.

![Administration Screen for Subscribe to Cart Settings](https://cldup.com/QMFX5DUlnY.png)

**Note:** If you do not wish to offer cart subscription options, leave this section empty.

# Support

Subscribe All the Things is released freely and openly to get feedback on experimental ideas and approaches to solving known limitations in the WooCommerce Subscriptions plugin. A lot of features available in Subscriptions are not supported, and you may have questions about how to use certain features with it.

These questions and other issues with this plugin are not supported via the [WooCommerce Helpdesk](http://woocommerce.com/). As the extension is not sold via Woocommerce.com, the support team at WooCommerce.com is not familiar with it and may not be able to assist.

If you think you have found a bug in the extension, a problem with the documentation, or want to see a new feature added, please [open a new issue](https://github.com/Prospress/woocommerce-subscribe-all-the-things/issues/new) and one of the developers or other users from its tiny community will do their best to help you out.

Please understand this is a non-commercial extension. As such:

* Development time for it is effectively being donated and is therefore, limited.
* Support inquiries may not be answered in a timely manner.
* Critical issues may not be resolved promptly.

#### Further Reading

Want to learn more? Check out the excellent post about [Subscribe All the Things on SellWithWP.com](https://www.sellwithwp.com/woocommerce-subscribe-all-the-things/).

#### License

This plugin is released under [GNU General Public License v3.0](http://www.gnu.org/licenses/gpl-3.0.html).

#### Credits

This extension is developed and maintained as a collaboration between the teams at [Prospress](http://prospress.com/) and [SomewhereWarm](http://somewherewarm.gr/).

---

<p align="center">
<img src="https://cloud.githubusercontent.com/assets/235523/11986380/bb6a0958-a983-11e5-8e9b-b9781d37c64a.png" width="160">
</p>
