<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Membersihkan kursi yatim: sesi dari jadwal yang sudah dihapus
     * (exam_group_id menjadi NULL lewat nullOnDelete) yang belum pernah
     * dipakai siswa. Kursi seperti ini hanya berisi peserta sementara
     * KURSI-{token}, jadi tidak ada data siswa yang hilang. Kursi yang pernah
     * dibuka, diisi identitas, atau dijawab tidak disentuh.
     */
    public function up(): void
    {
        $orphans = DB::table('test_sessions')
            ->join('participants', 'participants.id', '=', 'test_sessions.participant_id')
            ->whereNull('test_sessions.exam_group_id')
            ->where('participants.student_code', 'like', 'KURSI-%')
            ->where('test_sessions.status', 'pending')
            ->whereNull('test_sessions.opened_at')
            ->whereNull('test_sessions.claimed_at')
            ->whereNull('test_sessions.resume_token')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('session_items')->whereColumn('session_items.test_session_id', 'test_sessions.id'))
            ->select('test_sessions.id', 'test_sessions.participant_id')
            ->get();

        foreach ($orphans->chunk(500) as $chunk) {
            DB::table('test_sessions')->whereIn('id', $chunk->pluck('id'))->delete();
            DB::table('participants')
                ->whereIn('id', $chunk->pluck('participant_id'))
                ->where('student_code', 'like', 'KURSI-%')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('test_sessions')->whereColumn('test_sessions.participant_id', 'participants.id'))
                ->delete();
        }
    }

    public function down(): void
    {
        // Data yang dibersihkan tidak dikembalikan.
    }
};
