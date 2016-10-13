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

* Primeiro, você deve criar um Token no site do PagSeguro para utilizar o plugin.
* Além disso, no site do PagSeguro, você deve preencher o campo "Código de transação para página de redirecionamento" sem "transaction_id" (sem aspas).
* Com o token criado, habilite o plugin indo em "Bloco administração" > Administração do Site > Plugins > Inscrições > Gerenciar plugins de inscrições
* Acesse o link das configurações do plugin PagSeguro
* Preencha o campo de token com o token criado
* Agora você pode utilizar o método de inscrição PagSeguro nos cursos. Você deve ir em um curso, acessar o "Bloco Administração" > Usuários > Métodos de inscrição e lá adicionar o novo método "PagSeguro". Ao adicionar este método você poderá definir o valor do curso, a moeda de pagamento e o email associado com o PagSeguro que receberá os pagamentos.

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
