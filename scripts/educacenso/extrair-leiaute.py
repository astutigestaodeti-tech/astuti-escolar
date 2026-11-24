#!/usr/bin/env python3
"""Converte o leiaute oficial do Educacenso (planilha do Inep) e as tabelas
auxiliares em arquivos versionados que o Astuti lê: nada é digitado à mão.

Uso:
    python3 -I scripts/educacenso/extrair-leiaute.py ANO LEIAUTE.xlsx PASTA_DAS_TABELAS

Grava em resources/educacenso/ANO/:
    leiaute.json      registros, campos, grupos e regras, como estão na planilha
    tabelas/*.csv     tabelas auxiliares usadas pelos campos (UTF-8)
    tabelas.json      etapas e áreas do conhecimento por etapa, lidas das tabelas oficiais
    ORIGEM.md         SHA-256 de cada arquivo oficial de origem

Recusa a planilha se a contagem de campos de algum registro não bater com a
aba "regras gerais" do próprio Inep.
"""
import csv
import hashlib
import json
import os
import re
import sys

import openpyxl

REGISTROS = ['00', '10', '20', '30', '40', '50', '60']

# Tabela auxiliar -> (arquivo, aba, linha do cabeçalho (1-based), colunas a manter)
TABELAS = {
    'municipios': ('Tabela de Municípios', None),
    'distritos': ('Tabela de Distritos', None),
    'paises': ('Tabela de Países', None),
    'etapas': ('Tabela de Etapas', 'Etapas de ensino'),
    'areas_conhecimento': ('Tabela de Áreas do conhecimento_Componentes Curriculares', None),
    'atividade_complementar': ('Tabela de Tipo de Atividade Complementar', None),
    'linguas_indigenas': ('Tabela de Línguas Indígenas', None),
    'povos_indigenas': ('Tabela de Povos Indígenas', None),
    'cursos_educacao_profissional': ('Tabela de Cursos da Educação Profissional', None),
    'cursos_formacao_superior': ('Tabela de Cursos de Formação Superior', None),
    'ies': ('Tabela de IES', None),
    'areas_pos_graduacao': ('Tabela de áreas pós graduação', None),
    'orgaos_regionais': ('Tabela de Órgãos Regionais', None),
    'ddd': ('Tabela de DDD', None),
    'regras_horarios_coincidentes': ('Tabela de Regras Horários Coincidentes', None),
    'regras_areas_conhecimento': ('Tabela de Regras de Áreas do Conhecimento', None),
    'aee': ('Tabela de Atendimento Educacional Especializado (AEE)', None),
    'dependencias': ('Tabela de Dependências', None),
    'deficiencia_recurso': ('Tabela de Deficiência x Recurso', None),
    'transtorno_recurso': ('Tabela de Transtornos x Recurso', None),
    'mediacao_turma_etapa': ('Tabela de mediação didático-pedagógica', None),
}


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for bloco in iter(lambda: f.read(1 << 20), b''):
            h.update(bloco)
    return h.hexdigest()


def texto(valor):
    if valor is None:
        return None
    s = str(valor).replace('\r', '').strip()
    return s or None


def opcoes(permitidos):
    """'1-Urbana\\n2-Rural' -> [{'codigo': '1', 'rotulo': 'Urbana'}, ...]; None se não for lista."""
    if not permitidos:
        return None
    linhas = [l.strip() for l in permitidos.split('\n') if l.strip()]
    saida = []
    for linha in linhas:
        m = re.match(r'^(\d+)\s*[-–]\s*(.+)$', linha)
        if not m:
            return None
        saida.append({'codigo': m.group(1), 'rotulo': m.group(2).strip()})
    return saida or None


