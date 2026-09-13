<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamGroup;
use App\Models\ExamSimulation;
use App\Models\Item;
use App\Models\ItemBank;
use App\Models\ItemParameter;
use App\Models\Participant;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus satu gelombang simulasi tanpa menyentuh tes asli atau bank soal.
 */
class ExamSimulationPurger
{
    public function purge(ExamSimulation $simulation): void
    {
        $id = $simulation->id;

        DB::transaction(function () use ($simulation, $id): void {
            $groupIds = ExamGroup::query()->where('exam_simulation_id', $id)->pluck('id');
            $sessionIds = TestSession::query()->whereIn('exam_group_id', $groupIds)->pluck('id');
            $participantIds = TestSession::query()->whereIn('id', $sessionIds)->pluck('participant_id');
            $configIds = TestConfig::query()->where('exam_simulation_id', $id)->pluck('id');
            $schoolIds = School::query()->where('exam_simulation_id', $id)->pluck('id');

            TestSession::query()->whereIn('id', $sessionIds)->delete();
            TestSession::query()->whereIn('test_config_id', $configIds)->delete();
            ExamGroup::query()->whereIn('id', $groupIds)->delete();
            Participant::query()->whereIn('id', $participantIds)->delete();
            Participant::query()->whereIn('school_id', $schoolIds)->delete();
            TestConfig::query()->whereIn('id', $configIds)->delete();
            User::query()->where('exam_simulation_id', $id)->delete();
            School::query()->whereIn('id', $schoolIds)->delete();

            DB::table('exam_simulations')->where('id', $id)->delete();
        });
    }

    /** @return array{items: int, banks: int, parameters: int} */
    public static function inventorySnapshot(): array
    {
        return [
            'items' => Item::query()->count(),
            'banks' => ItemBank::query()->count(),
            'parameters' => ItemParameter::query()->count(),
        ];
    }
}
