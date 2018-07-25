<?php
/*
 * ***********************************************************************
 Copyright [2015] [PagSeguro Internet Ltda.]

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 * ***********************************************************************
 */

require_once "../../PagSeguroLibrary/PagSeguroLibrary.php";

/**
 * Class with a main method to illustrate the usage of the domain class PagSeguroPaymentRequest
 */
class CreatePreApproval
{

    public static function main()
    {
       // Instantiate a new pre-approval request
        $preApprovalRequest = new PagSeguroPreApprovalRequest();

        // Set the currency
        $preApprovalRequest->setCurrency("BRL");

        // Set a reference code for this payment request. It is useful to identify this payment
        // in future notifications.
        $preApprovalRequest->setReference("REF123");

        // Set shipping information for this payment request
        $sedexCode = PagSeguroShippingType::getCodeByType('SEDEX');
        $preApprovalRequest->setShippingType($sedexCode);
        $preApprovalRequest->setShippingAddress(
            '01452002',
            'Av. Brig. Faria Lima',
            '1384',
            'apto. 114',
            'Jardim Paulistano',
            'São Paulo',
            'SP',
            'BRA'
        );

        // Set your customer information.
        $preApprovalRequest->setSender(
            'João Comprador',
            'email@comprador.com.br',
            '11',
            '56273440',
            'CPF',
            '156.009.442-76'
        );

        /***
         * Pre Approval information
         */
        $preApprovalRequest->setPreApprovalCharge('manual');
        $preApprovalRequest->setPreApprovalName("Seguro contra roubo do Notebook Prata");
        $preApprovalRequest->setPreApprovalDetails("Todo dia 30 será cobrado o valor de R100,00 referente ao seguro contra
            roubo do Notebook Prata.");
        $preApprovalRequest->setPreApprovalAmountPerPayment('100.00');
        $preApprovalRequest->setPreApprovalMaxAmountPerPeriod('200.00');
        $preApprovalRequest->setPreApprovalPeriod('Monthly');
        $preApprovalRequest->setPreApprovalMaxTotalAmount('2400.00');
        $preApprovalRequest->setPreApprovalInitialDate('2015-09-10T00:00:00');
        $preApprovalRequest->setPreApprovalFinalDate('2017-09-07T00:00:00');
        $preApprovalRequest->setRedirectURL("http://www.lojateste.com.br/redirect");
        $preApprovalRequest->setReviewURL("http://www.lojateste.com.br/review");

        try {

            /*
             * #### Credentials #####
             * Replace the parameters below with your credentials
             * You can also get your credentials from a config file. See an example:
             * $credentials = PagSeguroConfig::getAccountCredentials();
             */

            // seller authentication
            $credentials = new PagSeguroAccountCredentials("vendedor@lojamodelo.com.br",
                "E231B2C9BCC8474DA2E260B6C8CF60D3");

            // application authentication
            //$credentials = PagSeguroConfig::getApplicationCredentials();

            //$credentials->setAuthorizationCode("E231B2C9BCC8474DA2E260B6C8CF60D3");

            // Register this payment request in PagSeguro to obtain the payment URL to redirect your customer.
            $url = $preApprovalRequest->register($credentials);

            self::printPreApprovalUrl($url);

        } catch (PagSeguroServiceException $e) {
            die($e->getMessage());
        }
    }

    public static function printPreApprovalUrl($url)
    {
        if ($url) {
            echo "<h2>Criando requisi&ccedil;&atilde;o de pagamento recorrente</h2>";
            echo "<p><strong>C&oacute;digo: </strong>".$url['code']."</p>";
            echo "<p><strong>URL do pagamento: </strong>".$url['checkoutUrl']."</p>";
            echo "<p><a title='URL do pagamento' href='".$url['checkoutUrl']."'>Ir para URL do pagamento.</a></p>";
        }
    }
}

CreatePreApproval::main();
