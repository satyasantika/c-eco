<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ExamGroup;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Sesi mana yang dihitung Monitor.
 *
 * - Pengawas: hanya ruang yang ia jaga.
 * - Akun gelombang simulasi: hanya ruang gelombangnya.
 * - Admin/operator/peneliti: tes asli saja; ruang simulasi tidak ikut
 *   (kecuali satu ruang simulasi dipilih langsung).
 *
 * Lalu filter halaman: satu jadwal tertentu, atau semua jadwal pada satu
 * tanggal (bawaan: hari ini). Tanpa ini, data uji coba lama dan simulasi
 * ikut terjumlah di KPI hari-H.
 */
final class MonitorScope
{
    /** @param  array<string, mixed>|null  $filters */
    public static function sessions(?array $filters, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $query = TestSession::query();
        $groupId = self::groupId($filters);

        self::restrictToUser($query, $user instanceof User ? $user : null, $groupId !== null);

        if ($groupId !== null) {
            return $query->where('exam_group_id', $groupId);
        }

        $date = self::date($filters);

        if ($date === null) {
            return $query;
        }

        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();

        return $query->where(fn (Builder $q): Builder => $q
            ->whereHas('examGroup', fn (Builder $g): Builder => $g->whereBetween('starts_at', [$from, $to]))
            // Token lama tanpa jadwal (terbit dari menu Peserta): pakai tanggal terbitnya.
            ->orWhere(fn (Builder $legacy): Builder => $legacy
                ->whereNull('exam_group_id')
                ->whereBetween('created_at', [$from, $to])));
    }

    /** @return array<int, string> */
    public static function groupOptions(?User $user = null): array
    {
        $user ??= auth()->user();
        $groups = ExamGroup::query()->with(['school', 'examSimulation'])->orderByDesc('starts_at')->orderBy('room');

        if ($user instanceof User && $user->isPengawas()) {
            $groups->where('supervisor_id', $user->id);
        } elseif ($user instanceof User && $user->isExamSimulationAccount()) {
            $groups->where('exam_simulation_id', $user->exam_simulation_id);
        }

        return $groups->limit(300)->get()->mapWithKeys(fn (ExamGroup $g): array => [
            $g->id => ($g->isExamSimulation() ? '[Simulasi] ' : '')
                .$g->label().' — '.$g->starts_at->timezone((string) config('app.timezone'))->translatedFormat('j M Y H:i'),
        ])->all();
    }

    public static function describe(?array $filters): string
    {
        $groupId = self::groupId($filters);

        if ($groupId !== null) {
            return 'Satu jadwal terpilih';
        }

        $date = self::date($filters);

        return $date === null
            ? 'Semua tanggal'
            : 'Jadwal tanggal '.$date->translatedFormat('j F Y');
    }

    private static function restrictToUser(Builder $query, ?User $user, bool $groupChosen): void
    {
        if ($user?->isPengawas()) {
            $query->whereHas('examGroup', fn (Builder $q): Builder => $q->where('supervisor_id', $user->id));

            return;
        }

        if ($user?->isExamSimulationAccount()) {
            $query->whereHas('examGroup', fn (Builder $q): Builder => $q->where('exam_simulation_id', $user->exam_simulation_id));

            return;
        }

        if (! $groupChosen) {
            // Tes asli: ruang bukan simulasi, atau token lama tanpa ruang.
            $query->where(fn (Builder $q): Builder => $q
                ->whereNull('exam_group_id')
                ->orWhereHas('examGroup', fn (Builder $g): Builder => $g->whereNull('exam_simulation_id')));
        }
    }

    private static function groupId(?array $filters): ?int
    {
        $id = $filters['exam_group_id'] ?? null;

        return $id === null || $id === '' ? null : (int) $id;
    }

    private static function date(?array $filters): ?Carbon
    {
        // Kunci "date" tidak ada = bawaan hari ini; ada tapi kosong = semua tanggal.
        if ($filters === null || ! array_key_exists('date', $filters)) {
            return Carbon::today();
        }

        $date = $filters['date'];

        return $date === null || $date === '' ? null : Carbon::parse((string) $date);
    }
}
