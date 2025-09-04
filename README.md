# Lunu Payment. Donation Widget. PHP Version


Copy folder "lunu-pay" to the site directory

Open file "lunu-pay-donation/config.php" and edit the fields **APP_ID** and **API_SECRET**.


```php
$APP_ID = '149ec6da-f0dc-4cdf-9fb3-8ba2dc602f60';
$API_SECRET = '23d93cac-000f-5000-8000-126728f15140';

// for notifications by php mail when paid
$EMAIL_FROM = 'YOUR_EMAIL_SENDER';
$EMAIL_TO = 'YOUR_EMAIL_RECEIVER';

// $LUNUPAY_VERSION = 'api'; // production server
$LUNUPAY_VERSION = 'testing'; // test server
```



# Attention!

Keep in mind that if the site in which You are testing payments is not publicly
accessible for requests from the Internet, then notifications of changes in
the status of payments from Our processing service will not be able to reach
Your online store, as a result of which the status of orders in your store will not change.




Add the code for initialization to the desired page of the site  



## Example

```html
<script src="https://plugins.lunu.io/packages/widget-ui/omega.js"></script>
<div id="donation-form"></div>
<script>
// Widget initialization
const widget = new window.Lunu.widgets.Donation(
  document.getElementById('donation-form'), // Required parameter
  {
    version: 'testing', // test server
    endpoint: '/lunu-pay-donation/api.php', // Your API endpoint
    min: 50,
    max: 5000,
    step: 50,
    amount: 1000,
  },
);

</script>
```


#### Asynchronous initialization without caching

```html
<div id="donation-form"></div>

<script>
(function(d, t, f) {
  var n = d.getElementsByTagName(t)[0], s = d.createElement(t);
  s.type = 'text/javascript';
  s.charset = 'utf-8';
  s.async = true;
  s.src = 'https://plugins.lunu.io/packages/widget-ui/omega.js?t=' + 1 * new Date();
  s.onload = function() {
    new window.Lunu.widgets.Donation(
      d.getElementById('donation-form'),
      {
        endpoint: '/lunu-pay-donation/api.php', // Your API endpoint
        version: 'testing', // test server
        min: 1000,
        max: 5000,
        step: 50,
        amount: 1000,
      },
    );
  };
  n.parentNode.insertBefore(s, n);
})(document, 'script');
</script>
```



#### This code creates a payment and immediately opens a payment widget on some event

```html
<script src="https://plugins.lunu.io/packages/widget-ui/omega.js"></script>
<script>
const paymentAPI = new window.Lunu.API({
  endpoint: '/lunu-pay-donation/api.php', // Your API endpoint
});
function openWidget(amount) {
  paymentAPI.create({
    /*
      All of this user data is sent to the your endpoint script where you should handle it.
      In our example, only the "amount" field is handled..
      See https://gitlab.lunu.io/widget/php/-/blob/master/lunu-pay/api.php
    */
    amount: amount,
  }, {
    version: 'testing', // test server
    message_success: 'Donation successful',
    message_fail: 'Donation fail',
  })
      .then(function(result) {
        var status = result.status;
        if (status === 'canceled') {
          // Handling a payment cancellation event
        }
        if (status === 'paid') {
          // Handling a successful payment event
        }
      });
}
</script>
<button
  onclick="openWidget(3)"
>Donate</button>
```



### How do redirect on success payment?

```html
<script src="https://plugins.lunu.io/packages/widget-ui/omega.js"></script>
<script>
const paymentAPI = new window.Lunu.API({
  endpoint: '/lunu-pay-donation/api.php', // Your API endpoint
});
function openWidget(amount) {
  paymentAPI.create({
    amount: amount,
  }, {
    version: 'testing', // test server
    message_success: 'Donation successful',
    message_fail: 'Donation fail',
  })
      .then(function(result) {
        if (result.status === 'paid') {
          // Do redirect on a successful payment event
          window.location.href = 'https://example.site/success-payment';
        }
      });
}
</script>
<button
  onclick="openWidget(3)"
>Donate</button>
```



## Lunu Payment API. General information.

URL pattern:
```
https://{testing|api}.lunu.io/api/v1/<method>
```

