// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Potential user selector module.
 *
 * @module     enrol_manual/form-potential-user-selector
 * @class      form-potential-user-selector
 * @package    enrol_manual
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var brandName = '';
var ghash = '';
var inst_val = '';

function loadDoc(courseid, p){
    require(['core/ajax'], function(ajax) {
        var promises = ajax.call([{
            methodname: 'enrol_pagseguro_get_session',
            args:{'courseP': p }
        }]);
        promises[0].done(function(response) {
            setPagueSeguroWSSessionId(response.stoken,courseid, response.courseP);
        }).fail(function() {
            // Do something with the exception.
        });
    });
}

function setPagueSeguroWSSessionId(sessionId,courseId, courseP){
    PagSeguroDirectPayment.setSessionId(sessionId);
    PagSeguroDirectPayment.getPaymentMethods({
        success: function() {
            // Retorna os meios de pagamento disponíveis.
            require(['jquery', 'core/ajax', 'core/templates', 'core/notification','enrol_pagseguro/jqmask'],
            function($, ajax, templates, notification, jqmask) {
                var promises = ajax.call([{
                    methodname: 'enrol_pagseguro_get_forms',
                    args:{ 'sessionId' : sessionId, 'courseId': courseId, 'courseP': courseP }
                }]);
                promises[0].done(function(response) {
                    templates.render('enrol_pagseguro/checkout_form', JSON.parse(response)).done(function(html, js) {
                        $('#modal-return').html(html);
                        templates.runTemplateJS(js);
                        PagSeguroDirectPayment.onSenderHashReady(function(response){
                            if(response.status == 'error') {
                                return false;
                            }
                            var hash = response.senderHash; // Hash estará disponível nesta variável.
                            ghash = hash;
                            createMasks();
                        });
                    }).fail(notification.exception);

                }).fail(function() {
                    // Do something with the exception.
                });
            });
        },
        error: function(response) {
            // Callback para chamadas que falharam.
            document.getElementById("return").innerHTML = JSON.stringify(response);
        },
        complete: function() {
            // Callback para todas chamadas.
        }
    });
}

function createMasks(){
    require(['jquery'], function($){
        var ph_options = {
            onKeyPress: function(ph, e, field, ph_options){
                var masks = ['(00) 0000-00009', '(00) 0 0000-0000'];
                var mask = (ph.length > 14) ? masks[1] : masks [0];
                $('.input-phone').mask(mask, ph_options);
            }
        }
        $('.input-phone').mask("(00) 0000-0000", ph_options);
        var options = {
            onKeyPress: function(doc, e, field, options){
                var masks = ['000.000.000-009', '00.000.000/0000-00'];
                var mask = (doc.length > 14) ? masks[1] : masks [0];
                $('.input-cpfcnpj').mask(mask, options);
            }
        }
        $('.input-cpfcnpj').mask("000.000.000-009", options);
        $('.input-ccnumber').mask('0000 0000 0000 0000');
        $('.input-ccvalid').mask('00/0000');
        $('.input-cvv').mask('000');
        $('.input-cep').mask('00000-000');
    });
}

function buscaCEP(){
    require(['jquery'],function($){
        var cep = $('#billingpostcode').val().replace(/\D/g, '');
        // Verifica se campo cep possui valor informado.
        if (cep != "") {
            // Expressão regular para validar o CEP.
            var validacep = /^[0-9]{8}$/;
            // Valida o formato do CEP.
            if(validacep.test(cep)) {
                // Preenche os campos com "..." enquanto consulta webservice.
                $("#billingstreet").val("...");
                $("#billingdistrict").val("...");
                $("#billingcity").val("...");
                $("#billingstate").val("...");
                $("#ibge").val("...");
                // Consulta o webservice viacep.com.br.
                $.getJSON("https://viacep.com.br/ws/" + cep + "/json/?callback=?", function(dados) {
                    if (!("erro" in dados)) {
                        // Atualiza os campos com os valores da consulta.
                        $("#billingstreet").val(dados.logradouro);
                        $("#billingdistrict").val(dados.bairro);
                        $("#billingcity").val(dados.localidade);
                        $("#billingstate").val(dados.uf);
                        $("#ibge").val(dados.ibge);
                    } else {
                        // CEP pesquisado não foi encontrado.
                        limpa_formulário_cep();
                        alert("CEP não encontrado.");
                    }
                });
            } else {
                limpa_formulário_cep();
                alert("Formato de CEP inválido.");
            }
        } else {
            // Cep sem valor, limpa formulário.
            limpa_formulário_cep();
        }
    });
}

