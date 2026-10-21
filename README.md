# Rayo SMS PHP SDK

Official PHP SDK for the Rayo SMS Gateway API. Easily integrate Web-to-SMS functionality, schedule SMS deliveries, fetch account credit balances, track delivery statuses, and verify webhook callbacks in your PHP applications.

## Requirements

- PHP 7.4 or higher (`8.0+` recommended)
- `ext-json`
- [Composer](https://getcomposer.org/)

## Installation

Install the package via Composer:

```bash
composer require rayo-sms/rayo-sms-php-sdk
```

## Quick Start

Initialize the client with your API key (managed in **Actions -> Settings** inside the web application) and the API base URL (`https://<base-url>`):

```php
<?php

require_once 'vendor/autoload.php';

use Rayo\SmsSdk\Client;
use Rayo\SmsSdk\Exceptions\RayoException;

$apiKey  = getenv('SMS_API_KEY');
$baseUrl = 'https://<base-url>';

$client = new Client($apiKey, $baseUrl);

try {
    // Send a simulated SMS (does not consume credits)
    $response = $client->sendSms([
        'fromLabel'        => 'MYBRAND',
        'toMobileNumber'   => '306912345678',
        'smsBody'          => 'Hello from the PHP SDK!',
        'simulateSendFlag' => true,
    ]);

    echo 'SMS Sent! Server Request ID: ' . $response->getSmsServerRequestId() . PHP_EOL;
} catch (RayoException $e) {
    echo 'Error (' . $e->getStatusCode() . '): ' . $e->getMessage() . PHP_EOL;
}
```

> **Note:** You can pass either `'https://<base-url>'` or `'https://<base-url>/v1/'` as the base URL—the SDK automatically normalizes the `/v1/` version prefix. You may also pass an options array as the second argument:
> ```php
> $client = new Client($apiKey, [
>     'base_url' => 'https://<base-url>',
>     'timeout'  => 15.0,
> ]);
> ```

---

## Usage Examples

### 1. Sending an SMS (`POST /v1/send_sms`)

You can send an SMS to one or more mobile numbers (separated by `;` or `,` or passed as an array) or to one or more contact groups registered in your account.

#### Using an Associative Array

```php
$response = $client->sendSms([
    'fromLabel'                 => 'MYBRAND',
    'toMobileNumber'            => '306912345678;306987654321',
    'toGroup'                   => 'High Valued Customers;Promotion Customers',
    'smsBody'                   => 'Hello! Your order has been dispatched.',
    'clientRequestId'           => 'order-9543212',
    'callbackWhenProcessedFlag' => true,
    'simulateSendFlag'          => false,
    'concatFlag'                => true,
]);

if ($response->isSuccess()) {
    echo 'Username: ' . $response->getUsername() . PHP_EOL;
    echo 'Server Request ID: ' . $response->getSmsServerRequestId() . PHP_EOL;
    echo 'Client Request ID: ' . $response->getClientRequestId() . PHP_EOL;
}
```

#### Using the `SmsRequest` Value Object

```php
use Rayo\SmsSdk\Models\SmsRequest;

$request = new SmsRequest(
    'MYBRAND',                                    // fromLabel (required)
    'Hello! Your verification code is 123456.',   // smsBody (required, max 1000 chars)
    ['306912345678', '306987654321'],             // toMobileNumber (string, array, or null)
    null,                                         // toGroup (string, array, or null)
    'verify-9543212',                             // clientRequestId (optional)
    true,                                         // callbackWhenProcessedFlag (optional)
    false,                                        // simulateSendFlag (optional)
    true                                          // concatFlag (optional)
);

$response = $client->sendSms($request);
```

---

### 2. Scheduling an SMS for Future Delivery (`POST /v1/send_sms`)

Provide `scheduledAt` as an ISO-8601 string with timezone offset (or a `DateTimeInterface` instance when using `SmsRequest`). The scheduled time must be **more than 5 minutes in the future**.

```php
use DateTimeImmutable;
use Rayo\SmsSdk\Models\SmsRequest;

$scheduledAt = new DateTimeImmutable('+2 hours');

$response = $client->sendSms(new SmsRequest(
    'MYBRAND',
    'Reminder: Your appointment is tomorrow at 10:00.',
    '306912345678',
    null,
    'appt-1001',
    true,
    false,
    true,
    $scheduledAt
));

if ($response->isScheduled()) {
    echo 'Schedule ID: ' . $response->getSmsRequestScheduleId() . PHP_EOL;
}
```

---

### 3. Deleting a Scheduled SMS (`DELETE /v1/sms_request_schedules/{id}`)

Cancel a scheduled SMS delivery before it fires using the `smsRequestScheduleId` returned when the schedule was created:

```php
$deleteResponse = $client->deleteSchedule('e0b107da-669a-40f5-b4d0-24fc70409307');

if ($deleteResponse->isSuccess()) {
    echo 'Deleted schedule: ' . $deleteResponse->getSmsRequestScheduleId() . PHP_EOL;
}
```

---

### 4. Checking Account Balance (`GET /v1/balance_in_credits`)

Retrieve your remaining balance in credits:

```php
$balance = $client->getBalance();

echo 'Request ID: ' . $balance->getRequestId() . PHP_EOL;
echo 'Remaining Credits: ' . $balance->getCredits() . PHP_EOL;
```

---

### 5. Checking SMS Delivery Status (`GET /v1/check_status`)

Query the delivery status of all outgoing messages belonging to an SMS request by **either** `smsServerRequestId` **or** `clientRequestId` (not both):

```php
use Rayo\SmsSdk\Models\DeliveryStatus;

// Option A: By server-generated request ID
$deliveries = $client->checkStatusByServerRequestId('cc479845-0f15-4ae7-b645-6ef6489868c1');

// Option B: By client-assigned request ID
$deliveries = $client->checkStatusByClientRequestId('order-9543212');

foreach ($deliveries as $item) {
    echo sprintf(
        "Mobile: %s | Status: %s | Delivery ID: %s\n",
        $item->getMobileNumber(),
        $item->getStatus(),
        $item->getInternalSmsOutDeliveryId()
    );

    if ($item->getStatus() === DeliveryStatus::DELIVERED) {
        // Message was delivered to the handset
    }
}
```

#### Possible Delivery Status Values (`Rayo\SmsSdk\Models\DeliveryStatus`)

| Constant | Value | Description |
| --- | --- | --- |
| `DeliveryStatus::NEW` | `new` | Initial status |
| `DeliveryStatus::TEMP_LOCKED` | `temp_locked` | Internal processing status |
| `DeliveryStatus::PROCESSING_ERROR` | `processing_error` | Failed to process the request; will not retry |
| `DeliveryStatus::FAILURE` | `failure` | Retried multiple times and failed |
| `DeliveryStatus::SENT` | `sent` | Message left the network |
| `DeliveryStatus::ERROR` | `error` | Temporary error; will retry |
| `DeliveryStatus::DELIVERED` | `delivered` | Delivered to the handset |
| `DeliveryStatus::NOT_DELIVERED` | `not_delivered` | Sent out, but could not be delivered to the handset |
| `DeliveryStatus::UNKNOWN_DELIVERY` | `unknown_delivery` | Sent out, but no handset confirmation received within 24 hours |

---

### 6. Verifying Webhook Delivery Callbacks

When `callbackWhenProcessedFlag` is set to `true` and a Callback URL is configured under **Actions -> Settings -> Callbacks API**, the server sends signed `GET` or `POST` webhook requests to your endpoint.

Use `WebhookSignatureVerifier` to verify the HMAC-SHA256 signature header in constant time, and `CallbackPayload` to inspect the delivery report:

```php
use Rayo\SmsSdk\Models\CallbackPayload;
use Rayo\SmsSdk\Webhooks\WebhookSignatureVerifier;

$signingSecret     = getenv('SMS_CALLBACK_SIGNING_SECRET');
$incomingSignature = $_SERVER['HTTP_<SIGNATURE_HEADER>'] ?? ''; // e.g. from your provider's signature header
$method            = $_SERVER['REQUEST_METHOD'] ?? 'POST';
$requestPath       = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$dateHeader        = $_SERVER['HTTP_DATE'] ?? '';
$contentType       = $_SERVER['CONTENT_TYPE'] ?? '';
$rawPayload        = strtoupper($method) === 'POST'
    ? (string) file_get_contents('php://input')
    : ($_SERVER['QUERY_STRING'] ?? '');

if (!WebhookSignatureVerifier::verify(
    $incomingSignature,
    $signingSecret,
    $method,
    $requestPath,
    $rawPayload,
    $dateHeader,
    $contentType
)) {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

$callback = strtoupper($method) === 'POST'
    ? CallbackPayload::fromJson($rawPayload)
    : CallbackPayload::fromQueryString($rawPayload);

$status       = $callback->getStatus();
$mobileNumber = $callback->getMobileNumber();
```

You can also verify a PSR-7 `ServerRequestInterface` directly:

```php
$isValid = WebhookSignatureVerifier::verifyPsr7Request(
    $psr7Request,
    $signingSecret,
    '<signature-header-name>'
);
```

---

## Error Handling

All SDK validation errors, network errors, and non-2xx API responses throw `Rayo\SmsSdk\Exceptions\RayoException`:

```php
use Rayo\SmsSdk\Exceptions\RayoException;

try {
    $client->sendSms([
        'fromLabel'      => 'INVALID_SENDER',
        'toMobileNumber' => '306912345678',
        'smsBody'        => 'Test message',
    ]);
} catch (RayoException $e) {
    $httpStatus        = $e->getStatusCode();        // e.g. 401, 404, 422, 500
    $errorMessage      = $e->getErrorMessage();      // Top-level "error" field from response JSON
    $resultCode        = $e->getResultCode();        // "0" on API validation failure
    $resultDescription = $e->getResultDescription(); // Detailed "ERROR: ..." description
    $errorPayload      = $e->getErrorPayload();      // Full decoded JSON array (if any)
}
```

## Testing

Run the PHPUnit test suite:

```bash
composer test
```

## Contributing

Contributions, bug reports, and pull requests are welcome! To contribute enhancements or fixes:

1. Fork the repository and create a branch (`git checkout -b feat/your-feature`).
2. Install dependencies (`composer update`) and make your changes in `src/` with corresponding unit tests in `tests/`.
3. Verify your changes pass validation and tests:
   ```bash
   composer validate --strict
   composer test
   ```
4. Open a Pull Request.

Please read [CONTRIBUTING.md](./CONTRIBUTING.md) for full details on local/Docker setup, project structure, PHP 7.4+ compatibility rules, and PR guidelines.

## Security Best Practices

- Keep your API keys and webhook signing secrets confidential. Never commit them to version control.
- Always use `https://` for your `<base-url>` in production.
- Verify incoming webhook signatures using `WebhookSignatureVerifier` before processing delivery reports.

## License

This SDK is open-source software licensed under the [MIT License](./LICENSE).

