"""Gera os documentos ficticios de teste do OCR a partir do gabarito do kit.

Uso: python3 gerar_documentos.py <pasta com alunos.csv> <pasta de saida>
Saidas: lista_impressa_E02.pdf (escaneado, so imagem), foto_lista_E03.jpg
(foto de celular) e lista_digital_E01.pdf (PDF com texto). Dados 100% ficticios.
"""
import csv, random, sys
from datetime import date
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter, ImageFont

src, out = Path(sys.argv[1]), Path(sys.argv[2])
out.mkdir(parents=True, exist_ok=True)
random.seed(2027)
alunos = list(csv.DictReader(open(src / 'alunos.csv', encoding='utf-8')))
por_escola = lambda e: sorted([a for a in alunos if a['escola_id'] == e], key=lambda a: (a['turma_id'], a['nome']))
FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
br = lambda iso: date.fromisoformat(iso).strftime('%d/%m/%Y')


def tabela(titulo, colunas, linhas, larguras, tam=26, pagina_linhas=30):
    """Desenha paginas A4 a 200 dpi com grade, como uma lista impressa."""
    paginas = []
    for inicio in range(0, len(linhas), pagina_linhas):
        img = Image.new('L', (1654, 2339), 255)
        d = ImageDraw.Draw(img)
        f, fb = ImageFont.truetype(FONT, tam), ImageFont.truetype(BOLD, tam)
        d.text((120, 110), titulo, font=ImageFont.truetype(BOLD, 34), fill=0)
        d.text((120, 165), f'Pagina {inicio // pagina_linhas + 1}', font=f, fill=60)
        y, alt = 240, 52
        xs = [120]
        for w in larguras:
            xs.append(xs[-1] + w)
        for i, c in enumerate(colunas):
            d.text((xs[i] + 10, y + 12), c, font=fb, fill=0)
        d.rectangle([xs[0], y, xs[-1], y + alt], outline=0, width=2)
        y += alt
        for linha in linhas[inicio:inicio + pagina_linhas]:
            for i, v in enumerate(linha):
                d.text((xs[i] + 10, y + 12), v, font=f, fill=0)
            d.line([xs[0], y + alt, xs[-1], y + alt], fill=90, width=1)
            y += alt
        for x in xs:
            d.line([x, 240, x, y], fill=90, width=1)
        paginas.append(img)
    return paginas


def escanear(img, angulo):
    img = img.rotate(angulo, resample=Image.BICUBIC, expand=False, fillcolor=255)
    ruido = Image.effect_noise(img.size, 18).point(lambda p: 255 if p > 120 else 235)
    img = Image.composite(img, ruido, img.point(lambda p: 255 if p < 200 else 0))
    return img.filter(ImageFilter.GaussianBlur(0.6))


# 1) EMEF: lista impressa e escaneada (PDF so com imagem, 2 paginas).
e02 = por_escola('E02')
linhas = [[str(i + 1), a['nome'], a['ra_ficticio'], br(a['data_nascimento']), a['turma_id'].split('-', 1)[1][0] + 'º ' + a['turma_id'][-1]]
          for i, a in enumerate(e02)]
pags = [escanear(p, random.choice([-0.8, 0.6])) for p in tabela('EMEF Prof. Sebastiao Arantes - Relacao de alunos 2027', ['Nº', 'Nome do aluno', 'RA', 'Nascimento', 'Turma'], linhas, [80, 620, 360, 230, 140])]
pags[0].save(out / 'lista_impressa_E02.pdf', save_all=True, append_images=pags[1:], resolution=200)

# 2) Escola rural: foto de celular da lista da manha (inclinada, luz desigual, JPEG).
e03 = [a for a in por_escola('E03') if a['turma_id'] == 'E03-MULTI-1']
linhas = [[a['nome'], a['serie'], br(a['data_nascimento']), 'MULTI-1'] for a in e03]
img = tabela('Escola Rural Bairro das Palmeiras - Turma da manha', ['Aluno', 'Ano', 'Nascimento', 'Turma'], linhas, [700, 200, 260, 220], tam=28)[0]
img = img.crop((60, 60, 1500, 300 + 52 * (len(linhas) + 2))).rotate(1.8, resample=Image.BICUBIC, expand=True, fillcolor=200)
luz = Image.linear_gradient('L').resize(img.size).point(lambda p: 255 - p // 5)
img = Image.composite(img, luz, img.point(lambda p: 255 if p < 128 else 0)).convert('RGB')
img.save(out / 'foto_lista_E03.jpg', quality=72)

# 3) EMEI: PDF digital com texto (exportado de um sistema), sem OCR.
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
pdfmetrics.registerFont(TTFont('DejaVu', FONT))
pdfmetrics.registerFont(TTFont('DejaVuB', BOLD))
c = canvas.Canvas(str(out / 'lista_digital_E01.pdf'), pagesize=A4)
e01 = por_escola('E01')
for inicio in range(0, len(e01), 32):
    c.setFont('DejaVuB', 13)
    c.drawString(50, 800, 'EMEI Profª Aurora Benedita Lemes - Crianças matriculadas 2027')
    y = 760
    c.setFont('DejaVuB', 10)
    for x, t in ((50, 'Nome da criança'), (330, 'D.N.'), (430, 'Turma')):
        c.drawString(x, y, t)
    c.setFont('DejaVu', 10)
    for a in e01[inicio:inicio + 32]:
        y -= 21
        c.drawString(50, y, a['nome'])
        c.drawString(330, y, br(a['data_nascimento']))
        c.drawString(430, y, a['turma_id'].split('-', 1)[1])
    c.showPage()
c.save()
print('ok', len(e02), len(e03), len(e01))
