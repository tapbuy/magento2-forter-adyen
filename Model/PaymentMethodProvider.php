<?php

declare(strict_types=1);

namespace Tapbuy\ForterAdyen\Model;

use Adyen\Payment\Helper\PaymentMethods;
use Tapbuy\Forter\Api\PaymentMethodProviderInterface;

/**
 * Adyen payment method provider for Forter integration.
 */
class PaymentMethodProvider implements PaymentMethodProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getPaymentMethods(): array
    {
        return [
            PaymentMethods::ADYEN_CC,
        ];
    }
}
