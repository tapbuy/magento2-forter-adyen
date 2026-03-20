<?php

declare(strict_types=1);

namespace Tapbuy\ForterAdyen\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tapbuy\ForterAdyen\Model\PaymentMethodProvider;

class PaymentMethodProviderTest extends TestCase
{
    public function testGetPaymentMethodsReturnsAdyenCc(): void
    {
        $provider = new PaymentMethodProvider();
        $methods = $provider->getPaymentMethods();

        $this->assertIsArray($methods);
        $this->assertCount(1, $methods);
        $this->assertSame('adyen_cc', $methods[0]);
    }
}
