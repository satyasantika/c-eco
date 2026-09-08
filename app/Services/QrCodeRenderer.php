<?php

declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Common\ErrorCorrectionLevel;

/**
 * QR dibuat di server, sebagai SVG sebaris.
 *
 * Aturan R1: tidak boleh ada layanan QR pihak ketiga. Selain soal kuota, URL
 * yang dikirim ke layanan luar berisi token akses sesi peserta — itu tidak
 * boleh meninggalkan mesin ini.
 *
 * SVG sebaris juga berarti nol permintaan HTTP tambahan saat mencetak
 * 300 slip, dan hasilnya tajam pada printer laser berapa pun resolusinya.
 */
class QrCodeRenderer
{
    public function svg(string $text, int $size = 132): string
    {
        // Tingkat koreksi galat M: slip dicetak di kertas biasa lalu difoto
        // dengan kamera ponsel di ruang kelas, kerap dengan bayangan.
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();
        $width = $matrix->getWidth();
        $quiet = 2;
        $side = $width + 2 * $quiet;

        $path = '';

        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $path .= sprintf('M%d %dh1v1h-1z', $x + $quiet, $y + $quiet);
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" '.
            'shape-rendering="crispEdges" role="img" aria-label="Kode QR tautan tes">'.
            '<rect width="%d" height="%d" fill="#fff"/><path d="%s" fill="#000"/></svg>',
            $size,
            $size,
            $side,
            $side,
            $side,
            $side,
            $path,
        );
    }
}
