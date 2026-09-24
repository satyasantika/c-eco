<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Services\ExamSimulationPurger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class ExamSimulation extends Model
{
    protected $fillable = [
        'name', 'students', 'rooms', 'starts_at',
        'grade_share_x', 'grade_share_xi', 'grade_share_xii', 'pool_size',
        'operators_count', 'pengawas_count', 'plain_password',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'is_demo' => 'boolean',
            'students' => 'integer',
            'rooms' => 'integer',
            'grade_share_x' => 'integer',
            'grade_share_xi' => 'integer',
            'grade_share_xii' => 'integer',
            'pool_size' => 'integer',
            'operators_count' => 'integer',
            'pengawas_count' => 'integer',
        ];
    }

    public function school(): HasOne
    {
        return $this->hasOne(School::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function testConfig(): HasOne
    {
        return $this->hasOne(TestConfig::class);
    }

    public function examGroups(): HasMany
    {
        return $this->hasMany(ExamGroup::class);
    }

    public function operators(): HasMany
    {
        return $this->users()->where('role', UserRole::Operator);
    }

    public function pengawas(): HasMany
    {
        return $this->users()->where('role', UserRole::Pengawas);
    }

    /** @param  Builder<self>  $query */
    public function scopeDemo(Builder $query): void
    {
        $query->where('is_demo', true);
    }

    public function label(): string
    {
        return "{$this->name} · {$this->students} siswa · {$this->rooms} kelas";
    }

    public function scaleLabel(): string
    {
        return "{$this->students} siswa di {$this->rooms} ruang";
    }

    public function mixLabel(): string
    {
        return "{$this->grade_share_x}% X, {$this->grade_share_xi}% XI, {$this->grade_share_xii}% XII";
    }

    public function staffLabel(): string
    {
        return "{$this->operators_count} operator dan {$this->pengawas_count} pengawas";
    }

    public function whenLabel(): string
    {
        return $this->starts_at
            ->timezone((string) config('app.timezone'))
            ->translatedFormat('l, d F Y').', pukul '.$this->starts_at
            ->timezone((string) config('app.timezone'))
            ->format('H:i');
    }

    public function hasStarted(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->starts_at !== null && $now->greaterThanOrEqualTo($this->starts_at);
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return true;
        }

        app(ExamSimulationPurger::class)->purge($this);
        $this->exists = false;

        return true;
    }
}
