Plugin de inscrição via PagSeguro para o Moodle
-----------------------------------------------

Este plugin de inscrição permite que você venda seus cursos no Moodle através do PagSeguro.

Também disponível em https://moodle.org/plugins/enrol_pagseguro

Instalação
-------

Você deve colocar este código no diretório moodle/enrol/pagseguro

Você pode fazer o "git clone" deste repositório ou então fazer o download da útlima versão no link https://github.com/danielneis/moodle-enrol_pagseguro/archive/master.zip

Configuração
------------

* Primeiro, você deve criar um Token no site do PagSeguro para utilizar o plugin.
* Também no site do PagSeguro, você deve preencher a "URL de retorno Fixa" com a URL do seu site Moodle + o caminho para o script do plugin que processará o retorno. Deve ficar algo como: https://www.meumoodle.org/enrol/pagseguro/process.php . ATENÇÃO: Este link é só um exemplo, você deve substituir "www.meumoodle.org" com a URL do seu Moodle.
* Ainda no site do PagSeguro, você deve preencher o campo "Código de transação para página de redirecionamento" com o valor "transaction_id" (sem as aspas).
* Com o token criado, volte ao seu Moodle e habilite o plugin indo em "Bloco administração" > Administração do Site > Plugins > Inscrições > Gerenciar plugins de inscrições
* Acesse o link das configurações do plugin PagSeguro
* Preencha o campo de token com o token criado
* Agora você pode utilizar o método de inscrição PagSeguro nos cursos. Você deve ir em um curso, acessar o "Bloco Administração" > Usuários > Métodos de inscrição e lá adicionar o novo método "PagSeguro". Ao adicionar este método você poderá definir o valor do curso, a moeda de pagamento e o email associado com o PagSeguro que receberá os pagamentos.

Funcionalidades
---------------

* Para cada curso Moodle, você pode configura o valor que o usuário deve pagar para se inscrever.
* A inscrição é feita automaticamente no caso de pagamento via cartão de crétido.
* Não é feita a desinscrição do usuário após devolução do dinheiro no PagSeguro.
* A inscrição automática via boleto bancário é feita quando o boleto é gerado. Não é validada a compensação do boleto, de forma que o usurio deve ser desinscrito manualmente caso no pague o boleto.
 
Sandbox
-------

Para utilizar ambiente de testes do PagSeguro (http://sandbox.pagseguro.uol.com.br/), marque a opção "Usar sandbox".
    
Perguntas Frequentes
--------------------

* Ao tentar comprar o curso pelo PagSeguro, recebo a mensagem: "This host is not authorized to use PagSeguro API"
 * Isso quer dizer que você não configurou o PagSeguro com a URL do seu ambiente Moodle. Você deve seguir os passos de configuração e preencher corretamente os campos no site do PagSeguro. Note que se você estiver usando o SandBox, deve cadastrar seu Moodle tambm no SandBox, pois são ambientes diferentes.
 
Dev Info
--------

[![Build Status](https://travis-ci.org/danielneis/moodle-enrol_pagseguro.svg?branch=update-3.0)](https://travis-ci.org/danielneis/moodle-enrol_pagseguro)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/danielneis/moodle-enrol_pagseguro/badges/quality-score.png?b=update-3.0)](https://scrutinizer-ci.com/g/danielneis/moodle-enrol_pagseguro/?branch=update-3.0)
