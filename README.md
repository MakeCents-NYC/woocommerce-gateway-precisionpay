# WooCommerce Gateway PrecisionPay

PrecisionPay payments for WooCommerce is the official PrecisionPay payment gateway for WooCommerce. This plugin uses Plaid (https://plaid.com/) along with the PrecisionPay checkout portal to allow your customers to pay with PrecisionPay as a guest (using Plaid) or as a PrecisionPay user (if they already have an account at myprecisionpay.com).

## Setup

Prerequisites:

- Make sure you have WooCommerce (https://wordpress.org/plugins/woocommerce/) installed.
- You will need a PrecisionPay Merchant account. Don't have an account? Contact us: support@myprecisionpay.com
- You will also need to have a connected bank account in the PrecisionPay Merchant portal. The plugin will not work if your bank account isn't connected. (You can't accept payments if you don't have a place for them to go!)

There are a few steps you are going to need to follow to use this plugin properly:

1. Log into your PrecisionPay Merchant account.
1. In the top navigation, click on API Keys.
1. Create a new API key. DON'T FORGET TO COPY THE SECRET AND SAVE IT IN A SAFE PLACE as you will not be able to retrieve it from the site later.

If you haven't added your bank account yet, here's how you do it:

1. Log into your PrecisionPay Merchant account.
1. In the top navigation, click on Account Settings.
1. Click the Add Bank Account button.
1. Choose Add Bank Account with Plaid or Manually Add Bank Account.
1. Complete the steps on screen.

Once you've installed and activated the PrecisionPay plugin, do the following in your Wordpress admin panel:

1. Make sure you have WooCommerce installed.
1. Go to the WooCommerce Settings page.
1. Click the Payments tab.
1. Activate the PrecisionPay Payment Gateway.
1. Click Manage.
1. Add your PrecisionPay Merchant API key and secret into the API key and secret fields.
1. Check "Enable Test Mode" if you want to test your ability to make a purchase without spending any money. This puts Plaid in sandbox mode. At checkout, click the PrecisionPay "Authorize Payment" button and use guest checkout to authorize the payment. When Plaid prompts you for the username type: "user_good", and for the password type: "pass_good". Once you are satisfied everything is working, uncheck "Enable Test Mode" and you will be ready to accept payments with PrecisionPay!
