<?php

namespace App\Support\Qr;

/** QR Code local, sem transmitir identificadores a serviços externos. */
final class VerificationQrCode
{
    public function svg(string $url): string
    {
        $qr = QRCode::getMinimumQRCode($url, 0);
        $count = $qr->getModuleCount();
        $size = $count + 8;
        $path = '';

        for ($row = 0; $row < $count; $row++) {
            for ($column = 0; $column < $count; $column++) {
                if ($qr->isDark($row, $column)) {
                    $path .= 'M'.($column + 4).' '.($row + 4).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="QR Code para verificar a declaração de matrícula" shape-rendering="crispEdges">'
            .'<rect width="'.$size.'" height="'.$size.'" fill="#fff"/>'
            .'<path fill="#000" d="'.$path.'"/></svg>';
    }
}
