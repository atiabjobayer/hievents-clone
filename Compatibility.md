

> ## Documentation Index
> Fetch the complete documentation index at: https://developer.bka.sh/llms.txt
> Use this file to discover all available pages before exploring further.

# Compatibility

Compatibility with existing Merchant API

bKash Payment Gateway allows you to provide customers with better user experience, while assuring compatibility with payments received using existing channels. For example: customer pays via **bKash menu** (\*247#) or **bKash App** (Android or iOS), instead of the online method.

<HTMLBlock>
  {`
  <div class="custom-placeholder">
    <img width="80%" src="http://developer.pay.bka.sh/checkout-placement.png" />
  </div>

  <style>
    .custom-placeholder {
      padding: 10px;
      margin-bottom: -50px;
      
    }
  </style>
  `}
</HTMLBlock>

**Option 1**: Refers to the [bKash Checkout](checkout-overview) integration for real-time online payment experience.\
**Option 2**: Refers to an optimized version of classic payment method, where payment is validated post transaction.

It is recommended that your e-commerce application also integrates with [bKash WebHooks](webhooks) for real-time payment notifications. This will allow you to give a better user experience, even with option 2 (as it will significantly reduce human errors).
