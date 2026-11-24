#!/usr/bin/env python3
"""Converte o leiaute oficial do Educacenso (planilha do Inep) e as tabelas
auxiliares em arquivos versionados que o Astuti lê: nada é digitado à mão.

Uso:
    python3 -I scripts/educacenso/extrair-leiaute.py ANO LEIAUTE.xlsx PASTA_DAS_TABELAS

Grava em resources/educacenso/ANO/:
    leiaute.json      registros, campos, grupos e regras, como estão na planilha
    tabelas/*.csv     tabelas auxiliares usadas pelos campos (UTF-8)
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

    with open(os.path.join(raiz, 'ORIGEM.md'), 'w', encoding='utf-8') as f:
        f.write(f'# Arquivos oficiais do Educacenso {ano}\n\n')
        f.write('Gerado por `scripts/educacenso/extrair-leiaute.py`. Não edite à mão: rode o script de novo com os arquivos oficiais.\n\n')
        f.write('| Conteúdo | Arquivo oficial | SHA-256 |\n|---|---|---|\n')
        for item in origem:
            f.write(f'| {item[0]} | {item[1]} | `{item[2]}` |\n')
    print('ok:', ', '.join(f"{r}={saida['registros'][r]['total_campos']}" for r in REGISTROS))


if __name__ == '__main__':
    main()
