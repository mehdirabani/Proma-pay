<?php

interface PaymentGatewayProviderInterface
{
    public function getId();

    public function getName();

    public function getDescription();

    public function isEnabled();

    public function supportsSinglePayment();

    public function supportsPaymentGroups();

    public function createPayment(array $context);

    public function getRedirectUrl($reference);

    public function parseCallback(array $query);

    public function verifyPayment(array $transaction);

    public function healthCheck();

    public function getSettingsSchema();

    public function getEnvironmentLabel();
}