def registro(ws):
    campos, grupos, atual = [], [], None
    for linha in ws.iter_rows(min_row=2, values_only=True):
        linha = list(linha[:11]) + [None] * max(0, 11 - len(linha))
        numero, nome, obg, tm, fixo, tipo, permitidos, n, regra, momento, status = linha[:11]
        numero, regra = texto(numero), texto(regra)
        if numero is not None:
            if re.fullmatch(r'\d+', numero):
                atual = {
                    'campo': int(numero),
                    'nome': re.sub(r'\s+', ' ', texto(nome) or ''),
                    'obrigatorio': texto(obg),
                    'tamanho': int(tm) if isinstance(tm, (int, float)) else texto(tm),
                    'tamanho_fixo': texto(fixo) == 'f',
                    'tipo': texto(tipo),
                    'permitidos': texto(permitidos),
                    'opcoes': opcoes(texto(permitidos)) if texto(tipo) in ('selec', 'multi') else None,
                    'regras': [],
                }
                campos.append(atual)
            else:
                m = re.fullmatch(r'(\d+)\s*a\s*(\d+)', numero)
                if not m:
                    raise SystemExit(f'numero de campo inesperado: {numero!r}')
                atual = {'de': int(m.group(1)), 'ate': int(m.group(2)), 'nome': re.sub(r'\s+', ' ', texto(nome) or ''), 'regras': []}
                grupos.append(atual)
        if regra and atual is not None:
            atual['regras'].append({'n': texto(n), 'texto': re.sub(r'\s+', ' ', regra), 'momento': texto(momento), 'status': texto(status)})
    return campos, grupos


def contagens_oficiais(ws):
    contagem = {}
    for linha in ws.iter_rows(values_only=True):
        regra = texto(linha[1]) if len(linha) > 1 else None
        if regra:
            m = re.match(r'O registro (\d{2}) deve ter (\d+) campos', regra)
            if m:
                contagem[m.group(1)] = int(m.group(2))
    return contagem


def regras_gerais(ws):
    saida = []
    for linha in ws.iter_rows(min_row=2, values_only=True):
        if len(linha) < 2 or not isinstance(linha[0], (int, float)):
            continue
        saida.append({'n': int(linha[0]), 'texto': re.sub(r'\s+', ' ', texto(linha[1]) or ''), 'momento': texto(linha[2]), 'status': texto(linha[3])})
    return saida


def anexos(wb):
    saida = {}
    for ws in wb.worksheets:
        if ws.title.startswith('Anexo'):
            linhas = []
            for linha in ws.iter_rows(values_only=True):
                valores = [texto(v) for v in linha]
                while valores and valores[-1] is None:
                    valores.pop()
                if any(valores):
                    linhas.append(valores)
            saida[ws.title] = linhas
    return saida


def tabela(caminho, aba):
    wb = openpyxl.load_workbook(caminho, read_only=True, data_only=True)
    ws = wb[aba] if aba else wb.worksheets[0]
    linhas = []
    for linha in ws.iter_rows(values_only=True):
        valores = ['' if v is None else str(v).replace('\r', '').replace('\n', ' ').strip() for v in linha]
        while valores and valores[-1] == '':
            valores.pop()
        if any(valores):
            linhas.append(valores)
    return linhas


