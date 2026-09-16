<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Salinan halaman error untuk siswa dan staf.
 *
 * Halaman error tidak boleh bergantung pada Vite: 500 hari-H sering justru
 * karena aset belum ter-build. Keterangan yang ditampilkan adalah pesan yang
 * aman (bukan jejak tumpukan atau SQL).
 */
final readonly class HttpErrorPage
{
    /**
     * @var list<string>
     */
    private const GENERIC = [
        'not found',
        'server error',
        'internal server error',
        'forbidden',
        'unauthorized',
        'page expired',
        'too many requests',
        'service unavailable',
        'this action is unauthorized.',
        'unauthenticated.',
        'no query results for model',
        'whoops, looks like something went wrong.',
        'csrf token mismatch',
        'the page has expired due to inactivity',
        'http exception',
        'could not be found',
        'too many attempts',
    ];

    public function __construct(
        public int $code,
        public string $title,
        public string $lede,
        public string $next,
        public ?string $detail,
        public string $method,
        public string $path,
        public string $happenedAt,
        public ?string $debug,
        public bool $student,
        public bool $staff,
    ) {}

    public static function from(Throwable $exception, ?Request $request = null): self
    {
        $request ??= request();
        $code = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        $student = self::isStudentPath($request);
        $staff = self::isStaffPath($request);
        $copy = self::copy($code, $student, $staff);

        return new self(
            code: $code,
            title: $copy['title'],
            lede: $copy['lede'],
            next: $copy['next'],
            detail: self::publicDetail($exception, $request),
            method: strtoupper($request->getMethod()),
            path: $request->getPathInfo() === '' ? '/' : $request->getPathInfo(),
            happenedAt: now()->timezone((string) config('app.timezone', 'Asia/Jakarta'))
                ->format('d/m/Y H:i:s').' WIB',
            debug: config('app.debug') && ! $student ? self::debugLine($exception) : null,
            student: $student,
            staff: $staff,
        );
    }

    /**
     * @return array{title: string, lede: string, next: string}
     */
    private static function copy(int $code, bool $student, bool $staff): array
    {
        if ($code === 404 && $student) {
            return [
                'title' => 'Token tidak ketemu',
                'lede' => 'Delapan huruf di tautan ini tidak ada di bank sesi. Bukan salah HP-mu, dan jawaban yang sudah tercatat tidak hilang karena tautan ini memang tidak membuka sesi.',
                'next' => 'Angkat tangan. Pengawas punya slip cadangan atau kode QR berikutnya. Jangan menebak token teman.',
            ];
        }

        return match ($code) {
            401 => [
                'title' => 'Belum masuk panel',
                'lede' => 'Halaman ini hanya untuk pengawas dan staf yang sudah masuk. Siswa tidak memakai surel — siswa memakai token delapan huruf.',
                'next' => 'Masuk panel dengan surel yang diberikan operator, atau kembali ke halaman depan untuk mengetik token tes.',
            ],
            403 => [
                'title' => 'Tidak boleh dibuka',
                'lede' => 'Akun yang sedang masuk tidak punya hak untuk halaman ini. Itu pembatasan peran, bukan kerusakan sistem.',
                'next' => $staff
                    ? 'Kembali ke monitor, atau minta operator/admin membuka halaman itu.'
                    : 'Kembali ke halaman yang tadi, atau masuk dengan akun yang sesuai peran.',
            ],
            404 => [
                'title' => 'Halaman tidak ada',
                'lede' => 'Alamat itu tidak ada di C-ECO. Tautan mungkin terpotong, salah ketik, atau tes sudah dihapus dari bank sesi.',
                'next' => 'Buka halaman depan, ketik delapan huruf dari slip, atau minta QR baru ke pengawas.',
            ],
            419 => [
                'title' => 'Sesi formulir kedaluwarsa',
                'lede' => 'Formulir ini terlalu lama terbuka, atau halaman dimuat ulang dari tombol kembali. Tes C-ECO tidak memakai navigasi mundur.',
                'next' => 'Muat ulang halaman, lalu kirim lagi. Jawaban yang sudah tercatat di server tidak hilang.',
            ],
            429 => [
                'title' => 'Terlalu banyak permintaan',
                'lede' => 'Token sesi ini mengirim lebih cepat dari batas. Pembatas dikunci ke token, bukan ke IP satu kelas, supaya 300 siswa satu operator tidak saling memblokir.',
                'next' => 'Tunggu sekitar satu menit. Jangan tutup tab tes, lalu lanjut dari layar yang sama.',
            ],
            503 => [
                'title' => 'Sistem sedang tidak menerima',
                'lede' => 'Server sedang pemeliharaan atau kelebihan beban. Ini bukan salah token atau HP siswa.',
                'next' => 'Tunggu instruksi pengawas. Jangan pindah ke tautan lain dan jangan ketik token berulang-ulang.',
            ],
            default => [
                'title' => 'Server gagal memproses',
                'lede' => 'C-ECO menemui kesalahan di server. Jawaban yang sudah tersimpan tidak dihapus hanya karena layar ini muncul.',
                'next' => $student
                    ? 'Jangan tutup tab tes. Kabari pengawas, sebutkan kode dan waktu di bawah.'
                    : 'Catat kode, alamat, dan waktu di bawah, lalu coba lagi. Kalau berulang, cek log server.',
            ],
        };
    }

    private static function publicDetail(Throwable $exception, Request $request): ?string
    {
        $fromModel = self::missingModelDetail($exception);
        if ($fromModel !== null) {
            if (self::isStudentPath($request) && self::missingModel($exception) === 'TestSession') {
                return 'Sesi tes dengan token pada tautan ini tidak ada. Cek delapan huruf di slip, jangan dari ingatan.';
            }

            return $fromModel;
        }

        $message = self::usableMessage($exception);
        if ($message === null) {
            return null;
        }

        if (mb_strlen($message) > 400) {
            return mb_substr($message, 0, 400).'…';
        }

        return $message;
    }

    private static function missingModel(Throwable $exception): ?string
    {
        foreach (self::chain($exception) as $item) {
            if ($item instanceof ModelNotFoundException) {
                return class_basename((string) $item->getModel());
            }
        }

        if (preg_match('/No query results for model \[([^\]]+)\]/i', $exception->getMessage(), $match) === 1) {
            return class_basename($match[1]);
        }

        return null;
    }

    private static function missingModelDetail(Throwable $exception): ?string
    {
        $model = self::missingModel($exception);

        return match ($model) {
            'TestSession' => 'Sesi tes yang diminta tidak ada.',
            'Item', 'ItemBank', 'ItemParameter' => 'Butir atau bank soal yang diminta tidak ada.',
            'Participant' => 'Peserta yang diminta tidak ada.',
            'ExamGroup' => 'Rombongan ujian yang diminta tidak ada.',
            'ExamSimulation' => 'Simulasi ujian yang diminta tidak ada.',
            'User' => 'Pengguna yang diminta tidak ada.',
            null => null,
            default => 'Data '.$model.' yang diminta tidak ada.',
        };
    }

    private static function usableMessage(Throwable $exception): ?string
    {
        foreach (self::chain($exception) as $item) {
            $message = trim($item->getMessage());
            if ($message === '' || self::isGeneric($message)) {
                continue;
            }

            if (self::looksLikeInternals($message) && ! config('app.debug')) {
                continue;
            }

            return $message;
        }

        return null;
    }

    /**
     * @return list<Throwable>
     */
    private static function chain(Throwable $exception): array
    {
        $chain = [];
        $current = $exception;
        while ($current instanceof Throwable) {
            $chain[] = $current;
            $current = $current->getPrevious();
        }

        return $chain;
    }

    private static function isGeneric(string $message): bool
    {
        $normalized = strtolower($message);

        foreach (self::GENERIC as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function looksLikeInternals(string $message): bool
    {
        return str_contains($message, 'SQLSTATE')
            || str_contains($message, 'vendor/')
            || str_contains($message, '/home/')
            || str_contains($message, 'stack trace')
            || str_contains($message, 'PDO')
            || preg_match('/(?:\\\\|\/)[\w.-]+\.php:\d+/', $message) === 1;
    }

    private static function debugLine(Throwable $exception): string
    {
        $root = $exception->getPrevious() ?? $exception;
        $file = str_replace('\\', '/', $root->getFile());
        $base = str_replace('\\', '/', base_path());
        if (str_starts_with($file, $base)) {
            $file = ltrim(substr($file, strlen($base)), '/');
        }

        return $root::class.' · '.$file.':'.$root->getLine();
    }

    private static function isStudentPath(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/t/');
    }

    private static function isStaffPath(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, '/admin')
            || str_starts_with($path, '/awas')
            || $path === '/login';
    }
}
