<?php

namespace App\Photos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto de identificação: preserva a imagem integral em 480 × 480 px e grava de
 * novo como JPEG. Regravar descarta metadados escondidos (como GPS) e
 * qualquer conteúdo que não seja imagem. O arquivo fica no disco privado.
 */
final class IdentificationPhoto
{
    public const SIZE = 480;

    public const DISK = 'local';

    public function store(UploadedFile $file, string $folder): string
    {
        return $this->storeContents((string) file_get_contents($file->getRealPath()), $folder);
    }

    /** Mesmo tratamento de store(), a partir dos bytes da imagem (ex.: lidos de um .zip). */
    public function storeContents(string $contents, string $folder): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['foto' => 'O servidor ainda não está preparado para fotos (falta a extensão GD do PHP). Avise o suporte.']);
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw ValidationException::withMessages(['foto' => 'Não consegui abrir esta imagem. Envie uma foto JPG, PNG ou WebP.']);
        }

        $image = $this->upright($image, $contents);
        $width = imagesx($image);
        $height = imagesy($image);
        if (min($width, $height) < 120) {
            imagedestroy($image);
            throw ValidationException::withMessages(['foto' => 'A foto está muito pequena. Use uma imagem com pelo menos 120 pixels de largura e altura.']);
        }

        // Encaixe proporcional: nenhum pixel de origem é eliminado por recorte.
        // A tela quadrada mantém o contrato dos cadastros e da impressão.
        $scale = self::SIZE / max($width, $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $image, intdiv(self::SIZE - $targetWidth, 2), intdiv(self::SIZE - $targetHeight, 2), 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($square, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($square);

        $path = 'fotos/'.$folder.'/'.Str::uuid().'.jpg';
        if ($jpeg === '' || ! Storage::disk(self::DISK)->put($path, $jpeg)) {
            throw ValidationException::withMessages(['foto' => 'Não foi possível armazenar a foto. A foto anterior foi preservada. Tente novamente.']);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'fotos/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Fotos de celular vêm deitadas com a orientação só no EXIF; aqui ficam em pé. */
    private function upright(\GdImage $image, string $contents): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }
}
