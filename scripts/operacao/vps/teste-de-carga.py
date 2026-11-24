#!/usr/bin/env python3
"""Teste de carga do Astuti Gestão Escolar, somente leitura e só com dados fictícios.

Simula várias pessoas usando o sistema ao mesmo tempo (painel, escolas, alunos,
turmas, pendências, Censo, relatórios) e mede o tempo de resposta e os erros.

Uso, na VPS de desenvolvimento, dentro da pasta do sistema:
    python3 scripts/operacao/vps/teste-de-carga.py --usuario admin --pessoas 20 --duracao 120

A senha (e o código do segundo fator, se a conta tiver) é digitada no terminal
e não fica gravada. Não grava nada no sistema: só abre telas (GET). Fichas de
alunos ficam de fora, para não encher a auditoria com aberturas de teste.

Recusa rodar quando o .env diz APP_ENV=production.
"""

from __future__ import annotations

import argparse
import getpass
import html
import random
import re
import statistics
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from pathlib import Path

UUID = re.compile(r"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}")


class SemRedirecionar(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):  # noqa: D401 - urllib API
        return None


class Sessao:
    """Cookies tratados à mão: o navegador simulado envia tudo, inclusive cookies Secure."""

    def __init__(self, base: str, agente: str, host: str | None = None) -> None:
        self.base = base.rstrip("/")
        # Teste local: fala direto com o Nginx da VPS (sem Cloudflare), com o nome público do site.
        self.host = host
        self.cookies: dict[str, str] = {}
        self.trava = threading.Lock()
        self.abridor = urllib.request.build_opener(SemRedirecionar)
        self.agente = agente

    def _guardar(self, resposta) -> None:
        for cabecalho in resposta.headers.get_all("Set-Cookie") or []:
            nome, _, resto = cabecalho.partition("=")
            valor = resto.split(";", 1)[0]
            with self.trava:
                self.cookies[nome.strip()] = valor

    def pedir(self, caminho: str, dados: dict | None = None, seguir: bool = True) -> tuple[int, str, str, float]:
        url = caminho if caminho.startswith("http") else self.base + caminho
        corpo = urllib.parse.urlencode(dados).encode() if dados is not None else None
        for _ in range(6):
            with self.trava:
                cookie = "; ".join(f"{k}={v}" for k, v in self.cookies.items())
            pedido = urllib.request.Request(url, data=corpo, method="POST" if corpo else "GET", headers={
                "Cookie": cookie, "User-Agent": self.agente, "Accept": "text/html",
                **({"Host": self.host, "X-Forwarded-Proto": "https"} if self.host else {}),
                **({"Content-Type": "application/x-www-form-urlencoded"} if corpo else {}),
            })
            inicio = time.perf_counter()
            try:
                resposta = self.abridor.open(pedido, timeout=60)
                status, texto = resposta.status, resposta.read().decode("utf-8", "replace")
            except urllib.error.HTTPError as erro:
                resposta, status = erro, erro.code
                texto = erro.read().decode("utf-8", "replace") if erro.fp else ""
            decorrido = time.perf_counter() - inicio
            self._guardar(resposta)
            destino = resposta.headers.get("Location")
            if seguir and status in (301, 302, 303, 307, 308) and destino:
                url, corpo = urllib.parse.urljoin(url, destino), None
                alvo = urllib.parse.urlparse(url)
                if self.host and alvo.netloc == self.host:
                    url = self.base + alvo.path + (f"?{alvo.query}" if alvo.query else "")
                continue
            return status, texto, url, decorrido
        return 599, "", url, 0.0


def token(texto: str) -> str:
    achado = re.search(r'name="_token"\s+value="([^"]+)"', texto) or re.search(r'name="csrf-token"\s+content="([^"]+)"', texto)
    if not achado:
        sys.exit("ERRO: não encontrei o token do formulário de login.")
    return html.unescape(achado.group(1))


def entrar(sessao: Sessao, usuario: str) -> str:
    _, pagina, _, _ = sessao.pedir("/login")
    senha = getpass.getpass(f"Senha de {usuario} (não aparece na tela): ")
    status, pagina, url, _ = sessao.pedir("/login", {"_token": token(pagina), "email": usuario, "password": senha})
    if "/segundo-fator/desafio" in url:
        codigo = input("Código de 6 números do aplicativo autenticador: ").strip()
        status, pagina, url, _ = sessao.pedir(url, {"_token": token(pagina), "code": codigo})
    if "/login" in urllib.parse.urlparse(url).path:
        sys.exit("ERRO: o login não foi aceito. Confira usuário e senha.")
    achado = re.search(r"/m/([a-z0-9-]+)/", url) or re.search(r'href="[^"]*/m/([a-z0-9-]+)/escolas"', pagina)
    if not achado:
        sys.exit("ERRO: entrei, mas não encontrei a rede municipal da conta.")
    return achado.group(1)


def descobrir(sessao: Sessao, municipio: str) -> list[str]:
    rede = f"/m/{municipio}"
    _, pagina, _, _ = sessao.pedir(f"{rede}/escolas")
    escolas = sorted(set(re.findall(rf"{re.escape(rede)}/escolas/({UUID.pattern})", pagina)))
    if not escolas:
        sys.exit("ERRO: a conta não enxerga nenhuma escola.")
    telas = [f"{rede}/escolas"]
    for tela in ("painel-da-rede", "conferencia-do-censo", "relatorio-mensal-secretaria", "alfabetizacao", "busca-ativa", "lista-de-espera", "equidade-vaar", "tempo-integral"):
        status, _, url, _ = sessao.pedir(f"{rede}/{tela}")
        if status == 200 and "segundo-fator" not in url:
            telas.append(f"{rede}/{tela}")
    for escola in escolas:
        base = f"{rede}/escolas/{escola}"
        telas += [base, f"{base}/alunos", f"{base}/alunos?busca=ana", f"{base}/turmas", f"{base}/pendencias", f"{base}/alertas-faltas", f"{base}/matriculas"]
        _, pagina, _, _ = sessao.pedir(f"{base}/turmas")
        telas += [f"{base}/turmas/{turma}" for turma in sorted(set(re.findall(rf"{re.escape(base)}/turmas/({UUID.pattern})", pagina)))[:4]]
    return telas


