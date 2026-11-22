<?php

namespace App\Security;

use App\Support\Qr\QRCode;

/**
 * QR Code do cadastro do segundo fator, desenhado no servidor em SVG.
 * O endereco otpauth contem a chave secreta: nunca vai para servico externo.
 */
final class TotpQrCode
{
    private const ISSUER = 'Astuti Gestão Escolar';

    /** Endereco padrao lido pelo Google Authenticator, Microsoft Authenticator e similares. */
    public function uri(string $account, string $secret): string
    {
        $label = rawurlencode(self::ISSUER).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => self::ISSUER,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<list<bool>> matriz de modulos (true = escuro) */
    public function modules(string $text): array
    {
        $qr = QRCode::getMinimumQRCode($text, 0); // 0 = nivel M de correcao (cerca de 15%)
        $count = $qr->getModuleCount();
        $matrix = [];
        for ($row = 0; $row < $count; $row++) {
            for ($col = 0; $col < $count; $col++) {
                $matrix[$row][$col] = (bool) $qr->isDark($row, $col);
            }
        }

        return $matrix;
    }

    /** SVG escalavel com margem de 4 modulos, como pede a norma. */
    public function svg(string $text): string
    {
        $matrix = $this->modules($text);
        $count = count($matrix);
        $size = $count + 8;
        $path = '';
        foreach ($matrix as $row => $cells) {
            foreach ($cells as $col => $dark) {
                if ($dark) {
                    $path .= 'M'.($col + 4).' '.($row + 4).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="QR Code para cadastrar no aplicativo autenticador" shape-rendering="crispEdges">'
            .'<rect width="'.$size.'" height="'.$size.'" fill="#fff"/>'
            .'<path fill="#000" d="'.$path.'"/></svg>';
    }
}
