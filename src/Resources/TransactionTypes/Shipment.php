<?php
/**
 * This represents the shipment transaction.
 *
 * Copyright (C) 2018 Heidelpay GmbH
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
 * @link  http://dev.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.com>
 *
 * @package  heidelpay/mgw_sdk/transaction_types
 */
namespace heidelpay\MgwPhpSdk\Resources\TransactionTypes;

class Shipment extends AbstractTransactionType
{
    /** @var float $amount */
    protected $amount;

    //<editor-fold desc="Getters/Setters">

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     *
     * @return Shipment
     */
    public function setAmount(float $amount): Shipment
    {
        $this->amount = $amount;
        return $this;
    }

    //</editor-fold>

    //<editor-fold desc="Overridable methods">
    protected function getResourcePath()
    {
        return 'shipments';
    }

    //</editor-fold>
}