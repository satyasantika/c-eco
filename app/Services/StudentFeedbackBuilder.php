<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\ResponseModel;
use App\Models\SessionItem;
use App\Models\TestSession;
use App\Support\StudentFeedback;

/**
 * Menyusun Wright map dan tes informasi butir untuk halaman terakhir siswa.
 *
 * I(θ̂) dihitung pada parameter yang dipakai menilai jawaban (aturan R4),
 * bukan pada parameter aktif hari ini.
 */
class StudentFeedbackBuilder
{
    public const MAP_TOP = 28.0;

    public const MAP_HEIGHT = 300.0;

    public const MAP_WIDTH = 340.0;

    public const AXIS_X = 56.0;

    public const PERSON_X = 34.0;

    public const TRACK_START = 78.0;

    public const TRACK_GAP = 36.0;

    public function __construct(private readonly SessionReporter $reporter = new SessionReporter) {}

    public function for(TestSession $session): StudentFeedback
    {
        $report = $this->reporter->report($session);
        $ability = (float) ($session->theta ?? 0.0);
        $se = (float) ($session->se ?? 1.0);

        $rows = $session->sessionItems()
            ->whereNotNull('response_label')
            ->with([
                'item.dimension:id,code,name,display_order',
                'itemParameter',
            ])
            ->orderBy('sequence')
            ->get();

        $logits = [$ability, $ability - $se, $ability + $se];
        foreach ($rows as $row) {
            if ($row->itemParameter !== null) {
                $logits[] = (float) $row->itemParameter->b;
            }
        }

        [$min, $max] = $this->scale($logits);

        $dimensions = $this->dimensionTracks($rows);
        $trackIndex = [];
        foreach ($dimensions as $index => $dimension) {
            $trackIndex[$dimension['code']] = $index;
        }

        $maxInformation = 0.0;
        $prepared = [];

        foreach ($rows as $row) {
            $information = $this->informationAt($row, $ability);
            $maxInformation = max($maxInformation, $information);
            $prepared[] = [
                'row' => $row,
                'information' => $information,
            ];
        }

        $items = [];
        $total = 0.0;

        foreach ($prepared as $entry) {
            /** @var SessionItem $row */
            $row = $entry['row'];
            $information = $entry['information'];
            $total += $information;

            $code = (string) ($row->item?->dimension?->code ?? 'lain');
            $name = (string) ($row->item?->dimension?->name ?? 'Dimensi lain');
            $b = (float) ($row->itemParameter?->b ?? 0.0);
            $track = $trackIndex[$code] ?? 0;

            $items[] = [
                'sequence' => (int) $row->sequence,
                'dimension' => $code,
                'dimension_label' => $this->shortDimension($name),
                'information' => round($information, 3),
                'bar_width' => $maxInformation <= 0.0
                    ? 0
                    : (int) round(100 * $information / $maxInformation),
                'map_x' => self::TRACK_START + ($track * self::TRACK_GAP),
                'map_y' => self::logitToY($b, $min, $max),
            ];
        }

        $bandTop = self::logitToY($ability + $se, $min, $max);
        $bandBottom = self::logitToY($ability - $se, $min, $max);

        return new StudentFeedback(
            category: (string) $report['category'],
            provisional: (bool) $report['category_is_provisional'],
            itemsAdministered: (int) $report['items_administered'],
            precisionLabel: $this->precision($se),
            testInformation: round($total, 2),
            personY: self::logitToY($ability, $min, $max),
            bandY: $bandTop,
            bandHeight: max(8.0, $bandBottom - $bandTop),
            ticks: $this->ticks($min, $max),
            items: $items,
            dimensions: $dimensions,
        );
    }

    public static function logitToY(float $logit, float $min, float $max): float
    {
        if ($max <= $min) {
            return self::MAP_TOP + (self::MAP_HEIGHT / 2.0);
        }

        $ratio = ($max - $logit) / ($max - $min);

        return self::MAP_TOP + ($ratio * self::MAP_HEIGHT);
    }

    /**
     * @param  list<float>  $logits
     * @return array{0: float, 1: float}
     */
    private function scale(array $logits): array
    {
        $min = -3.0;
        $max = 3.0;

        foreach ($logits as $logit) {
            $min = min($min, $logit);
            $max = max($max, $logit);
        }

        $min = floor($min - 0.15);
        $max = ceil($max + 0.15);

        if ($max <= $min) {
            $max = $min + 1.0;
        }

        return [$min, $max];
    }

    /**
     * @return list<array{label: string, y: float}>
     */
    private function ticks(float $min, float $max): array
    {
        $ticks = [];

        for ($value = (int) $min; $value <= (int) $max; $value++) {
            $ticks[] = [
                'label' => (string) $value,
                'y' => self::logitToY((float) $value, $min, $max),
            ];
        }

        return $ticks;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SessionItem>  $rows
     * @return list<array{code: string, label: string}>
     */
    private function dimensionTracks($rows): array
    {
        return $rows
            ->map(fn (SessionItem $row): array => [
                'code' => (string) ($row->item?->dimension?->code ?? 'lain'),
                'label' => $this->shortDimension((string) ($row->item?->dimension?->name ?? 'Lain')),
                'order' => (int) ($row->item?->dimension?->display_order ?? 99),
            ])
            ->unique('code')
            ->sortBy('order')
            ->values()
            ->map(fn (array $row): array => [
                'code' => $row['code'],
                'label' => $row['label'],
            ])
            ->all();
    }

    private function informationAt(SessionItem $row, float $ability): float
    {
        if ($row->itemParameter === null) {
            return 0.0;
        }

        return (new ResponseModel($row->itemParameter->toCat()))->information($ability);
    }

    private function shortDimension(string $name): string
    {
        if (preg_match('/\(([^)]+)\)/', $name, $matches) === 1) {
            return $matches[1];
        }

        return $name;
    }

    private function precision(float $se): string
    {
        return match (true) {
            $se <= 0.30 => 'Sangat jelas',
            $se <= 0.40 => 'Jelas',
            $se <= 0.55 => 'Cukup jelas',
            default => 'Masih kasar',
        };
    }
}
