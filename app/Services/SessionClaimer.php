<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Participant;
use App\Models\TestSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengikat kursi token rombongan ke identitas siswa setelah scan QR.
 */
class SessionClaimer
{
    /**
     * @param  array{student_code: string, display_name: string, grade: string, class_name: string}  $identity
     */
    public function claim(TestSession $session, array $identity): TestSession
    {
        $code = strtoupper(trim($identity['student_code']));
        $name = trim($identity['display_name']);
        $grade = strtoupper(trim($identity['grade']));
        $kelas = trim($identity['class_name']);

        if ($code === '' || $name === '' || $kelas === '') {
            throw new RuntimeException('Nomor induk, nama, dan kelas wajib diisi.');
        }

        if (! in_array($grade, ['X', 'XI', 'XII'], true)) {
            throw new RuntimeException('Jenjang harus X, XI, atau XII.');
        }

        $className = str_starts_with($kelas, $grade) ? $kelas : $grade.' '.$kelas;

        return DB::transaction(function () use ($session, $code, $name, $className): TestSession {
            $session = TestSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $session->load(['examGroup', 'participant']);

            if (! $session->isUnclaimed()) {
                if ($session->participant?->student_code === $code) {
                    return $session;
                }

                throw new RuntimeException('Token ini sudah dipakai siswa lain. Minta kode QR berikutnya ke pengawas.');
            }

            if (! $session->examWindowOpen()) {
                throw new RuntimeException('Tes belum dimulai. Tunggu jam pelaksanaan yang ditetapkan operator.');
            }

            $schoolId = $session->examGroup?->school_id ?? $session->participant->school_id;
            $placeholder = $session->participant;

            $existing = Participant::query()
                ->where('school_id', $schoolId)
                ->where('student_code', $code)
                ->first();

            if ($existing !== null) {
                $already = TestSession::query()
                    ->where('participant_id', $existing->id)
                    ->where('test_config_id', $session->test_config_id)
                    ->where('id', '!=', $session->id)
                    ->where(function ($q): void {
                        $q->whereNotNull('claimed_at')
                            ->orWhereIn('status', ['in_progress', 'completed']);
                    })
                    ->exists();

                if ($already) {
                    throw new RuntimeException('Nomor induk itu sudah terdaftar pada paket ujian ini.');
                }

                $existing->forceFill([
                    'display_name' => $name,
                    'class_name' => $className,
                ])->save();

                $session->forceFill([
                    'participant_id' => $existing->id,
                    'claimed_at' => Carbon::now(),
                    'opened_at' => $session->opened_at ?? Carbon::now(),
                ])->save();

                if ($placeholder !== null && $placeholder->id !== $existing->id && str_starts_with((string) $placeholder->student_code, 'KURSI-')) {
                    $placeholder->delete();
                }

                return $session->fresh(['participant', 'examGroup']);
            }

            $placeholder->forceFill([
                'student_code' => $code,
                'display_name' => $name,
                'class_name' => $className,
            ])->save();

            $session->forceFill([
                'claimed_at' => Carbon::now(),
                'opened_at' => $session->opened_at ?? Carbon::now(),
            ])->save();

            return $session->fresh(['participant', 'examGroup']);
        });
    }
}
