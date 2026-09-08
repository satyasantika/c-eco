<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamGroup;
use App\Models\Participant;
use App\Models\TestSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengisi kursi token sebuah rombongan sampai mencapai capacity.
 *
 * Setiap kursi punya peserta sementara (KURSI-{token}) sampai siswa mengisi
 * identitas setelah scan QR. Token yang sudah diklaim tidak dihapus.
 */
class ExamGroupSeater
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    /**
     * @return Collection<int, TestSession>
     */
    public function fill(ExamGroup $group): Collection
    {
        if ($group->capacity < 1) {
            throw new RuntimeException('Kapasitas rombongan minimal 1.');
        }

        $config = $group->testConfig;

        if ($config === null) {
            throw new RuntimeException('Rombongan belum punya paket ujian.');
        }

        return DB::transaction(function () use ($group, $config): Collection {
            $existing = $group->testSessions()->count();
            $needed = $group->capacity - $existing;

            if ($needed <= 0) {
                return $group->testSessions()->orderBy('id')->get();
            }

            for ($i = 0; $i < $needed; $i++) {
                $token = $this->tokens->uniqueToken();

                $participant = Participant::query()->create([
                    'school_id' => $group->school_id,
                    'class_name' => '—',
                    'student_code' => 'KURSI-'.$token,
                    'display_name' => 'Belum diisi',
                ]);

                TestSession::query()->create([
                    'participant_id' => $participant->id,
                    'test_config_id' => $config->id,
                    'item_bank_id' => $config->item_bank_id,
                    'exam_group_id' => $group->id,
                    'access_token' => $token,
                    'rng_seed' => random_int(1, PHP_INT_MAX),
                    'status' => 'pending',
                ]);
            }

            return $group->testSessions()->orderBy('id')->get();
        });
    }

    public function currentUnopened(ExamGroup $group): ?TestSession
    {
        return $group->testSessions()
            ->whereNull('opened_at')
            ->orderBy('id')
            ->first();
    }
}
