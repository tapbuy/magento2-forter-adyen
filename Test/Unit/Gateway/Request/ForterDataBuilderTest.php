<?php

declare(strict_types=1);

namespace Tapbuy\ForterAdyen\Test\Unit\Gateway\Request;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Api\PaymentMethodProviderInterface;
use Tapbuy\Forter\Observer\OrderValidation\PaymentPlaceStart;
use Tapbuy\ForterAdyen\Gateway\Request\ForterDataBuilder;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class ForterDataBuilderTest extends TestCase
{
    private ForterDataBuilder $builder;
    private PaymentMethodProviderInterface&MockObject $paymentMethodProvider;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paymentMethodProvider->method('getPaymentMethods')
            ->willReturn(['adyen_cc']);

        $this->builder = new ForterDataBuilder(
            $this->paymentMethodProvider,
            $this->logger
        );
    }

    public function testBuildReturnsEmptyArrayWhenNoForterDecision(): void
    {
        $buildSubject = $this->createBuildSubject(null, 'adyen_cc');

        $this->assertSame([], $this->builder->build($buildSubject));
    }

    public function testBuildReturnsEmptyArrayWhenPaymentMethodNotSupported(): void
    {
        $buildSubject = $this->createBuildSubject('approve', 'checkmo');

        $this->assertSame([], $this->builder->build($buildSubject));
    }

    public function testBuildReturnsEmptyArrayOnDeclineWithout3dsChallenge(): void
    {
        $buildSubject = $this->createBuildSubject('decline', 'adyen_cc', []);

        $result = $this->builder->build($buildSubject);

        $this->assertSame([], $result);
    }

    public function testBuildProcessesApproveDecisionWith3dsChallenge(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['VERIFICATION_REQUIRED_3DS_CHALLENGE']
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame(
            CheckoutDataInterface::THREE_DS_AUTH_ALWAYS,
            $result['body']['authenticationData']['attemptAuthentication']
        );
    }

    public function testBuildProcesses3dsChallengeEvenOnDecline(): void
    {
        $buildSubject = $this->createBuildSubject(
            'decline',
            'adyen_cc',
            ['VERIFICATION_REQUIRED_3DS_CHALLENGE']
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame(
            CheckoutDataInterface::THREE_DS_AUTH_ALWAYS,
            $result['body']['authenticationData']['attemptAuthentication']
        );
    }

    public function testBuildSetsExemptionForTraRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXEMPTION_TRA']
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('transactionRiskAnalysis', $result['body']['additionalData']['scaExemption']);
    }

    public function testBuildSetsExemptionForLowValueRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXEMPTION_LOW_VALUE']
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('lowValue', $result['body']['additionalData']['scaExemption']);
    }

    public function testBuildSetsExemptionForCorpRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXEMPTION_CORP']
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('secureCorporate', $result['body']['additionalData']['scaExemption']);
    }

    public function testBuildSetsExemptionForTrustedBeneficiaryRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXEMPTION_TRUSTED_BENEFICIARY']
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('trustedBeneficiary', $result['body']['additionalData']['scaExemption']);
    }

    public function testBuildSetsExclusionAuthForAnonymousRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXCLUSION_ANONYMOUS'],
            'preferNo'
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('preferNo', $result['body']['authenticationData']['attemptAuthentication']);
    }

    public function testBuildSetsExclusionAuthForMotoRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXCLUSION_MOTO'],
            'preferNo'
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('preferNo', $result['body']['authenticationData']['attemptAuthentication']);
    }

    public function testBuildSetsExclusionAuthForOneLegOutRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXCLUSION_ONE_LEG_OUT'],
            'preferNo'
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('preferNo', $result['body']['authenticationData']['attemptAuthentication']);
    }

    public function testBuildSetsExclusionAuthForMitRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXCLUSION_MIT'],
            'preferNo'
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('preferNo', $result['body']['authenticationData']['attemptAuthentication']);
    }

    public function testBuildUsesExclusionConfigForEmptyRecommendations(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            [],
            'preferNo'
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('preferNo', $result['body']['authenticationData']['attemptAuthentication']);
    }

    public function testBuildUsesExclusionConfigForEmptyStringRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            [''],
            'preferNo'
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame('preferNo', $result['body']['authenticationData']['attemptAuthentication']);
    }

    public function testBuildLogsWarningForUnknownRecommendation(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['UNKNOWN_RECOMMENDATION']
        );

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                'Unknown recommendation received from Forter',
                ['recommendation' => 'UNKNOWN_RECOMMENDATION']
            );

        $result = $this->builder->build($buildSubject);

        $this->assertSame([], $result['body'] ?? []);
    }

    public function testBuildLogsWarningAndReturnsEmptyForMultipleRecommendations(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXEMPTION_TRA', 'VERIFICATION_REQUIRED_3DS_CHALLENGE']
        );

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                'More than one Forter recommendation received',
                $this->callback(fn($ctx) => $ctx['count'] === 2)
            );

        $result = $this->builder->build($buildSubject);

        $this->assertSame([], $result['body'] ?? []);
    }

    public function testBuildDefaultsThreeDsAuthToAlwaysWhenNotInPayment(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            [],
            null // no 3ds config in payment, should default to THREE_DS_AUTH_ALWAYS
        );

        $result = $this->builder->build($buildSubject);

        $this->assertSame(
            CheckoutDataInterface::THREE_DS_AUTH_ALWAYS,
            $result['body']['authenticationData']['attemptAuthentication']
        );
    }

    public function testBuildCatchesThrowableAndReturnsEmptyArray(): void
    {
        // Create buildSubject that will throw during SubjectReader::readPayment
        $buildSubject = [];

        $this->logger->expects($this->once())->method('logException');

        $result = $this->builder->build($buildSubject);

        $this->assertSame([], $result);
    }

    public function testBuildLogsDebugOnSuccessfulProcessing(): void
    {
        $buildSubject = $this->createBuildSubject(
            'approve',
            'adyen_cc',
            ['REQUEST_SCA_EXEMPTION_TRA']
        );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Forter-Adyen: Built Forter data for Adyen request',
                $this->callback(fn($ctx) => isset($ctx['order_id']) && isset($ctx['forter_decision']))
            );

        $this->builder->build($buildSubject);
    }

    /**
     * @param string|null $forterDecision
     * @param string $paymentMethod
     * @param array|null $recommendations
     * @param string|null $threeDsAuthOnExclusion
     * @return array
     */
    private function createBuildSubject(
        ?string $forterDecision,
        string $paymentMethod = 'adyen_cc',
        ?array $recommendations = null,
        ?string $threeDsAuthOnExclusion = null
    ): array {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            function (string $key) use ($forterDecision, $recommendations, $threeDsAuthOnExclusion) {
                return match ($key) {
                    PaymentPlaceStart::PRE_DECISION_KEY => $forterDecision,
                    PaymentPlaceStart::PRE_RECOMMENDATIONS_KEY => $recommendations,
                    PaymentPlaceStart::THREE_DS_AUTH_ON_EXCLUSION_KEY => $threeDsAuthOnExclusion,
                    default => null,
                };
            }
        );

        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getOrderIncrementId')->willReturn('100000001');

        $paymentDO = $this->createMock(PaymentDataObject::class);
        $paymentDO->method('getPayment')->willReturn($payment);
        $paymentDO->method('getOrder')->willReturn($order);

        return ['payment' => $paymentDO];
    }
}
