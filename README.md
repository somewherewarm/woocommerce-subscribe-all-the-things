# WooCommerce Subscribe All The Things

[![Build Status](https://travis-ci.org/Prospress/woocommerce-subscribe-all-the-things.svg?branch=master)](https://travis-ci.org/Prospress/woocommerce-subscribe-all-the-things)
[![codecov](https://codecov.io/gh/Prospress/woocommerce-subscribe-all-the-things/branch/master/graph/badge.svg)](https://codecov.io/gh/Prospress/woocommerce-subscribe-all-the-things)

WooCommerce Subscribe All The Things is a mini-extension for [WooCommerce Subscriptions](http://woocommerce.com/products/woocommerce-subscriptions/) that allows you to add subscription options to non-subscription product types, such as Simple and Variable products. The plugin has been tested with and can be used to add subscription options to [Product Bundles](http://woocommerce.com/products/product-bundles/), [Composite Products](http://woocommerce.com/products/composite-products/) and [Mix and Match Products](http://woocommerce.com/products/woocommerce-mix-and-match-products/).

<p align="center">
	<img width="800" src="https://user-images.githubusercontent.com/1783726/37648362-6aaeab16-2c37-11e8-84c1-aec208e9f447.png" alt="Simple Product with Subscription Options"/>
</p>

# Features

Capture more residual revenue by offering existing products on a recurring basis. Subscribe All The Things allows you to add subscription options to:

* Any Simple or Variable product -- no need to introduce new SKUs or complicate inventory management.
* Grouped product types such as [Composite Products](https://woocommerce.com/products/composite-products/) and [Product Bundles](https://woocommerce.com/products/product-bundles/).
* The cart page -- give customers the option to purchase their entire cart on a recurring billing and shipping schedule.

<p align="center">
	<img width="800" src="https://user-images.githubusercontent.com/1783726/37654834-1e213f24-2c4c-11e8-85ee-c1605325bb92.png" alt="Subscription Options Offered in the Cart"/>
</p>

To incentivize customers to subscribe, you can even assign a different/discounted product price to each subscription option:

<p align="center">
	<img width="800" src="https://user-images.githubusercontent.com/1783726/37655470-11cab4c4-2c4e-11e8-8d24-6106c88c742d.png" alt="Simple Product with Discounted Subscription Options"/>
</p>

Additionally, Subscribe All The Things makes it possible to **add products and entire carts to existing subscriptions**:

* Products without subscription options can be added to **any active subscription**.
* Products with subscription options can be added only to active subscriptions with a **matching billing schedule**.

<p align="center">
	<img width="800" src="https://user-images.githubusercontent.com/1783726/37670668-8b587e9c-2c72-11e8-8260-efcceb8b5eee.png" alt="Adding a Product to an Existing Susbcription."/>
</p>


# Guide

### Requirements

To use the plugin, you will need:

* WooCommerce Subscriptions 2.1 or newer.
* WooCommerce 3.0 or newer.
* A sense of adventure.

**Note:** While we do our best to add new features, squash bugs and keep the plugin updated, we currently **do not offer a dedicated, premium support channel** for Subscribe All The Things. If you decide to install it on a production site, please remember that general support inquiries may not be answered in a timely manner and critical issues may not be resolved promptly.

### Installation

0. Download the latest version from [GitHub](https://github.com/Prospress/woocommerce-subscribe-all-the-things/releases)
1. Go to **WordPress Admin &gt; Plugins &gt; Add New**.
2. Click **Upload Plugin** at the top.
3. **Choose File** and select the .zip file you downloaded in Step 1.
4. Click **Install Now** and **Activate** the plugin.

### Product Subscription Options

To add subscription options to a non-subscription product:

1. Navigate to **Product Data > Subscriptions**.
2. Click **Add Option** to add subscription options.
3. **Optional**: Check **Force Subscription** if the product must be available on a recurring basis only.
4. **Optional**: If applicable, define whether the product should default to a one-time or recurring purchase using the **Default to** option.
5. **Optional**: Enter a custom **Subscription prompt** -- this is the text displayed above the subscription options on the product page.

![](http://pic.pros.pr/30777652ee09/Image%2525202018-10-16%252520at%25252021.19.56.png)


#### Subscription Discounts

You can optionally offer a discounted price that's unique to each subscription option. This is a great way to provide an incentive for customers to subscribe to a product.

To offer subscription discounts locate the **Price** option and select either **Inherit from product** or **Override product**:

* **Inherit from product** allows you to enter a discounted amount as a percentage (without the `%` symbol) -- for example, to offer a price discounted by 10%, enter `10`.
* **Override product** allows you to override the default **Regular Price** and **Sale Price** of the product.

<p align="center">
	<img width="800" src="https://user-images.githubusercontent.com/1783726/37664257-996da444-2c63-11e8-8b6b-c24aedd92ef3.png" alt="Adding and Configuring Subscription Options"/>
</p>


### Cart Subscription Options

To offer cart subscription options:

1. Go to **WooCommerce > Settings**.
2. Click the **Subscriptions** tab to open the subscription settings page.
3. Scroll down to the **Subscribe to Cart** section.
4. Click **Add Option** to add some subscription options.

**Note:** If you do not wish to offer cart subscription options, leave this section empty.


# Support

Subscribe All The Things is released freely and openly to get feedback on experimental ideas and approaches to solving known limitations in WooCommerce Subscriptions. However, some features available in WooCommerce Subscriptions are not supported by Subscribe All The Things.

Subscribe All The Things is not supported via the [WooCommerce Helpdesk](http://woocommerce.com/). As the extension is not sold via Woocommerce.com, the support team at WooCommerce.com is not familiar with it and may not be able to assist.

If you think you have found a bug in the extension, a problem with the documentation, or want to see a new feature added, please [open a new issue](https://github.com/Prospress/woocommerce-subscribe-all-the-things/issues/new) and one of the developers or other users from its tiny community will do their best to help you out.

At present we **do not offer a dedicated, premium support channel** for Subscribe All The Things. Please understand this is a non-commercial extension. As such:

* Development time for it is effectively being donated and is therefore, limited.
* Support inquiries may not be answered in a timely manner.
* Critical issues may not be resolved promptly.

#### Further Reading

Want to learn more? Check out the excellent post about [Subscribe All The Things on SellWithWP.com](https://www.sellwithwp.com/woocommerce-subscribe-all-the-things/).

#### License

This plugin is released under [GNU General Public License v3.0](http://www.gnu.org/licenses/gpl-3.0.html).

#### Credits

This extension is developed and maintained as a collaboration between the teams at [Prospress](http://prospress.com/) and [SomewhereWarm](http://somewherewarm.gr/).

---

<p align="center">
	<img src="https://cloud.githubusercontent.com/assets/235523/11986380/bb6a0958-a983-11e5-8e9b-b9781d37c64a.png" width="160">
</p>
