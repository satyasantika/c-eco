<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeatRelease;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Izin pindah HP: pengawas melepas kunci kursi siswa yang HP-nya bermasalah.
 *
 * Kunci lama dikosongkan dan disimpan di seat_releases. HP pertama yang
 * membuka token (selain HP lama) dalam RELEASE_MINUTES menjadi pemilik baru
 * dan melanjutkan dari butir terakhir, karena semua jawaban ada di server.
 * Bila tidak dipakai, kunci lama dipulihkan otomatis.
 */
class SeatReleaser
{
    public const RELEASE_MINUTES = 10;

    public function canRelease(User $user, TestSession $session): bool
    {
        if (! $user->canProctorExamGroups()) {
            return false;
        }

        // Akun simulasi (termasuk admin demo) tidak boleh menyentuh kursi tes asli.
        if ($user->isExamSimulationAccount()
            && $session->examGroup?->exam_simulation_id !== $user->exam_simulation_id) {
            return false;
        }

        if ($user->isPengawas()) {
            return $session->examGroup !== null && $session->examGroup->supervisor_id === $user->id;
        }

        return true;
    }

    public function blockReason(TestSession $session): ?string
    {
        if ($session->status === 'completed') {
            return 'Tes siswa ini sudah selesai.';
        }

        if ($this->activeRelease($session) !== null) {
            return 'Izin pindah HP masih berlaku.';
        }

        if ($session->resume_token === null) {
            return 'Token ini belum terikat ke HP mana pun; siswa bisa langsung membukanya.';
        }

        return null;
    }

    public function release(User $user, TestSession $session, ?string $reason = null): SeatRelease
    {
        if (! $this->canRelease($user, $session)) {
            throw new RuntimeException('Anda tidak mengawasi ruang siswa ini.');
        }

        return DB::transaction(function () use ($user, $session, $reason): SeatRelease {
            /** @var TestSession $locked */
            $locked = TestSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($why = $this->blockReason($locked)) {
                throw new RuntimeException($why);
            }

            $release = SeatRelease::query()->create([
                'test_session_id' => $locked->id,
                'released_by' => $user->id,
                'previous_token' => $locked->resume_token,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'expires_at' => Carbon::now()->addMinutes(self::RELEASE_MINUTES),
            ]);

            $locked->forceFill(['resume_token' => null])->save();

            return $release;
        });
    }

    public function activeRelease(TestSession $session): ?SeatRelease
    {
        return SeatRelease::query()
            ->where('test_session_id', $session->id)
            ->open()
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->first();
    }

    /**
     * Izin yang kedaluwarsa tanpa dipakai: pulihkan kunci HP lama.
     * Dipanggil di setiap permintaan siswa, sebelum pemeriksaan kursi.
     */
    public function settle(TestSession $session): TestSession
    {
        $expired = SeatRelease::query()
            ->where('test_session_id', $session->id)
            ->open()
            ->where('expires_at', '<=', Carbon::now())
            ->latest('id')
            ->first();

        if ($expired === null) {
            return $session;
        }

        DB::transaction(function () use ($session, $expired): void {
            TestSession::query()
                ->whereKey($session->id)
                ->whereNull('resume_token')
                ->update(['resume_token' => $expired->previous_token]);

            SeatRelease::query()
                ->where('test_session_id', $session->id)
                ->open()
                ->where('expires_at', '<=', Carbon::now())
                ->update(['restored_at' => Carbon::now()]);
        });

        return $session->fresh(['examGroup']) ?? $session;
    }

    public function markUsed(TestSession $session): void
    {
        SeatRelease::query()
            ->where('test_session_id', $session->id)
            ->open()
            ->update(['used_at' => Carbon::now()]);
    }
}