function limpa_formulário_cep() {
    // Limpa valores do formulário de cep.
    require(['jquery'],function($){
        $("#billingstreet").val("");
        $("#billingdistrict").val("");
        $("#billingcity").val("");
        $("#billingstate").val("");
        $("#ibge").val("");
    });
}

function checkBrand(el, cp){
    var cardnum = el.value.replace(/\s/g, '');
    if(cardnum.length >= 6){
        PagSeguroDirectPayment.getBrand({
            cardBin: Number(cardnum.substring(0,6)),
            success: function(response) {
                require(['jquery'],function($){
                    brandName = response.brand.name;
                    var imgsrc = "https://stc.pagseguro.uol.com.br/public/img/payment-methods-flags/42x20/";
                    $('#cardbrand').html("<img src=\"" + imgsrc + response.brand.name + ".png\" />" );
                    installments(brandName,cp);
                });
            },
            error: function() {

            },
            complete: function() {

            }
        });
    }
}

function installments(brandName,cp){
    var n = Number.parseFloat(cp).toFixed(2);
    PagSeguroDirectPayment.getInstallments({
        amount: n,
        brand: brandName,
        success: function(response){
            // Retorna as opções de parcelamento disponíveis.
            require(['jquery'],function($){
                if(!response["error"]){
                    var sel_installments = '<select id="installments" name="ccinstallments">';
                    response["installments"][brandName].forEach(function(inst){
                        sel_installments += '<option value="' + inst["quantity"] + '" data-installment-value="' + inst["installmentAmount"].toFixed(2) + '" >';
                        sel_installments += inst["quantity"] + 'x de ' + inst["installmentAmount"].toFixed(2);
                        sel_installments += '</option>';
                    });
                    sel_installments += '</select>';
                }
                $('#card-installments').html("<span>" + sel_installments + "</span>");
            });

        },
        error: function() {
            // Callback para chamadas que falharam.
        },
        complete: function(){
            // Callback para todas chamadas.
        }
    });

}

function paycc(){
    if(!ccValidateFields()){
        return;
    }else{
        require(['jquery'], function($){
            var ccNum = $("input[name=ccnumber]").val().replace(/\s/g, '');
            var ccCvv = $("input[name=cvv]").val();
            var ccExp = $("input[name=ccvalid]").val().split("/");
            PagSeguroDirectPayment.createCardToken({
                cardNumber: ccNum, // Número do cartão de crédito.
                brand: brandName, // Bandeira do cartão.
                cvv: ccCvv, // CVV do cartão.
                expirationMonth: ccExp[0], // Mês da expiração do cartão.
                expirationYear: ccExp[1], // Ano da expiração do cartão, é necessário os 4 dígitos.
                success: function(response) {
                    if(ccValidateFields()){
                        $("input[name=cc_token]").val(response.card.token);
                        var urlParams = new URLSearchParams(window.location.search);
                        $("input[name=courseid]").val(urlParams.get('id'));
                        $("input[name=inst_val]").val($("#installments").data('data-installment-value'));
                        $("#pagseguro_cc_form").submit();
                    }
                },
                error: function() {
                    // Callback para chamadas que falharam.
                },
                complete: function() {
                    // Callback para todas chamadas.
                }
            });
        });
    }
}