def regras_areas(caminho, codigos_areas):
    """Tabela de Regras de Áreas do Conhecimento -> {etapa: [códigos que podem ser 1]}.

    A planilha agrupa etapas e áreas em blocos visuais. Um bloco novo começa
    depois do bloco "Nenhuma" (educação infantil) ou quando a coluna de áreas
    volta a um código menor. Cada bloco precisa cobrir as 27 áreas do registro 20
    (permitidas + não permitidas), senão a extração é recusada.
    """
    ws = openpyxl.load_workbook(caminho, data_only=True).worksheets[0]
    blocos, atual, anterior = [], None, None
    for a, b, c in ws.iter_rows(min_col=1, max_col=3, values_only=True):
        a, b, c = (texto(v) or '' for v in (a, b, c))
        if a.startswith('Etapa/'):
            atual = None
            continue
        mb = re.match(r'^(\d+)\s*-', b)
        mc = re.match(r'^(\d+)\s*-', c)
        ma = re.match(r'^(\d+)\b', a)
        codigo_b = int(mb.group(1)) if mb else None
        novo = atual is None or b == 'Nenhuma' or (atual['nenhuma'] and codigo_b is not None) \
            or (codigo_b is not None and anterior is not None and codigo_b < anterior)
        if novo and (ma or b == 'Nenhuma' or codigo_b is not None):
            atual = {'etapas': [], 'permitidas': [], 'proibidas': [], 'nenhuma': b == 'Nenhuma'}
            blocos.append(atual)
            anterior = None
        if atual is None:
            continue
        if ma:
            atual['etapas'].append(int(ma.group(1)))
        if codigo_b is not None:
            atual['permitidas'].append(codigo_b)
            anterior = codigo_b
        if mc:
            atual['proibidas'].append(int(mc.group(1)))
    # Bloco sem etapa é continuação do anterior (linha que sobrou depois do 99).
    unidos = []
    for bloco in blocos:
        if not bloco['etapas'] and unidos:
            unidos[-1]['permitidas'] += bloco['permitidas']
            unidos[-1]['proibidas'] += bloco['proibidas']
        else:
            unidos.append(bloco)
    saida = {}
    todas = set(codigos_areas)
    for bloco in unidos:
        permitidas = [] if bloco['nenhuma'] else sorted(set(bloco['permitidas']))
        if not bloco['nenhuma']:
            proibidas = set(bloco['proibidas'])
            if set(permitidas) & proibidas or set(permitidas) | proibidas != todas:
                raise SystemExit(f"regras de áreas: bloco {bloco['etapas']} não cobre as 27 áreas")
        for etapa in bloco['etapas']:
            if etapa in saida:
                raise SystemExit(f'regras de áreas: etapa {etapa} repetida')
            saida[str(etapa)] = permitidas
    return saida


def etapas(caminho):
    """Tabela de Etapas (colunas da esquerda) -> lista de {codigo, nome, agregada, nome_agregada}."""
    ws = openpyxl.load_workbook(caminho, data_only=True)['Etapas de ensino']
    saida, agregada, nome_agregada = [], None, None
    for a, b, c, d in ws.iter_rows(min_col=1, max_col=4, values_only=True):
        if isinstance(a, (int, float)):
            agregada, nome_agregada = int(a), re.sub(r'\s+', ' ', texto(b) or '')
        if isinstance(c, (int, float)) and agregada is not None:
            saida.append({'codigo': int(c), 'nome': re.sub(r'\s+', ' ', texto(d) or ''), 'agregada': agregada, 'nome_agregada': nome_agregada})
    return saida


def organizacao_por_etapa(anexo6):
    """Anexo 6 -> {etapa: {'formas': [1..5 permitidas], 'alternancia': bool}}."""
    saida = {}
    for linha in anexo6:
        linha = list(linha) + [None] * (10 - len(linha))
        if linha[2] is None or not str(linha[2]).isdigit():
            continue
        formas = [i + 1 for i, v in enumerate(linha[4:9]) if v == 'X']
        saida[str(int(linha[2]))] = {'formas': formas, 'alternancia': linha[9] == 'X'}
    return saida


def mediacao_tipo_etapa(anexo7, registro20):
    """Anexo 7 -> lista de {mediacao, tipo_turma, etapas}; códigos tirados das opções do registro 20."""
    def codigo(campo, rotulo):
        for opcao in registro20[campo - 1]['opcoes']:
            if opcao['rotulo'].lower() == rotulo.lower() or opcao['rotulo'].lower().endswith(rotulo.lower()):
                return int(opcao['codigo'])
        raise SystemExit(f'Anexo 7: {rotulo!r} não corresponde a uma opção do campo {campo} do registro 20')
    saida, mediacao, tipo = [], None, None
    for linha in anexo7[2:]:
        linha = list(linha) + [None] * (4 - len(linha))
        if linha[0]:
            mediacao = codigo(6, linha[0].strip())
        if linha[1]:
            tipo = codigo(14, linha[1].strip())
        etapas = linha[3] or ''
        lista = [int(v) for v in re.findall(r'\d+', etapas)]
        saida.append({'mediacao': mediacao, 'tipo_turma': tipo, 'agregada': (linha[2] or '').strip(), 'etapas': lista, 'observacao': None if lista or etapas.strip() in ('-', '') else etapas.strip()})
    return saida


