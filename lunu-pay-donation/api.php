<?php
$ROOT_PATH = dirname(__FILE__);

include $ROOT_PATH . '/config.php';

$AUTH_TOKEN = base64_encode($APP_ID . ':' . $API_SECRET);
$CURRENT_TIME = time();

$LUNUPAY_ENDPOINT = 'https://' . $LUNUPAY_VERSION . '.lunu.io/api/v1/payments/';
$LUNUPAY_URL_CREATE = $LUNUPAY_ENDPOINT . 'create';
$LUNUPAY_URL_GET = $LUNUPAY_ENDPOINT . 'get/';

$LUNUPAY_DEFAULT_EXPIRES = 3600;

header("Content-type: application/json; charset=utf-8");

$API_METHOD = $_GET['method'];

$POST_DATA = json_decode(file_get_contents('php://input'), true);
if (!is_array($POST_DATA)) $POST_DATA = array();

if ($API_METHOD === 'notify') {
  $payment_status = strtolower($POST_DATA['status']);
  $payment_id = $POST_DATA['id'];

  if (
    !empty($payment_id)
    && (
      $payment_status === 'paid'
      || $payment_status === 'awaiting_payment_confirmation'
    )
  ) {

    // checking payment on server
    $url_get = $LUNUPAY_URL_GET . $payment_id;
    $ch = curl_init($url_get);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Basic ' . $AUTH_TOKEN,
    ));

    $responseBody = curl_exec($ch);
    $responseHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($responseHttpCode === 200) {
      $responseWrap = json_decode($responseBody, true);
      $payment = $responseWrap['response'];

      // Your code of notification should be here

      $payment_status = strtolower($payment['status']);
      $amount = $payment['amount'];
      $currency = $payment['currency'];

      if ($payment_status === 'awaiting_payment_confirmation') {
        // code on awaiting for transaction confirmation in blockchain
        // when transaction found in mempool
        // ...
      } elseif ($payment_status === 'paid') {
        // code on paid
        // when transaction found in blockchain

        // Email notification
        if (isset($EMAIL_TO)) {
          $amount = $payment['amount'];
          $currency = $payment['currency'];

          $headers = array(
            'Content-type' => 'text/html',
            'From' => $EMAIL_FROM,
            'Reply-To' => $EMAIL_FROM
          );
          $subject = 'To you donated ' . $amount . ' ' . $currency .' through the Lunu Pay';

          $message = $subject;
          $message .= '<br>Created at: ' . $payment['created_at'];
          $message .= '<br>Description: ' . $payment['description'];
          $message .= '<br><br><br>Thanks for using Lunu Pay!';

          mail($EMAIL_TO, $subject, $message, $headers);
        }
      }
    } else {
      ob_start();
      var_dump(array(
        'get_data' => $_GET,
        'post_data' => $POST_DATA,
        'check' => array(
          'url' => $url_get,
          'code' => $responseHttpCode,
          'body' => $responseBody
        ),
      ));
      file_put_contents(
        __DIR__ . '/lunu_error_log.txt',
        date('Y-m-d H:i:s') . ' ' . ob_get_clean() . PHP_EOL,
        FILE_APPEND
      );
    }

  }

  echo json_encode(array(
    'response' => array(
      'status' => 'success'
    ),
  ), JSON_UNESCAPED_UNICODE);
  exit;
}


if ($API_METHOD === 'create') {
  $order_id = rand() . '_' . $CURRENT_TIME;

  $ch = curl_init($LUNUPAY_URL_CREATE);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
    'amount' => $POST_DATA['amount'],
    'callback_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/lunu-pay-donation/api.php?method=notify',
    'description' => 'Donate #' . $order_id,
    'expires' => date("c", $CURRENT_TIME + $LUNUPAY_DEFAULT_EXPIRES),
    'test' => false
  )));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Basic ' . $AUTH_TOKEN,
    'Idempotence-Key: '. $order_id,
    'Content-Type: application/json'
  ));

  echo curl_exec($ch);
  curl_close($ch);

  exit;
}

echo json_encode(array(
  'error' => array(
    'code' => 501,
    'message' => 'Could not find method implementation',
  ),
), JSON_UNESCAPED_UNICODE);

?>
