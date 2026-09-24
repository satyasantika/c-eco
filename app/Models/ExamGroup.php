<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ExamGroup extends Model
{
    protected $fillable = [
        'school_id', 'name', 'room', 'starts_at',
        'supervisor_id', 'test_config_id', 'capacity', 'notes',
        'exam_simulation_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Jalur mana pun yang menghapus jadwal (menu, simulasi, tinker) tidak
        // boleh meninggalkan token kursi kosong yang masih bisa dipindai dan
        // terhitung "Belum mulai" di Monitor.
        static::deleting(function (ExamGroup $group): void {
            $unused = $group->testSessions()
                ->where('status', 'pending')
                ->whereNull('opened_at')
                ->whereNull('claimed_at')
                ->whereNull('resume_token')
                ->whereDoesntHave('sessionItems')
                ->get(['id', 'participant_id']);

            if ($unused->isEmpty()) {
                return;
            }

            TestSession::query()->whereIn('id', $unused->pluck('id'))->delete();
            Participant::query()
                ->whereIn('id', $unused->pluck('participant_id'))
                ->where('student_code', 'like', 'KURSI-%')
                ->whereDoesntHave('testSessions')
                ->delete();
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function testConfig(): BelongsTo
    {
        return $this->belongsTo(TestConfig::class);
    }

    public function examSimulation(): BelongsTo
    {
        return $this->belongsTo(ExamSimulation::class);
    }

    public function isExamSimulation(): bool
    {
        return $this->exam_simulation_id !== null;
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }

    public function claimedCount(): int
    {
        return $this->testSessions()->whereNotNull('claimed_at')->count();
    }

    public function openedCount(): int
    {
        return $this->testSessions()->whereNotNull('opened_at')->count();
    }

    public function seatCount(): int
    {
        return $this->testSessions()->count();
    }

    public function label(): string
    {
        return "{$this->school?->name} · {$this->room} · ".$this->starts_at?->timezone(config('app.timezone'))->format('d M H:i');
    }

    /** Jam HP siswa tidak dipakai. Pembandingnya selalu waktu server. */
    public function hasStarted(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->starts_at !== null && $now->greaterThanOrEqualTo($this->starts_at);
    }
}