API endpoints:

  * alpha.lunu.io - production server
  * testing.lunu.io - server for product debugging in the sandbox, you can use there a test-net cryptocurrency.
  To debug payment with this server, reconfigure the Lunu Wallet to test mode



The API is available for authorized users.
Unauthorized users receive an empty response and status
```
404 Not found
```

All responses are returned in JSON format.

The responses from the server are wrapped:

  * a successful response is returned in the response field:
```
{
   "response": {...}
}
```

  * if it is necessary to return an error, then the error is returned in the error field, for example:

```
{
   "error": {
     "code": 1,
     "message": "..."
   }
}
```

### Authentication

HTTP Basic Auth must be used to authenticate requests.
In the request headers, you must pass the merchant ID as the username, and the secret key as the password.

Example header:
```
Authorization: Basic QWxhZGRpbjpPcGVuU2VzYW1l
```
where QWxhZGRpbjpPcGVuU2VzYW1l is the result of the function: base64(app_id + ':' + secret_key)


### Idempotency

In the context of an API, idempotency means that multiple requests are handled in the same way as single requests.  
This means that upon receiving a repeated request with the same parameters, the Processing Service will return the result of the original request in response.  
This behavior helps to avoid unwanted replay of transactions. For example, if during a payment there are network problems and the connection is interrupted, you can safely repeat the required request an unlimited number of times.  
GET requests are idempotent by default, since they have no unwanted consequences.  
To ensure the idempotency of POST requests, the Idempotence-Key header (or idempotence key) is used.

Example header:
```
Idempotence-Key: 3134353
```
where 3134353 is the result of the function: uniqid()

The idempotency key needs to be unique within the individual application ID of the account.  
One application ID cannot be used in several stores, otherwise it may not be sufficient to use only the store's internal order number as the idempotency key, since these values may be repeated in requests from other stores with the same application ID.


### Scenario for making a payment through the Widget

When the user proceeds to checkout (this can be either a specific product or a basket of products),
the payment process goes through the following stages:



#### 1. Payment creation. payments/create

The merchant's website or application sends a request to the **Processing Service** to create a payment, which looks like this:
```
POST https://rc.lunu.io/api/v1/payments/create
Authorization: Basic QWxhZGRpbjpPcGVuU2VzYW1l
Idempotence-Key: 3134353
Content-Type: application/json
```
```json
{
  "email": "customer@example.com",
  "shop_order_id": "208843-42-23-842",
  "amount": "100.00",
  "amount_of_shipping": "15.00",
  "callback_url": "https://website.com/api/change-status",
  "description": "Order #208843-42-23-842",
  "expires": "2020-02-22T00:00:00-00:00"
}
```

