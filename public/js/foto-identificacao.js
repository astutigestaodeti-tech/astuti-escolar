// Reduz a foto no próprio navegador antes do envio. O sistema guarda só 480 px,
// então enviar a foto original (às vezes 5 MB ou mais) só gasta dados e esbarra
// no limite do servidor. A imagem gerada sai em JPEG, sem dados de câmera ou GPS.
const LADO_MAXIMO = 960;
const QUALIDADE = 0.88;
const TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/webp'];

function tamanhoLegivel(bytes) {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

async function reduzir(arquivo) {
    const imagem = await createImageBitmap(arquivo, { imageOrientation: 'from-image' });
    const escala = Math.min(1, LADO_MAXIMO / Math.max(imagem.width, imagem.height));
    const largura = Math.max(1, Math.round(imagem.width * escala));
    const altura = Math.max(1, Math.round(imagem.height * escala));

    const tela = document.createElement('canvas');
    tela.width = largura;
    tela.height = altura;
    const desenho = tela.getContext('2d');
    desenho.fillStyle = '#ffffff';
    desenho.fillRect(0, 0, largura, altura);
    desenho.drawImage(imagem, 0, 0, largura, altura);
    imagem.close?.();

    const blob = await new Promise((resolve) => tela.toBlob(resolve, 'image/jpeg', QUALIDADE));
    if (!blob) throw new Error('sem-imagem');

    const nome = arquivo.name.replace(/\.[^.]+$/, '') || 'foto';
    return new File([blob], `${nome}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

function trocarArquivo(campo, arquivo) {
    const transferencia = new DataTransfer();
    transferencia.items.add(arquivo);
    campo.files = transferencia.files;
}

function prepararCampo(campo) {
    const formulario = campo.form;
    const botao = formulario?.querySelector('button[type="submit"]');
    const situacao = formulario?.querySelector('.foto-id-situacao');
    const limite = Number(campo.dataset.limiteBytes) || Infinity;

    const avisar = (texto, erro = false) => {
        if (!situacao) return;
        situacao.textContent = texto;
        situacao.classList.toggle('foto-id-situacao-erro', erro);
    };

    campo.addEventListener('change', async () => {
        const arquivo = campo.files?.[0];
        if (!arquivo) {
            avisar('');
            return;
        }
        if (!TIPOS_ACEITOS.includes(arquivo.type)) {
            avisar('Envie uma foto JPG, PNG ou WebP.', true);
            if (botao) botao.disabled = true;
            return;
        }

        if (botao) botao.disabled = true;
        avisar('Preparando a foto...');

        let final = arquivo;
        try {
            const reduzida = await reduzir(arquivo);
            if (reduzida.size < arquivo.size) {
                trocarArquivo(campo, reduzida);
                final = reduzida;
            }
        } catch {
            // Navegador sem suporte: segue com o arquivo original e o servidor confere.
        }

        if (final.size > limite) {
            avisar(`A foto tem ${tamanhoLegivel(final.size)} e o limite é ${tamanhoLegivel(limite)}. Escolha uma foto menor.`, true);
            return;
        }

        if (botao) botao.disabled = false;
        avisar(final === arquivo
            ? 'Foto pronta. Clique em "Enviar foto".'
            : `Foto ajustada de ${tamanhoLegivel(arquivo.size)} para ${tamanhoLegivel(final.size)}. Clique em "Enviar foto".`);
    });
}

document.querySelectorAll('input[type="file"][data-foto-reduzir]').forEach(prepararCampo);
