# Tapbuy Forter Adyen Integration for Magento 2

This module integrates Forter fraud prevention with Adyen payment gateway for Magento 2.

## Requirements

- Magento 2.4.x
- PHP 8.1+
- `tapbuy/magento2-forter` module (^1.0)
- `adyen/module-payment` module

## Installation

```bash
composer require tapbuy/magento2-forter-adyen
bin/magento module:enable Tapbuy_ForterAdyen
bin/magento setup:upgrade
bin/magento cache:flush
```

## Structure

- `Gateway/` - Payment gateway integration
- `Model/` - Business logic models
- `etc/` - Module configuration

## Dependencies

- [tapbuy/magento2-forter](../forter) - Base Forter integration module
- [adyen/module-payment](https://github.com/Adyen/adyen-magento2) - Adyen payment module

## Development

### Running Tests

Tests run inside a Docker container that replicates the CI environment (PHP 8.3, Magento 2.4.7-p5). Docker must be running.

**Prerequisites:** clone the following sibling repositories next to this one:

```bash
# From the parent directory
git clone git@github.com:tapbuy/magento-redirect-plugin.git redirect-tracking
git clone git@github.com:tapbuy/magento2-forter.git forter
```

`Adyen/adyen-magento2` is cloned automatically to `~/.tapbuy-ci-cache/` on first run.

**First-time setup:**

```bash
cp auth.json.dist auth.json
# Fill in your repo.magento.com public/private keys in auth.json
```

**Run all unit tests:**

```bash
make test
```

On the first run, the Docker image is built and Magento is installed into a named volume (`tapbuy-magento-2.4.7-p5-php83`). Subsequent runs reuse the cached volume and are fast.

> Do not use `composer test` — it runs PHPUnit without the Magento bootstrap and will fail or produce misleading results.

### Linting

Linting runs PHPMD and PHPCS (Magento2 standard) inside the same Docker container as tests. Docker must be running.

**Run both linters:**

```bash
make lint
```

**Run individually:**

```bash
make phpmd   # PHP Mess Detector
make phpcs   # PHP CodeSniffer (Magento2 standard)
```

Both linters always run when using `make lint`; if either fails, the command exits with a non-zero code.
