#WooCommerce Subscribe All the Things

WooCommerce Subscribe All the Things is an experimental mini-extension for [WooCommerce Subscriptions](http://www.woothemes.com/products/woocommerce-subscriptions/).

The goal of the extension is to provide subscription support for non-subscription product types, allowing you to offer any product in your store as a subscription product.

For example, using this extension, you can offer a subscription product for the built-in WooCommerce Simple product types. It has also been tested and can be used to create a subscription product for extensions like [Composite Products](http://www.woothemes.com/products/composite-products/), [Product Bundles](http://www.woothemes.com/products/product-bundles/) and [Mix and Match Products](http://www.woothemes.com/products/woocommerce-mix-and-match-products/).

![Simple Product with Subscription Options](https://cloud.githubusercontent.com/assets/235523/11986954/8a6cd3d2-a98b-11e5-9bf8-77f2c31480b8.png)

**Example screenshot of a Simple Product with Subscription Options as displayed to the customer in a store.**

##Subscribe to Cart

In addition to subscription schemes at the product level, you can also offer subscription options at the cart level. This feature allows your customers to subscribe to the entire contents of their cart in a single subscription.

![Example Cart Subscription Options](https://cldup.com/brEjbe3wDX.png)

**Example screenshot of Subscription Options as displayed to the customer on the WooCommerce Cart page.**

If you have a store with a large number of products, using Cart Subscription Schemes can make it easier to offer your customers subscription options because you will not need to add subscription schemes to all products.

#Guide

###Requirements

In order to use the extension, you will need:

* WooCommerce Subscriptions v2.0 or newer
* WooCommerce 2.3 or newer
* A staging or test site, we do not recommend using this on live sites yet
* A sense of adventure as the codebase is still pre-beta

###Installation

1. Upload the plugin's files to the `/wp-content/plugins/` directory of your WordPress site
1. Activate the plugin through the **Plugins** menu in WordPress

##Usage: Product Subscription Schemes

To add subscription options to a non-subscription product:

1. Go to the **WooCommerce > Product > Add/Edit Product** administration screen
1. Enter the product details, including choosing a product type other than a subscription product type
1. Click the **Subscriptions** tab in the **Product Data** meta box
1. Add the subscription options for this product
1. **Optional**: Choose whether the product should default to a one-time or recurring purchase
1. **Optional**: Enter custom prompt, this is the text displayed above the subscription options to the customer on the product page

![Example Subscription Options on Simple Product](https://cloud.githubusercontent.com/assets/235523/11986952/860ba32c-a98b-11e5-84c5-b1035d4d3be1.png)

#### Discounted Prices

You can optionally offer discounted prices for each subscription option. This is a great way to provide an incentive for the customer to subscribe to the product.

To offer customers discounted prices:

1. Go to the **WooCommerce > Product > Add/Edit Product** administration screen
1. Click the **Subscriptions** tab in the **Product Data** meta box
1. Click **Subscription Price** select
1. Choose whether to have subscription prices **Inherit from the product** or **Override the product**
1. If you choose **Inherit from the product**, enter a discounted amount as a percentage (without the `%` symbol), for example, to offer a price discounted by 10%, enter `10`
1. If you choose **Override the product**, enter a new price and optional sale price for this subscription scheme

![Example Custom Prices for Subscription Options on a Simple Product](https://cldup.com/a_dlYS0yFr.png)

##Usage: Subscribe to Cart

To offer customers cart subscription options:

1. Go to the **WooCommerce > Settings** administration screen
1. Click the **Subscriptions** tab to open the subscription settings page
1. Scroll down to the **Subscribe to Cart** section
1. Add the subscription options you want to offer customers

![Administration Screen for Subscribe to Cart Settings](https://cldup.com/QMFX5DUlnY.png)

**Note:** if you do not wish to offer cart level subscription options, leave this section empty.

# Support

Subscribe All the Things is released freely and openly to get feedback on experimental ideas and approaches to solving known limitations in the WooCommerce Subscriptions plugin. A lot of features available in Subscriptions are not supported, and you may have questions about how to use certain features with it.

These questions and other issues with this plugin are not supported via the [WooCommerce support system](http://woocommerce.com/). As the extension is not sold via Woocommerce.com, the support team at WooCommerce.com are not educated about the extension and are not responsible for providing answers to questions.

If you think you have found a bug in the extension, problem with the documentation or want to see a new feature added, please [open a new issue](https://github.com/Prospress/woocommerce-subscribe-all-the-things/issues/new) and one of the developers or other users from its tiny community will do their best to help you out.

For feature requests, please understand this is a non-commercial extension and development time for it is effectively being donated and is therefore, limited.

#### Further Reading

Want to learn more? Check out the excellent post about [Subscribe All the Things on SellWithWP.com](https://www.sellwithwp.com/woocommerce-subscribe-all-the-things/).

#### License

This plugin is released under [GNU General Public License v3.0](http://www.gnu.org/licenses/gpl-3.0.html).

#### Credits

This extension is developed and maintained as a collaboration between the teams at [Prospress](http://prospress.com/) and [SomewhereWarm](http://somewherewarm.net/).

---

<p align="center">
<img src="https://cloud.githubusercontent.com/assets/235523/11986380/bb6a0958-a983-11e5-8e9b-b9781d37c64a.png" width="160">
</p>