def percentil(valores: list[float], p: float) -> float:
    if not valores:
        return 0.0
    ordenados = sorted(valores)
    return ordenados[min(len(ordenados) - 1, int(round(p / 100 * (len(ordenados) - 1))))]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--usuario", required=True, help="e-mail ou usuário curto (ex.: admin)")
    parser.add_argument("--url", help="endereço do sistema; padrão: APP_URL do .env")
    parser.add_argument("--pessoas", type=int, default=20, help="pessoas usando ao mesmo tempo (padrão 20)")
    parser.add_argument("--duracao", type=int, default=120, help="segundos de teste (padrão 120)")
    parser.add_argument("--local", action="store_true", help="mede só a VPS, sem passar pela Cloudflare (http://127.0.0.1 com o nome do site)")
    parser.add_argument("--pausa", type=float, default=3.0, help="segundos médios entre um clique e outro de cada pessoa (padrão 3)")
    args = parser.parse_args()

    env = Path(".env")
    valores = dict(re.findall(r"^([A-Z_]+)=\"?([^\"\n]*)\"?", env.read_text(), re.M)) if env.exists() else {}
    if valores.get("APP_ENV") == "production":
        sys.exit("ERRO: este teste é só para a VPS de desenvolvimento (APP_ENV=production recusado).")
    base = args.url or valores.get("APP_URL")
    if not base:
        sys.exit("ERRO: informe --url ou rode dentro da pasta do sistema (com .env).")
    if not 1 <= args.pessoas <= 300 or not 10 <= args.duracao <= 1800:
        sys.exit("ERRO: use de 1 a 300 pessoas e de 10 a 1800 segundos.")

    host = None
    if args.local:
        host = urllib.parse.urlparse(base).netloc
        base = "http://127.0.0.1"
    sessao = Sessao(base, "astuti-teste-de-carga", host)
    municipio = entrar(sessao, args.usuario)
    telas = descobrir(sessao, municipio)
    print(f"\nRede {municipio}: {len(telas)} telas no sorteio. {args.pessoas} pessoas por {args.duracao} s, pausa média {args.pausa} s.\n")

    tempos: list[float] = []
    por_tela: dict[str, list[float]] = defaultdict(list)
    erros: dict[str, int] = defaultdict(int)
    trava = threading.Lock()
    fim = time.monotonic() + args.duracao

    def pessoa(numero: int) -> None:
        time.sleep(random.uniform(0, min(10, args.duracao / 4)))  # chegada gradual
        while time.monotonic() < fim:
            tela = random.choice(telas)
            try:
                status, _, url, decorrido = sessao.pedir(tela, seguir=False)
            except Exception as erro:  # rede, tempo esgotado
                with trava:
                    erros[type(erro).__name__] += 1
                continue
            nome = UUID.sub("{id}", tela.split("?")[0].replace(f"/m/{municipio}", ""))
            with trava:
                if status == 200:
                    tempos.append(decorrido)
                    por_tela[nome].append(decorrido)
                else:
                    erros[f"HTTP {status}"] += 1
            time.sleep(random.uniform(0.5, 1.5) * args.pausa)

    linhas = [threading.Thread(target=pessoa, args=(n,), daemon=True) for n in range(args.pessoas)]
    for linha in linhas:
        linha.start()
    inicio = time.monotonic()
    while any(linha.is_alive() for linha in linhas):
        time.sleep(max(0.5, min(10.0, fim - time.monotonic())))
        with trava:
            feitos, p95 = len(tempos), percentil(tempos, 95)
        print(f"  {int(time.monotonic() - inicio):>4} s: {feitos} telas abertas, 95% em até {p95:.2f} s, erros {sum(erros.values())}")
    total = max(1.0, min(time.monotonic() - inicio, float(args.duracao)))

    print("\n===== RESULTADO =====")
    print(f"Telas abertas: {len(tempos)} em {total:.0f} s ({len(tempos) / total:.1f} por segundo)")
    if tempos:
        print(f"Tempo de resposta: mediana {statistics.median(tempos):.2f} s | 95% até {percentil(tempos, 95):.2f} s | 99% até {percentil(tempos, 99):.2f} s | mais lenta {max(tempos):.2f} s")
    print(f"Erros: {dict(erros) if erros else 'nenhum'}")
    print("\nTelas mais lentas (95% até):")
    for nome, lista in sorted(por_tela.items(), key=lambda item: -percentil(item[1], 95))[:6]:
        print(f"  {percentil(lista, 95):.2f} s  {nome}  ({len(lista)} aberturas)")
    lento = percentil(tempos, 95) if tempos else 99
    erro_pct = sum(erros.values()) / max(1, len(tempos) + sum(erros.values())) * 100
    if lento <= 1.0 and erro_pct < 1:
        veredito = "CONFORTÁVEL: dá para subir o número de pessoas."
    elif lento <= 2.5 and erro_pct < 2:
        veredito = "ACEITÁVEL: perto do limite confortável."
    else:
        veredito = "PESADO: acima do que a VPS atende bem com esse número de pessoas."
    print(f"\nVeredito para {args.pessoas} pessoas: {veredito}")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit("\nInterrompido.")
