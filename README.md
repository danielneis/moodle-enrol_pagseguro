Plugin de inscrição via PagSeguro para o Moodle
-----------------------------------------------

Este plugin de inscrição permita que você venda seus cursos no Moodle e receba pelo PagSeguro.

Também disponível em https://moodle.org/plugins/enrol_pagseguro

Instalação
-------

Você deve colocar este código no diretório moodle/enrol/pagseguro

Você pode fazer o "git clone" deste repositório ou então fazer o download da útlima versão no link https://github.com/danielneis/moodle-enrol_pagseguro/archive/master.zip

Configuração
------------

* First, enable the plugin at Administration block > Site Administration > Plugins > Enrolments > Manage enrol plugins
* Then, go to its settings
* You must create a token at the PagSeguro website and use it to configure your Moodle plugin.
* Also, at the PagSeguro website, you should set the field "Código de transação para página de redirecionamento" with "transaction_id" (without quotes).
* Now you can go to any course and add the PagSeguro enrol method. There you will set the cost, currency and the email for the PagSeguro account that will be credited.

Funcionalidades
---------------

* Para cada curso Moodle, você pode configura o valor que o usuário deve pagar para se inscrever.
* A inscrição é feita automaticamente no caso de pagamento via cartão de crétido.
* Não é feita a desinscrição do usuário após devolução do dinheiro no PagSeguro.
* A inscrição automática via boleto bancário ainda não está funcionando, mas será implementada na próxima versão.


Dev Info
--------

[![Build Status](https://travis-ci.org/danielneis/moodle-enrol_pagseguro.svg?branch=update-3.0)](https://travis-ci.org/danielneis/moodle-enrol_pagseguro)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/danielneis/moodle-enrol_pagseguro/badges/quality-score.png?b=update-3.0)](https://scrutinizer-ci.com/g/danielneis/moodle-enrol_pagseguro/?branch=update-3.0)
