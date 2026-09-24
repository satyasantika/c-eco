<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamGroup;
use App\Models\Participant;
use App\Models\TestConfig;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengubah atau menghapus jadwal (rombongan) yang belum berjalan.
 *
 * Jadwal terkunci begitu jam mulai lewat atau ada satu kursi terpakai
 * (dibuka, diisi identitas, terikat HP, atau sudah menjawab). Sebelum itu
 * kursinya hanya berisi peserta sementara KURSI-{token}, jadi paket bisa
 * diganti dan jadwal bisa dihapus tanpa menyentuh data siswa.
 */
class ExamGroupPlanner
{
    public function __construct(private readonly ExamGroupSeater $seater) {}

    public function usedSeats(ExamGroup $group): int
    {
        return $group->testSessions()
            ->where(fn ($q) => $q->whereNotNull('opened_at')
                ->orWhereNotNull('claimed_at')
                ->orWhereNotNull('resume_token')
                ->orWhere('status', '!=', 'pending'))
            ->count();
    }

    public function lockReason(ExamGroup $group): ?string
    {
        if ($group->isExamSimulation()) {
            return 'Jadwal ini milik gelombang simulasi; ubah lewat menu Simulasi.';
        }

        if ($group->hasStarted()) {
            return 'Jam pelaksanaan sudah lewat.';
        }

        $used = $this->usedSeats($group);

        return $used > 0 ? "{$used} kursi sudah dipakai siswa." : null;
    }

    public function isLocked(ExamGroup $group): bool
    {
        return $this->lockReason($group) !== null;
    }

    /**
     * Terapkan paket dan kapasitas yang baru disimpan ke kursi.
     * Dipanggil setelah form menyimpan; $previousConfigId = paket sebelum disunting.
     */
    public function sync(ExamGroup $group, ?int $previousConfigId): void
    {
        DB::transaction(function () use ($group, $previousConfigId): void {
            if ($previousConfigId !== null && $previousConfigId !== $group->test_config_id) {
                $this->applyPackage($group);
            }

            $this->trim($group);
            $this->seater->fill($group);
        });
    }

    public function delete(ExamGroup $group): void
    {
        if ($reason = $this->lockReason($group)) {
            throw new RuntimeException('Jadwal tidak bisa dihapus. '.$reason);
        }

        DB::transaction(function () use ($group): void {
            $participantIds = $group->testSessions()->pluck('participant_id');

            // Kursi hanya berisi peserta sementara; sesi ikut terhapus lewat cascade participant.
            Participant::query()
                ->whereIn('id', $participantIds)
                ->where('student_code', 'like', 'KURSI-%')
                ->delete();
            $group->testSessions()->delete();
            $group->delete();
        });
    }

    private function applyPackage(ExamGroup $group): void
    {
        if ($reason = $this->lockReason($group)) {
            throw new RuntimeException('Paket tidak bisa diganti. '.$reason);
        }

        /** @var TestConfig $config */
        $config = TestConfig::query()->findOrFail($group->test_config_id);

        $group->testSessions()->update([
            'test_config_id' => $config->id,
            'item_bank_id' => $config->item_bank_id,
        ]);
    }

    /** Kapasitas dikurangi: buang kursi kosong dari ujung antrean. */
    private function trim(ExamGroup $group): void
    {
        $excess = $group->testSessions()->count() - $group->capacity;

        if ($excess <= 0) {
            return;
        }

        $free = $group->testSessions()
            ->where('status', 'pending')
            ->whereNull('opened_at')
            ->whereNull('claimed_at')
            ->whereNull('resume_token')
            ->orderByDesc('id')
            ->limit($excess)
            ->get();

        if ($free->count() < $excess) {
            throw new RuntimeException('Kapasitas tidak bisa dikurangi sebanyak itu: kursi yang sudah dipakai tidak dihapus.');
        }

        Participant::query()
            ->whereIn('id', $free->pluck('participant_id'))
            ->where('student_code', 'like', 'KURSI-%')
            ->delete();
        $group->testSessions()->whereIn('id', $free->pluck('id'))->delete();
    }
}
