<?php

namespace Tests\Feature\Students;

use App\Photos\IdentificationPhoto;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentificationPhotoFramingTest extends TestCase
{
    public function test_preserva_as_quatro_bordas_em_retrato_paisagem_e_quadrado(): void
    {
        Storage::fake('local');

        foreach ([[600, 900, 320, 480], [900, 600, 480, 320], [600, 600, 480, 480]] as [$width, $height, $targetWidth, $targetHeight]) {
            $path = (new IdentificationPhoto)->storeContents($this->markedImage($width, $height), 'municipio-ficticio/alunos');
            $image = imagecreatefromstring(Storage::disk('local')->get($path));
            $this->assertSame(480, imagesx($image));
            $this->assertSame(480, imagesy($image));

            $x = intdiv(480 - $targetWidth, 2);
            $y = intdiv(480 - $targetHeight, 2);

            $this->assertColor($image, 240, $y + 8, [220, 30, 30]);
            $this->assertColor($image, 240, $y + $targetHeight - 9, [30, 30, 220]);
            $this->assertColor($image, $x + 8, 240, [30, 180, 30]);
            $this->assertColor($image, $x + $targetWidth - 9, 240, [220, 180, 30]);
            $this->assertColor($image, 240, 240, [100, 100, 100]);

            if ($x > 0) {
                $this->assertColor($image, 4, 240, [255, 255, 255]);
                $this->assertColor($image, 475, 240, [255, 255, 255]);
            }
            if ($y > 0) {
                $this->assertColor($image, 240, 4, [255, 255, 255]);
                $this->assertColor($image, 240, 475, [255, 255, 255]);
            }
            imagedestroy($image);
        }
    }

    public function test_falha_de_gravacao_nao_devolve_caminho_inexistente(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->expectException(ValidationException::class);
        (new IdentificationPhoto)->storeContents($this->markedImage(600, 900), 'municipio-ficticio/alunos');
    }

    public function test_respeita_orientacao_exif_antes_do_encaixe(): void
    {
        Storage::fake('local');
        $jpeg = $this->markedImage(600, 900, true);
        // EXIF sintético: TIFF little endian, um campo Orientation = 6 (90° horário).
        $exif = "Exif\x00\x00II".pack('vVv', 42, 8, 1).pack('vvVvvV', 274, 3, 1, 6, 0, 0);
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

        $path = (new IdentificationPhoto)->storeContents($jpeg, 'municipio-ficticio/alunos');
        $image = imagecreatefromstring(Storage::disk('local')->get($path));
        $this->assertColor($image, 240, 88, [30, 180, 30]);
        $this->assertColor($image, 240, 391, [220, 180, 30]);
        $this->assertColor($image, 8, 240, [30, 30, 220]);
        $this->assertColor($image, 471, 240, [220, 30, 30]);
        $this->assertColor($image, 240, 4, [255, 255, 255]);
        imagedestroy($image);
    }

    private function markedImage(int $width, int $height, bool $jpeg = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 100, 100));
        imagefilledrectangle($image, 0, 0, $width - 1, 39, imagecolorallocate($image, 220, 30, 30));
        imagefilledrectangle($image, 0, $height - 40, $width - 1, $height - 1, imagecolorallocate($image, 30, 30, 220));
        imagefilledrectangle($image, 0, 40, 39, $height - 41, imagecolorallocate($image, 30, 180, 30));
        imagefilledrectangle($image, $width - 40, 40, $width - 1, $height - 41, imagecolorallocate($image, 220, 180, 30));

        ob_start();
        if ($jpeg) {
            imagejpeg($image, null, 95);
        } else {
            imagepng($image);
        }
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    /** JPEG admite pequenas diferenças de cor decorrentes da compressão. */
    private function assertColor(\GdImage $image, int $x, int $y, array $expected): void
    {
        $pixel = imagecolorat($image, $x, $y);
        $actual = [($pixel >> 16) & 255, ($pixel >> 8) & 255, $pixel & 255];
        foreach ($expected as $index => $value) {
            $this->assertLessThanOrEqual(25, abs($actual[$index] - $value), "Cor na coordenada {$x},{$y}");
        }
    }
}