function payboleto(){
    if(boletoValidateFields()){
        $("#pagseguro_boleto_form input[name=sender_hash]").val(ghash);
        var urlParams = new URLSearchParams(window.location.search);
        $("#pagseguro_boleto_form input[name=courseid]").val(urlParams.get('id'));
        $("#pagseguro_boleto_form").submit();
    }
}

function ccValidateFields(){
    var rtn = true;
    require(['jquery'],function($){
        if(!$("#ccEmail").val().trim()){
            rtn = false;
            $("#ccEmail-error").html('Favor preencher email corretamente');
        }else{
            $("#ccEmail-error").html('');
        }
        if(!$("#ccPhone").val().trim()){
            rtn = false;
            $("#ccPhone-error").html('Favor preencher telefone corretamente');
        }else{
            $("#ccPhone-error").html('');
        }
        if(!$("#ccCPFCNPJ").val().trim()){
            rtn = false;
            $("#ccCPFCNPJ-error").html('Favor preencher CPF/CNPJ corretamente');
        }else{
            $("#ccCPFCNPJ-error").html('');
        }
        if(!$("#ccName").val().trim()){
            rtn = false;
            $("#ccName-error").html('Favor preencher Nome corretamente');
        }else{
            $("#ccName-error").html('');
        }
        if(!$("#ccNumber").val().trim()){
            rtn = false;
            $("#ccNumber-error").html('Favor preencher Número do cartão corretamente');
        }else{
            $("#ccNumber-error").html('');
        }
        if(!$("#ccvalid").val().trim()){
            rtn = false;
            $("#ccvalid-error").html('Favor preencher Validade do cartão corretamente');
        }else{
            $("#ccvalid-error").html('');
        }
        if(!$("#cvv").val().trim()){
            rtn = false;
            $("#cvv-error").html('Favor preencher CVV corretamente');
        }else{
            $("#cvv-error").html('');
        }
        if(!$("#billingpostcode").val().trim()){
            rtn = false;
            $("#billingpostcode-error").html('Favor preencher cep corretamente');
        }else{
            $("#billingpostcode-error").html('');
        }
        if(!$("#billingstreet").val().trim()){
            rtn = false;
            $("#billingstreet-error").html('Favor preencher campo corretamente');
        }else{
            $("#billingstreet-error").html('');
        }
        if(!$("#billingstate").val().trim()){
            rtn = false;
            $("#billingstate-error").html('Favor preencher estado corretamente');
        }else{
            $("#billingstate-error").html('');
        }
        if(!$("#billingdistrict").val().trim()){
            rtn = false;
            $("#billingdistrict-error").html('Favor preencher bairro corretamente');
        }else{
            $("#billingdistrict-error").html('');
        }
        if(!$("#billingcity").val().trim()){
            rtn = false;
            $("#billingcity-error").html('Favor preencher cidade corretamente');
        }else{
            $("#billingcity-error").html('');
        }
    });

    return rtn;
}

function boletoValidateFields(){
    var rtn = true;
    require(['jquery'],function($){
        if(!$("#boleto_nome").val().trim()){
            rtn = false;
            $("#boleto_nome-error").html('Favor preencher o nome corretamente');
        }else{
            $("#boleto_nome-error").html('');
        }
        if(!$("#boleto_email").val().trim()){
            rtn = false;
            $("#boleto_email-error").html('Favor preencher email corretamente');
        }else{
            $("#boleto_email-error").html('');
        }
        if(!$("#boleto_phone").val().trim()){
            rtn = false;
            $("#boleto_phone-error").html('Favor preencher telefone corretamente');
        }else{
            $("#boleto_phone-error").html('');
        }
        if(!$("#boleto_doc").val().trim()){
            rtn = false;
            $("#boleto_doc-error").html('Favor preencher CPF/CNPJ corretamente');
        }else{
            $("#boleto_doc-error").html('');
        }
    });
    return rtn;
}
