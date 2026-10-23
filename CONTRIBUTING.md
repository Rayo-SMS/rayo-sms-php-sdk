# Contributing to Rayo SMS PHP SDK

Thank you for your interest in contributing to the **Rayo SMS PHP SDK**! We welcome bug reports, enhancements, documentation improvements, and pull requests from the community.

## Table of Contents

- [Getting Started](#getting-started)
- [Development Environment](#development-environment)
  - [Option 1: Local PHP & Composer](#option-1-local-php--composer)
  - [Option 2: Using Docker](#option-2-using-docker)
- [Project Structure](#project-structure)
- [Coding Standards & Design Guidelines](#coding-standards--design-guidelines)
- [Running Tests](#running-tests)
- [Opening a Pull Request](#opening-a-pull-request)
- [Reporting Bugs or Security Issues](#reporting-bugs-or-security-issues)

---

## Getting Started

1. **Fork** the repository on GitHub: [`https://github.com/Rayo-SMS/rayo-sms-php-sdk`](https://github.com/Rayo-SMS/rayo-sms-php-sdk)
2. **Clone** your fork locally:
   ```bash
   git clone https://github.com/<your-username>/rayo-sms-php-sdk.git
   cd rayo-sms-php-sdk
   ```
3. **Create a feature or bugfix branch** from the default branch:
   ```bash
   git checkout -b feat/your-feature-name
   ```

---

## Development Environment

### Option 1: Local PHP & Composer

Ensure you have:
- PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || ~8.5.12` (`8.5.12` recommended for local development)
- `ext-json` and `ext-mbstring`
- [Composer](https://getcomposer.org/) v2

Install dependencies:

```bash
composer update
```

### Option 2: Using Docker

If you do not have PHP or Composer installed on your host machine, you can run Composer and PHPUnit directly using the official `composer:2` Docker image:

```bash
# Install dependencies
docker run --rm --network host -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app composer:2 composer update

# Run the test suite
docker run --rm --network host -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app composer:2 vendor/bin/phpunit
```

---

## Project Structure

```text
.
├── src/
│   ├── Client.php                         # Main HTTP API client
│   ├── Exceptions/
│   │   └── RayoException.php              # Unified SDK exception
│   ├── Models/
│   │   ├── BalanceResponse.php            # GET /v1/balance_in_credits response model
│   │   ├── CallbackPayload.php            # Delivery status webhook payload model
│   │   ├── DeleteScheduleResponse.php     # DELETE /v1/sms_request_schedules/{id} response model
│   │   ├── DeliveryStatus.php             # Delivery status constants & helpers
│   │   ├── SmsDeliveryStatusItem.php      # GET /v1/check_status item model
│   │   ├── SmsRequest.php                 # POST /v1/send_sms request payload model
│   │   └── SmsResponse.php                # POST /v1/send_sms response model
│   └── Webhooks/
│       └── WebhookSignatureVerifier.php   # HMAC-SHA256 webhook signature verifier
└── tests/
    ├── ClientTest.php
    ├── ModelsTest.php
    └── WebhookSignatureVerifierTest.php
```

---

## Coding Standards & Design Guidelines

When contributing code, please follow these rules:

1. **PHP 8.2 – 8.5+ Compatibility**:
   - The library supports patched PHP `8.2` through `8.5` releases (`~8.2.35 || ~8.3.36 || ~8.4.27 || ~8.5.12`, tested on PHP `8.5.12`). Keep `src/` compatible with PHP `8.2+`.
   - Include `declare(strict_types=1);` at the top of every PHP file.
2. **PSR-4 & PSR-12**:
   - Follow [PSR-4](https://www.php-fig.org/psr/psr-4/) autoloading under the `Rayo\SmsSdk\` namespace (`src/`) and `Rayo\SmsSdk\Tests\` (`tests/`).
   - Format code according to [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style.
3. **Domain-Agnostic Placeholders**:
   - Never hardcode specific business domain names in code, tests, or documentation.
   - Use `https://<base-url>` in documentation and `.example.invalid` (RFC 2606) in tests.
4. **No Real Credentials or Personal Data**:
   - Never include real API keys, signing secrets, or real phone numbers in code, tests, or commit history. Use dummy values (e.g. `00000000-0000-0000-0000-000000000000` and `306900000001`) in unit tests.
5. **Backwards Compatibility**:
   - Response models implement `ArrayAccess` and `JsonSerializable` alongside typed getters. Preserve this contract when adding or modifying response models.

---

## Running Tests

All pull requests must include unit tests covering any new functionality or bug fixes. Tests use Guzzle's `MockHandler` so no live network calls are made.

Run the test suite and validate `composer.json`:

```bash
composer validate --strict
composer test
```

---

## Opening a Pull Request

1. Ensure all existing and new tests pass (`composer test`).
2. Ensure `composer validate --strict` passes with no warnings or errors.
3. Update `README.md` if your change introduces new public methods, models, or options.
4. Push your branch to your fork and open a Pull Request against `Rayo-SMS/rayo-sms-php-sdk`.
5. Fill out the Pull Request template clearly describing **what** changed and **why**.

---

## Reporting Bugs or Security Issues

- **Bugs & Enhancements**: Open a GitHub Issue describing the steps to reproduce, PHP version, expected behavior, and actual behavior.
- **Security Vulnerabilities**: Please do **not** post sensitive security vulnerabilities in public issues. Report security concerns privately to the repository maintainers via GitHub Security Advisories.
