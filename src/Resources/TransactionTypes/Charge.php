<?php
/**
 * Description
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.de>
 *
 * @package  heidelpay/${Package}
 */
namespace heidelpay\NmgPhpSdk\Resources\TransactionTypes;

use heidelpay\NmgPhpSdk\Resources\Payment;
use heidelpay\NmgPhpSdk\Exceptions\MissingResourceException;
use heidelpay\NmgPhpSdk\Interfaces\HeidelpayResourceInterface;
use heidelpay\NmgPhpSdk\Interfaces\PaymentTypeInterface;
use heidelpay\NmgPhpSdk\Traits\HasCancellationsTrait;
use heidelpay\NmgPhpSdk\Traits\HasValueHelper;

class Charge extends AbstractTransactionType
{
    use HasCancellationsTrait;
    use HasValueHelper;

    /** @var float $amount */
    protected $amount;

    /** @var string $currency */
    protected $currency;

    /** @var string $returnUrl */
    protected $returnUrl;

    /** @var string $uniqueId */
    private $uniqueId;

    /** @var string $redirectUrl */
    private $redirectUrl;

    /**
     * Authorization constructor.
     * @param float $amount
     * @param string $currency
     * @param string $returnUrl
     */
    public function __construct($amount, $currency, $returnUrl)
    {
        $this->setAmount($amount);
        $this->setCurrency($currency);
        $this->setReturnUrl($returnUrl);

        parent::__construct(null);
    }

    //<editor-fold desc="Setters/Getters">
    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     * @return HeidelpayResourceInterface
     */
    public function setAmount(float $amount): HeidelpayResourceInterface
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     * @return HeidelpayResourceInterface
     */
    public function setCurrency($currency): HeidelpayResourceInterface
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    /**
     * @param string $returnUrl
     * @return HeidelpayResourceInterface
     */
    public function setReturnUrl($returnUrl): HeidelpayResourceInterface
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @param string $uniqueId
     * @return HeidelpayResourceInterface
     */
    public function setUniqueId(string $uniqueId): HeidelpayResourceInterface
    {
        $this->uniqueId = $uniqueId;
        return $this;
    }

    /**
     * Returns true if the charge is fully canceled.
     * todo: canceled as state?
     *
     * @return bool
     */
    public function isCanceled(): bool
    {
        $canceledAmount = 0.0;

        /** @var Cancellation $cancellation */
        foreach ($this->cancellations as $cancellation) {
            $canceledAmount += $cancellation->getAmount();
        }

        return $this->amountIsGreaterThanOrEqual($canceledAmount, $this->amount);
    }

    /**
     * @return string|null
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @param string|null $redirectUrl
     * @return Charge
     */
    public function setRedirectUrl($redirectUrl): Charge
    {
        $this->redirectUrl = $redirectUrl;
        return $this;
    }
    //</editor-fold>

    //<editor-fold desc="Overridable Methods">
    /**
     * {@inheritDoc}
     */
    public function getResourcePath()
    {
        return 'charges';
    }

    /**
     * {@inheritDoc}
     */
    public function getLinkedResources(): array
    {
        /** @var Payment $payment */
        $payment = $this->getPayment();
        $paymentType = $payment ? $payment->getPaymentType() : null;
        if (!$paymentType instanceof PaymentTypeInterface) {
            throw new MissingResourceException();
        }

        return [
            'customer'=> $payment->getCustomer(),
            'type' => $paymentType
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function handleResponse(\stdClass $response)
    {
        /** @var Payment $payment */
        $payment = $this->getPayment();
        if (isset($response->resources->paymentId)) {
            $payment->setId($response->resources->paymentId);
        }

        if (isset($response->redirectUrl)) {
            // todo: maybe just one of these applies depending on answer #10 https://heidelpay.atlassian.net/wiki/spaces/ID/pages/359727164/Q+and+A+Suggestions
            $this->setRedirectUrl($response->redirectUrl);
            $payment->setRedirectUrl($response->redirectUrl);
        }

        parent::handleResponse($response);
    }
    //</editor-fold>

    /**
     * Full cancel of this authorization.
     * Returns the last cancellation object if charge is already canceled.
     * Creates and returns new cancellation object otherwise.
     *
     * @param float $amount
     * @return Cancellation
     */
    public function cancel($amount = null): Cancellation
    {
        if ($this->isCanceled()) {
            return end($this->cancellations);
        }

        $cancellation = new Cancellation($amount);
        $this->addCancellation($cancellation);
        $cancellation->setParentResource($this);
        $cancellation->setPayment($this->getPayment());
        $cancellation->create();

        return $cancellation;
    }
}