/*
 * Endereço pelo CEP: ao completar os 8 números, consulta a base local da
 * Prefeitura e preenche rua, bairro, cidade, UF e zona. Só preenche campos
 * vazios ou que o próprio sistema preencheu; o que a pessoa digitou fica.
 */
(function () {
    'use strict';

    var cep = document.getElementById('postal_code');
    var status = document.getElementById('postal_code-status');
    if (!cep || !status || !cep.dataset.consulta) {
        return;
    }

    var campos = {
        logradouro: document.getElementById('address_street'),
        bairro: document.getElementById('address_district'),
        cidade: document.getElementById('address_city'),
        uf: document.getElementById('address_state'),
        zona: document.getElementById('residence_zone')
    };
    var zonas = { urban: 'urban', rural: 'rural' };
    var ultimo = '';

    function avisar(texto, tipo) {
        status.textContent = texto;
        status.className = 'cep-status' + (tipo ? ' ' + tipo : '');
    }

    function preencher(campo, valor) {
        if (!campo || valor === null || valor === undefined || valor === '') {
            return;
        }
        if (campo.value === '' || campo.dataset.automatico === '1') {
            campo.value = valor;
            campo.dataset.automatico = '1';
        }
    }

    Object.keys(campos).forEach(function (chave) {
        var campo = campos[chave];
        if (campo) {
            campo.addEventListener('input', function () { campo.dataset.automatico = '0'; });
            campo.addEventListener('change', function () { campo.dataset.automatico = '0'; });
        }
    });

    function consultar() {
        var numeros = cep.value.replace(/\D+/g, '');
        if (numeros.length === 8 && !/-/.test(cep.value)) {
            cep.value = numeros.slice(0, 5) + '-' + numeros.slice(5);
        }
        if (numeros.length !== 8) {
            avisar(numeros.length === 0 ? '' : 'Digite os 8 números do CEP.', '');
            ultimo = '';
            return;
        }
        if (numeros === ultimo) {
            return;
        }
        ultimo = numeros;
        avisar('Procurando o CEP...', '');

        fetch(cep.dataset.consulta.replace('00000000', numeros), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (resposta) {
            return resposta.json().catch(function () { return { encontrado: false }; });
        }).then(function (dados) {
            if (!dados || !dados.encontrado) {
                avisar((dados && dados.mensagem) || 'CEP não encontrado. Preencha os campos abaixo.', 'aviso');
                return;
            }
            preencher(campos.logradouro, dados.logradouro);
            preencher(campos.bairro, dados.bairro);
            preencher(campos.cidade, dados.cidade);
            preencher(campos.uf, dados.uf);
            preencher(campos.zona, zonas[dados.zona] || '');
            var partes = [dados.logradouro, dados.bairro, dados.cidade + '/' + dados.uf].filter(Boolean);
            avisar('Endereço encontrado: ' + partes.join(', ') + '. Confira e informe o número.', 'ok');
            var numero = document.getElementById('address_number');
            if (numero && numero.value === '' && dados.logradouro) {
                numero.focus();
            }
        }).catch(function () {
            avisar('Não foi possível consultar o CEP agora. Preencha os campos abaixo.', 'aviso');
        });
    }

    cep.addEventListener('input', consultar);
    cep.addEventListener('change', consultar);
}());