def main():
    if len(sys.argv) != 4 or not re.fullmatch(r'20\d\d', sys.argv[1]):
        raise SystemExit(__doc__)
    ano, leiaute, pasta = sys.argv[1], sys.argv[2], sys.argv[3]
    raiz = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'resources', 'educacenso', ano)
    os.makedirs(os.path.join(raiz, 'tabelas'), exist_ok=True)

    wb = openpyxl.load_workbook(leiaute, data_only=True)
    oficiais = contagens_oficiais(wb['regras gerais'])
    saida = {'ano': int(ano), 'fonte': os.path.basename(leiaute), 'regras_gerais': regras_gerais(wb['regras gerais']), 'registros': {}, 'anexos': anexos(wb)}
    for nome in REGISTROS:
        campos, grupos = registro(wb[nome])
        numeros = [c['campo'] for c in campos]
        if numeros != list(range(1, len(numeros) + 1)):
            raise SystemExit(f'registro {nome}: campos fora de sequência')
        if oficiais.get(nome) != len(campos):
            raise SystemExit(f'registro {nome}: {len(campos)} campos, mas as regras gerais dizem {oficiais.get(nome)}')
        saida['registros'][nome] = {'total_campos': len(campos), 'campos': campos, 'grupos': grupos}
    with open(os.path.join(raiz, 'leiaute.json'), 'w', encoding='utf-8') as f:
        json.dump(saida, f, ensure_ascii=False, indent=1)
        f.write('\n')

    origem = [('Leiaute de importação e exportação', os.path.basename(leiaute), sha256(leiaute))]
    arquivos = {f: os.path.join(pasta, f) for f in os.listdir(pasta) if f.endswith('.xlsx')}
    for chave, (prefixo, aba) in TABELAS.items():
        achados = sorted(f for f in arquivos if f.startswith(prefixo))
        if len(achados) != 1:
            raise SystemExit(f'tabela {chave}: esperado 1 arquivo começando com {prefixo!r}, achei {achados}')
        caminho = arquivos[achados[0]]
        with open(os.path.join(raiz, 'tabelas', chave + '.csv'), 'w', encoding='utf-8', newline='') as f:
            csv.writer(f, lineterminator='\n').writerows(tabela(caminho, aba))
        origem.append((chave, achados[0], sha256(caminho)))

    codigos_areas = [int(m.group(1)) for c in saida['registros']['20']['campos'][38:65] for m in [re.match(r'^(\d+)\.', c['nome'])]]
    if len(codigos_areas) != 27:
        raise SystemExit('registro 20: esperadas 27 áreas do conhecimento (campos 39 a 65)')
    derivados = {
        'etapas': etapas(arquivos[sorted(f for f in arquivos if f.startswith(TABELAS['etapas'][0]))[0]]),
        'areas_por_etapa': regras_areas(arquivos[sorted(f for f in arquivos if f.startswith(TABELAS['regras_areas_conhecimento'][0]))[0]], codigos_areas),
        'areas_campo': {str(codigo): 39 + i for i, codigo in enumerate(codigos_areas)},
        'organizacao_por_etapa': organizacao_por_etapa(saida['anexos']['Anexo 6']),
        'mediacao_tipo_etapa': mediacao_tipo_etapa(saida['anexos']['Anexo 7'], saida['registros']['20']['campos']),
    }
    with open(os.path.join(raiz, 'tabelas.json'), 'w', encoding='utf-8') as f:
        json.dump(derivados, f, ensure_ascii=False, indent=1)
        f.write('\n')

    with open(os.path.join(raiz, 'ORIGEM.md'), 'w', encoding='utf-8') as f:
        f.write(f'# Arquivos oficiais do Educacenso {ano}\n\n')
        f.write('Gerado por `scripts/educacenso/extrair-leiaute.py`. Não edite à mão: rode o script de novo com os arquivos oficiais.\n\n')
        f.write('| Conteúdo | Arquivo oficial | SHA-256 |\n|---|---|---|\n')
        for item in origem:
            f.write(f'| {item[0]} | {item[1]} | `{item[2]}` |\n')
    print('ok:', ', '.join(f"{r}={saida['registros'][r]['total_campos']}" for r in REGISTROS))


if __name__ == '__main__':
    main()
