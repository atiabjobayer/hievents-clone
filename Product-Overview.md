

> ## Documentation Index
> Fetch the complete documentation index at: https://developer.bka.sh/llms.txt
> Use this file to discover all available pages before exploring further.

# Product Overview

bKash Payment Gateway provides range of payment solutions to merchants of the online sphere.

> 📘 bKash now supports TLS 1.2 or upper versions only
>
> bKash online payment gateway is now only supports TLS version 1.2 or higher. If you are still using old versions, please upgrade your TLS version or SSL certificate.

## Available and Upcoming Payment Solutions

**Checkout** *(Available Now)*\
This feature allows customers to complete bKash payment directly from the merchant website or mobile app by entering their bKash account credentials (Account Number, Verification Code, PIN) on an embedded and secure hosted bKash page.

**Tokenized Checkout** *(Available Now)*\
Tokenized Checkout is a next generation “Checkout” feature to provide simplified and faster bKash Payment experience to the customers from the merchant website or mobile app. With Tokenized Checkout, bKash payment can be completed only by entering customers' bKash account PIN provided they have valid “Agreement ID”.

**Instant Payout** *(Available Now)*\
This solution is designed to provide an integrated collection and disbursement solution to business platforms which usually have two ends (e.g. buyer-seller, employer-employee in the job-seeking platform, customer-driver in the ride-sharing platform). Using it, bKash merchants can make instant payouts to its channel partners.

**Add Wallet** *(Available)*\
Using this feature, customers can add bKash as payment option for future payments in his/her most used merchant websites or mobile apps.

**Auth and Capture** *(Available)*\
Auth and Capture enables merchants to authorize funds beforehand for a transaction but delay the capture of funds until a later time. This solution can be used by merchants who have delayed order fulfillment process but need to ensure the availability of fund beforehand.

**Subscriptions** *(Available)*\
After one-time authorization by a customer, subscription based merchants will be able to collect recurring payments (e.g. Payment of EMI, Insurance premium etc.) using this payment solution.

## Steps for using solutions of bKash Payment Gateway

## Step 1: On-board as a bKash Merchant

To become a bKash Merchant, you will have to go through the bKash on-boarding process to setup organization account(s) for accepting payments from customers, or disbursing amounts to beneficiaries. For this, you will need to provide the required documents (such as KYC), and sign the Agreement to initiate the activation process.

Consult our Merchant Account Managers for assistance and support.

## Step 2: Stage on Sandbox

The bKash Payment Gateway Sandbox is a staging environment that mimics real payment experience. It is self-contained with mocked APIs at back-end, so that you can safely simulate your application requests using our Open APIs without using any Live bKash wallets.

Whether or not your on-boarding process has completed, you can always try out our APIs on Sandbox. It is open for everyone!

> 📘 Sandbox endpoint: https\://\<service-name>.sandbox.bka.sh

Test as much as you like on Sandbox, no e-money will be deducted.

## Step 3: Go Live on Production

After official on-boarding process is completed, bKash will issue Production credentials. Once you have successfully built your application, including testing relevant REST API calls in the sandbox, move your APP to bKash Production environment to launch services; where users can make live payment transactions on your website/app.

> 📘 Production endpoint: https\://\<service-name>.pay.bka.sh

If you test on Live environment, e-money will be debited or credited from your organization wallet based on transaction type.
