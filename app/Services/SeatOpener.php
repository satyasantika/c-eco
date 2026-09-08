<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TestSession;
use Illuminate\Support\Carbon;

/**
 * Menandai kursi QR sudah dimuat di HP siswa.
 *
 * Pengawas berganti QR pada opened_at, bukan claimed_at, supaya antrian
 * di kelas tidak menunggu siswa selesai mengisi identitas.
 */
class SeatOpener
{
    public function markOpened(TestSession $session): TestSession
    {
        if ($session->exam_group_id === null || $session->opened_at !== null) {
            return $session;
        }

        TestSession::query()
            ->whereKey($session->id)
            ->whereNull('opened_at')
            ->update(['opened_at' => Carbon::now()]);

        return $session->fresh() ?? $session;
    }
};
