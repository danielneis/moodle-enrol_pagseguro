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
 * Class with a main method to illustrate the usage of the domain class PagSeguroPreApprovalCharge
 */
class ChargePreApproval
{

    public static function main()
    {

        $preApprovalCharge = new PagSeguroPreApprovalCharge();
        $preApprovalCharge->setReference("REF123-1");
        $preApprovalCharge->setPreApprovalCode('230B933B11116E66645FFF8DEAB6CF11');
        $preApprovalCharge->addItem('0001', 'Parcela 1 do Seguro para Notebook', 1, 100.00);


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
            $response = $preApprovalCharge->register($credentials);

            self::printResponse($response);

        } catch (PagSeguroServiceException $e) {
            die($e->getMessage());
        }
    }

    public static function printResponse($response)
    {

        if ($response) {
            echo utf8_decode("<h2>Response:</h2>");
            echo "<p> Transaction code: ".$response->getCode() ."</p> ";
            echo "<p> Registration date: ".$response->getRegistrationDate() ."</p> ";
        }

        echo "<pre>";
    }
}

ChargePreApproval::main();
