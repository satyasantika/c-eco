<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Participant;
use App\Models\TestConfig;
use App\Models\TestSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;
use RuntimeException;

/**
 * Penerbitan token akses peserta.
 */
class TokenIssuer
{
    /**
     * Abjad tanpa huruf dan angka yang mudah tertukar saat disalin dari kertas:
     * 0/O, 1/I/L, 2/Z, 5/S, 8/B. Siswa mengetik ulang token ini dari slip
     * cetak, dan satu salah ketik berarti satu peserta gagal masuk.
     */
    public const ALPHABET = '34679ACDEFGHJKMNPQRTUVWXY';

    public const LENGTH = 8;

    /**
     * @param  Collection<int, Participant>  $participants
     * @return Collection<int, TestSession>
     */
    public function issue(Collection $participants, TestConfig $config): Collection
    {
        if ($participants->isEmpty()) {
            throw new RuntimeException('Tidak ada peserta yang cocok dengan penyaring itu.');
        }

        return DB::transaction(function () use ($participants, $config): Collection {
            return $participants->map(function (Participant $participant) use ($config): TestSession {
                // Satu peserta satu sesi per konfigurasi: menjalankan ulang
                // perintah ini sehari sebelum tes tidak boleh menerbitkan token
                // kedua dan membuat slip yang sudah dicetak jadi salah.
                $existing = TestSession::query()
                    ->where('participant_id', $participant->id)
                    ->where('test_config_id', $config->id)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                return TestSession::query()->create([
                    'participant_id' => $participant->id,
                    'test_config_id' => $config->id,
                    'item_bank_id' => $config->item_bank_id,
                    'access_token' => $this->uniqueToken(),
                    'rng_seed' => random_int(1, PHP_INT_MAX),
                    'status' => 'pending',
                ]);
            });
        });
    }

    public function uniqueToken(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $token = $this->token();

            if (! TestSession::query()->where('access_token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Gagal menemukan token unik setelah 100 percobaan.');
    }

    private function token(): string
    {
        $randomizer = new Randomizer;
        $alphabet = str_split(self::ALPHABET);
        $token = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $token .= $alphabet[$randomizer->getInt(0, count($alphabet) - 1)];
        }

        return $token;
    }
}
