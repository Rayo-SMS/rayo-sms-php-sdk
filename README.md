# Rayo SMS Gateway PHP SDK

Official PHP SDK for the [Rayo SMS Gateway](https://rayo.gr). Easily integrate SMS functionalities, fetch credit balances, and track delivery statuses inside your PHP applications or custom e-commerce plugins.

## Installation

Install the package via Composer:

```bash
composer require rayo/rayo-sms-php-sdk
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use Rayo\SmsSdk\Client as RayoClient;
use Rayo\SmsSdk\Exceptions\RayoException;

\$apiKey = 'YOUR_RAYO_API_KEY';
rayo = new RayoClient(apiKey);

try {
    // Send a simulated SMS (does not consume credits)
    response = rayo->sendSms([
        'smsBody'          => 'Hello from Rayo PHP SDK!',
        'msisdn'           => '306912345678', // Country code + mobile number
        'senderId'         => 'RayoSMS',
        'simulateSendFlag' => true
    ]);

    echo "SMS Sent Successfully! Request ID: " . \$response['smsRequestId'];

} catch (RayoException \$e) {
    echo "Error: " . \$e->getMessage();
}
```
