<?php
/**
 * This file provides a list of the example implementations.
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
 * @package  heidelpay/mgw_sdk/examples
 */

/** Require the constants of this example */
require_once __DIR__ . '/Constants.php';

/** Require the composer autoloader file */
require_once __DIR__ . '/../../../autoload.php';
?>

<!DOCTYPE html>
<html>
    <?php include './assets/partials/_indexPage_html.php'; ?>

    <body>
        <div class="ui container segment">
            <h2 class="ui header">
                <i class="shopping cart icon"></i>
                <div class="content">
                    Payment Implentation Examples
                    <div class="sub header">Choose the Payment Type you want to evaluate...</div>
                </div>
            </h2>
            <ul style="list-style: none;">
                <li><i class="credit card icon"></i><a href="CreditCard/">CreditCard</a></li>
                <li><i class="credit card outline icon"></i>CreditCard with 3D <i>(not available)</i></li>
                <li><i class="paypal icon"></i><a href="Paypal/">PayPal</a></li>
                <li><i class="credit card icon"></i>Sepa Direct Debit (guaranteed) <i>(not available)</i></li>
            </ul>
        </div>
    </body>

</html>