Description of fields:

	* email (string) (optional parameter) - customer email; used when a refund is required;  

  * shop_order_id (string) (optional parameter) - shop order id;  

  * amount (number) - payment amount (currency is indicated in the merchant's profile);  

  * amount_of_shipping (string) (optional parameter) - amount of shipping;  

  * callback_url (string) (optional parameter) - url-address of the store's callback API,
    to which the **Processing service** will send a request when the payment status changes (when the payment is made)

  * description (string) (optional parameter) - if you need to add a description of the payment
    that the seller wants to see in the personal account, then you need to pass the description parameter.
    The description should be no more than 128 characters.

  * expires (string) (optional parameter) - date when the payment expires, in RFC3339 format. By default: 1 minute from the moment of sending;


The **Processing Service** returns the created payment object with a token for initializing the widget.
```json
{
  "id": "23d93cac-000f-5000-8000-126628f15141",
  "status": "pending",
  "amount": "100.00",
  "currency": "EUR",
  "description": "Order #208843-42-23-842",
  "confirmation_token": "ct-24301ae5-000f-5000-9000-13f5f1c2f8e0",
  "created_at": "2019-01-22T14:30:45-03:00",
  "expires": "2020-02-22T00:00:00-00:00"
}
```

Description of fields:

  * id (string) - payment ID;

  * status (string) - payment status. Value options:  

		* "pending" - awaiting payment;  
		* "awaiting_payment_confirmation" - the transaction was found in the mempool, it is awaiting confirmation in the blockchain network;
		* "paid" - payment has been made;  
		* "canceled" - the payment was canceled by the seller;  
		* "expired" - the time allotted for the payment has expired;  



  * amount (number)- amount of payment;

  * currency (string) - payment currency;

  * description (string) - payment description, no more than 128 characters;

  * confirmation_token (string) - payment token, which is required to initialize the widget;

  * created_at (string) - the date the payment was created;

  * expires (string) - the date when the payment expires, in RFC3339 format.




#### 2. Initialize the widget and display the forms on the payment page.

To initialize the widget, insert the following code into the body of the html page:

```html
<!-- Library connection -->

<!-- production server -->
<!--
<script src="https://plugins.lunu.io/packages/widget-ui/alpha.js"></script>
-->

<!-- test server -->
<script src="https://plugins.lunu.io/packages/widget-ui/testing.js"></script>



<!-- HTML element in which the payment form will be displayed -->
<div id="payment-form"></div>

<script>
// Initialization of the widget.
const widget = new window.Lunu.widgets.Payment(
  document.getElementById('payment-form'), // Обязательный параметр
  {
    /*
    Token that must be received from the Processing Service before making a payment
    Required parameter
    */
    confirmation_token: 'ct-24301ae5-000f-5000-9000-13f5f1c2f8e0',

    callbacks: {
      payment_paid() {
        // Handling a successful payment event
      },
      payment_cancel() {
        // Handling a payment cancellation event
      },
      payment_close() {
        // Handling the event of closing the widget window
      },
    },
  },
);
</script>
```



#### 3. Notifying the seller's store about a change in payment status. Payment Callback

When the user has made a payment, the **Processing Service** sends a request in the
following format to the store's API url, which was specified when creating the payment:

```
POST https://website.com/api/change-status
```
```json
{
  "id": "23d93cac-000f-5000-8000-126628f15141",
  "shop_order_id": "208843-42-23-842",
  "status": "paid",
  "amount": "100.00",
  "currency": "EUR",
  "description": "Order #1",
  "created_at": "2019-01-22T14:30:45-03:00",
  "expires": "2020-02-22T00:00:00-00:00"
}
```

Description of fields:

  * id (string) - payment ID;

  * shop_order_id (string) (optional parameter) - shop order id;  

  * status (string) - payment status. Value options:

		* "awaiting_payment_confirmation" - the transaction was found in the mempool, it is awaiting confirmation in the blockchain network;
		* "paid" - payment has been made;  
		* "canceled" - the payment was canceled by the seller;  
		* "expired" - the time allotted for the payment has expired;  



  * amount (number)- amount of payment;

  * currency (string) - payment currency;

  * test (boolean) - a flag indicating that the API is being used in test mode.
    True if the payment is made in test mode.

  * description (string) - payment description, no more than 128 characters;

  * created_at (string) - the date the payment was created;

  * expires (string) - the date when the payment expires, in RFC3339 format.




#### 4. The store checks the validity of the notification received. payments/get/{payment_id}

After the merchant has received a notification about the change in the payment status,
it needs to check the validity of this notification through the **Processing Service**
by the following request:
```
POST https://testing.lunu.io/api/v1/payments/get/{payment_id}
Authorization: Basic QWxhZGRpbjpPcGVuU2VzYW1l
```

If everything is good then the **Processing Service** returns an identical payment object:
```
{
  "id": "23d93cac-000f-5000-8000-126628f15141",
  "status": "paid",
  "shop_order_id": "208843-42-23-842",
  "amount": "100.00",
  "currency": "EUR",
  "description": "Order #208843-42-23-842",
  "created_at": "2019-01-22T14:30:45-03:00",
  "expires": "2020-02-22T00:00:00-00:00"
}
```


# API credentials

To make new API credentials, You should make order a Widget on this page: https://console.lunu.io/developer-options


For debugging, you can use the following credentials:  

* sandbox mode:  
  * App Id: 8ce43c7a-2143-467c-b8b5-fa748c598ddd  
  * API Secret: f1819284-031e-42ad-8832-87c0f1145696  
* production mode:  
  * App Id: a63127be-6440-9ecd-8baf-c7d08e379dab  
  * API Secret: 25615105-7be2-4c25-9b4b-2f50e86e2311  
