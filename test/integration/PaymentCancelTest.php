<?php
/**
 * This class defines integration tests to verify functionality of the Payment charge method.
 *
 * Copyright (C) 2019 heidelpay GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link  https://docs.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.com>
 *
 * @package  heidelpayPHP/test/integration
 */
namespace heidelpayPHP\test\integration;

use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\PaymentTypes\Invoice;
use heidelpayPHP\test\BasePaymentTest;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use RuntimeException;

class PaymentCancelTest extends BasePaymentTest
{
    //<editor-fold desc="Tests">

    /**
     * Verify full cancel on authorize returns first cancellation if already cancelled.
     *
     * @test
     *
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnAuthorizeShouldReturnExistingCancellationIfAlreadyCanceled()
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $cancellations = $payment->cancelPayment();
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 0.00, 0.0);
        $this->assertCount(1, $cancellations);

        $this->assertCount(0, $payment->cancelPayment());
    }

    /**
     * Return first cancel if charge is already fully cancelled.
     *
     * @test
     *
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function doubleCancelOnChargeShouldReturnEmptyArray()
    {
        $charge = $this->createCharge(123.44);
        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 123.44, 123.44, 0.0);

        $cancellations = $payment->cancelPayment();
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 123.44, 123.44);
        $this->assertCount(1, $cancellations);

        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $newCancellations = $payment->cancelPayment();
        $this->assertAmounts($payment, 0.0, 0.0, 123.44, 123.44);
        $this->assertCount(0, $newCancellations);
    }

    /**
     * Verify full cancel on charge.
     * PHPLIB-228 - Case 1
     *
     * @test
     *
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnChargeShouldBePossible()
    {
        $charge = $this->createCharge(123.44);
        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $this->assertAmounts($payment, 0.0, 123.44, 123.44, 0.0);

        $payment->cancelPayment();
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 123.44, 123.44);
    }

    /**
     * Verify full cancel on multiple charges.
     * PHPLIB-228 - Case 2
     *
     * @test
     *
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnPaymentWithAuthorizeAndMultipleChargesShouldBePossible()
    {
        $authorization = $this->createCardAuthorization(123.44);
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 123.44, 0.0, 123.44, 0.0);

        $payment->charge(100.44);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 23.0, 100.44, 123.44, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->charge(23.00);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 123.44, 123.44, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $cancellations = $payment->cancelPayment();
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 123.44, 123.44);
        $this->assertCount(2, $cancellations);
    }

    /**
     * Verify partial cancel on charge.
     * PHPLIB-228 - Case 3
     *
     * @test
     *
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function partialCancelOnSingleChargeShouldBePossible()
    {
        $charge = $this->createCharge(222.33);
        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 222.33, 222.33, 0.0);

        $payment->cancelPayment(123.12);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 99.21, 222.33, 123.12);

        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $payment->cancelPayment(99.21);
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 222.33, 222.33);
    }

    /**
     * Verify partial cancel on multiple charges (cancel < last charge).
     * PHPLIB-228 - Case 4 + 5
     *
     * @test
     * @dataProvider partCancelDataProvider
     *
     * @param $amount
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function partialCancelOnMultipleChargedAuthorizationAmountSmallerThenAuthorize($amount)
    {
        $authorizeAmount = 123.44;
        $authorization = $this->createCardAuthorization($authorizeAmount);
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());

        $payment->charge(100.44);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 23.0, 100.44, $authorizeAmount, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->charge(23.00);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, $authorizeAmount, $authorizeAmount, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment($amount);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, $authorizeAmount - $amount, $authorizeAmount, $amount);
    }

    /**
     * Verify full cancel on authorize.
     * PHPLIB-228 - Case 6
     *
     * @test
     * @dataProvider fullCancelDataProvider
     *
     * @param float $amount
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnAuthorizeShouldBePossible($amount)
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->cancelPayment($amount);
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 0.0, 0.0);
    }

    /**
     * Verify partial cancel on authorize.
     * PHPLIB-228 - Case 7
     *
     * @test
     *
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnPartCanceledAuthorizeShouldBePossible()
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->cancelPayment(10.0);
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 90.0, 0.0, 90.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment(10.0);
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 80.0, 0.0, 80.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment();
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 0.0, 0.0);
    }

    /**
     * Verify full cancel on fully charged authorize.
     * PHPLIB-228 - Case 8
     *
     * @test
     * @dataProvider fullCancelDataProvider
     *
     * @param float $amount The amount to be cancelled.
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnFullyChargedAuthorizeShouldBePossible($amount)
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->charge();
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 100.0, 100.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment($amount);
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 100.0, 100.0);
    }

    /**
     * Verify full cancel on partly charged authorize.
     * PHPLIB-228 - Case 9
     *
     * @test
     * @dataProvider fullCancelDataProvider
     *
     * @param $amount
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnPartlyChargedAuthorizeShouldBePossible($amount)
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->charge(50.0);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 50.0, 50.0, 100.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment($amount);
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 50.0, 50.0);
    }

    /**
     * Verify part cancel on umcharged authorize.
     * PHPLIB-228 - Case 10
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function partCancelOnUnchargedAuthorizeShouldBePossible()
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->cancelPayment(50.0);
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 50.0, 0.0, 50.0, 0.0);
    }

    /**
     * Verify part cancel on partly charged authorize with cancel amount lt charged amount.
     * PHPLIB-228 - Case 11
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function partCancelOnPartlyChargedAuthorizeWithAmountLtChargedShouldBePossible()
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->charge(25.0);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 75.0, 25.0, 100.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment(20.0);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 55.0, 25.0, 80.0, 0.0);
    }

    /**
     * Verify part cancel on partly charged authorize with cancel amount gt charged amount.
     * PHPLIB-228 - Case 12
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function partCancelOnPartlyChargedAuthorizeWithAmountGtChargedShouldBePossible()
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->charge(40.0);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 60.0, 40.0, 100.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment(80.0);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 20.0, 40.0, 20.0);
    }

    /**
     * Verify full cancel on initial iv charge (reversal)
     * PHPLIB-228 - Case 13
     *
     * @test
     * @dataProvider fullCancelDataProvider
     *
     * @param float $amount
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function fullCancelOnInitialInvoiceChargeShouldBePossible($amount)
    {
        /** @var Invoice $invoice */
        $invoice = $this->heidelpay->createPaymentType(new Invoice());
        $charge = $invoice->charge(100.0, 'EUR', self::RETURN_URL);
        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->cancelPayment($amount);
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 0.0, 0.0);
    }

    /**
     * Verify part cancel on initial iv charge (reversal)
     * PHPLIB-228 - Case 14
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function partCancelOnInitialInvoiceChargeShouldBePossible()
    {
        /** @var Invoice $invoice */
        $invoice = $this->heidelpay->createPaymentType(new Invoice());
        $charge = $invoice->charge(100.0, 'EUR', self::RETURN_URL);
        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->cancelPayment(50.0);
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 50.0, 0.0, 50.0, 0.0);
    }

    /**
     * Verify cancelling more than was charged.
     * PHPLIB-228 - Case 15
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function cancelMoreThanWasCharged()
    {
        $charge = $this->createCharge(50.0);
        $payment = $this->heidelpay->fetchPayment($charge->getPaymentId());
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 50.0, 50.0, 0.0);

        $payment->cancelPayment(100.0);
        $this->assertTrue($payment->isCanceled());
        $this->assertAmounts($payment, 0.0, 0.0, 50.0, 50.0);
    }

    /**
     * Verify second cancel on partly cancelled charge.
     * PHPLIB-228 - Case 16
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws HeidelpayApiException
     * @throws RuntimeException
     */
    public function secondCancelExceedsRemainderOfPartlyCancelledCharge()
    {
        $authorization = $this->createCardAuthorization();
        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $this->assertTrue($payment->isPending());
        $this->assertAmounts($payment, 100.0, 0.0, 100.0, 0.0);

        $payment->charge(50.0);
        $this->assertTrue($payment->isPartlyPaid());
        $this->assertAmounts($payment, 50.0, 50.0, 100.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->charge(50.0);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 100.0, 100.0, 0.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment(40.0);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 60.0, 100.0, 40.0);

        $payment = $this->heidelpay->fetchPayment($authorization->getPaymentId());
        $payment->cancelPayment(30.0);
        $this->assertTrue($payment->isCompleted());
        $this->assertAmounts($payment, 0.0, 30.0, 100.0, 70.0);
    }

    //</editor-fold>

    //<editor-fold desc="Data Providers">

    /**
     * @return array
     */
    public function partCancelDataProvider(): array
    {
        return [
            'cancel amount lt last charge' => [20],
            'cancel amount gt last charge' => [40]
        ];
    }

    /**
     * @return array
     */
    public function fullCancelDataProvider(): array
    {
        return [
            'no amount given' => [null],
            'amount given' => [100.0]
        ];
    }

    //</editor-fold>
}
