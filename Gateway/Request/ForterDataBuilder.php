<?php

declare(strict_types=1);

namespace Tapbuy\ForterAdyen\Gateway\Request;

use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Api\PaymentMethodProviderInterface;
use Tapbuy\Forter\Observer\OrderValidation\PaymentPlaceStart;
use Exception;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Psr\Log\LoggerInterface;

class ForterDataBuilder implements BuilderInterface
{
    private const REQUIRED_3DS_CHALLENGE = 'VERIFICATION_REQUIRED_3DS_CHALLENGE';

    private const EXEMPTION_MAP = [
        'REQUEST_SCA_EXEMPTION_TRA' => 'transactionRiskAnalysis',
        'REQUEST_SCA_EXEMPTION_LOW_VALUE' => 'lowValue',
        'REQUEST_SCA_EXEMPTION_CORP' => 'secureCorporate',
        'REQUEST_SCA_EXEMPTION_TRUSTED_BENEFICIARY' => 'trustedBeneficiary',
    ];

    private const EXCLUSIONS = [
        'REQUEST_SCA_EXCLUSION_ANONYMOUS',
        'REQUEST_SCA_EXCLUSION_MOTO',
        'REQUEST_SCA_EXCLUSION_ONE_LEG_OUT',
        'REQUEST_SCA_EXCLUSION_MIT',
    ];

    /**
     * @param PaymentMethodProviderInterface $paymentMethodProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaymentMethodProviderInterface $paymentMethodProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Add data to Adyen payment authorization request based on Forter decision.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $request = [];

        try {
            /** @var PaymentDataObject $paymentDataObject */
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();

            // Only process if Forter decision is present (indicates Forter is enabled for this order)
            $forterDecision = $payment->getAdditionalInformation(PaymentPlaceStart::PRE_DECISION_KEY);
            if (empty($forterDecision)) {
                return $request;
            }

            if (!in_array($payment->getMethod(), $this->paymentMethodProvider->getPaymentMethods(), true)) {
                return $request;
            }

            $recommendations = $payment->getAdditionalInformation(PaymentPlaceStart::PRE_RECOMMENDATIONS_KEY) ?? [];

            // Get 3DS config from payment additional info (stored by PaymentPlaceStart from tapbuy-api response)
            $threeDsAuthOnExclusion = $payment->getAdditionalInformation(PaymentPlaceStart::THREE_DS_AUTH_ON_EXCLUSION_KEY)
                ?? CheckoutDataInterface::THREE_DS_AUTH_ALWAYS;

            // Process recommendations on "approve" decision
            // OR when Forter recommends 3DS challenge (even on "decline" - give customer a chance to verify)
            if (
                $forterDecision === CheckoutDataInterface::ACTION_APPROVE
                || in_array(self::REQUIRED_3DS_CHALLENGE, $recommendations, true)
            ) {
                $request['body'] = $this->buildForterData($recommendations, $threeDsAuthOnExclusion);
            }

            return $request;
        } catch (Exception $e) {
            $this->logger->error(
                'Error during Adyen transaction building: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $request;
    }

    /**
     * Build Forter data.
     *
     * @param array $recommendations
     * @param string $threeDsAuthOnExclusion
     * @return array
     */
    private function buildForterData(array $recommendations, string $threeDsAuthOnExclusion): array
    {
        $requestBody = [];

        // No more than one recommendation is expected from Forter.
        if (count($recommendations) > 1) {
            $this->logger->warning(
                'More than one Forter recommendation received',
                [
                    'count' => count($recommendations),
                    'recommendations' => $recommendations
                ]
            );

            return $requestBody;
        }

        if (!isset($recommendations[0])) {
            // Empty recommendations - use exclusion config from tapbuy-api
            $requestBody['authenticationData']['attemptAuthentication'] = $threeDsAuthOnExclusion;
            return $requestBody;
        }

        $recommendation = $recommendations[0];
        if ($recommendation === self::REQUIRED_3DS_CHALLENGE) {
            $requestBody['authenticationData']['attemptAuthentication'] = CheckoutDataInterface::THREE_DS_AUTH_ALWAYS;
        } elseif (array_key_exists($recommendation, self::EXEMPTION_MAP)) {
            $requestBody['additionalData']['scaExemption'] = self::EXEMPTION_MAP[$recommendation];
        } elseif (in_array($recommendation, self::EXCLUSIONS, true) || $recommendation === '') {
            // Exclusion recommendation - use exclusion config from tapbuy-api
            $requestBody['authenticationData']['attemptAuthentication'] = $threeDsAuthOnExclusion;
        } else {
            $this->logger->warning(
                'Unknown recommendation received from Forter',
                ['recommendation' => $recommendation]
            );
        }

        return $requestBody;
    }
}
