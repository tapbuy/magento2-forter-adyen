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
