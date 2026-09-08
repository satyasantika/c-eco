<?php

declare(strict_types=1);

namespace App\CAT;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pemilihan butir, SPEC §3.
 *
 * Urutan penyaringan: kandidat tersisa → penyeimbang isi → kriteria informasi
 * → kontrol eksposur randomesque.
 */
final readonly class ItemSelector
{
    /** Lima butir pertama memakai MPWI: θ̂ masih kasar, posterior lebih dipercaya. */
    private const MPWI_UNTIL_SEQUENCE = 5;

    /** Butir pertama dibatasi ke tengah skala bila memungkinkan (SPEC §3). */
    private const FIRST_ITEM_MAX_ABS_B = 1.0;

    public function __construct(
        private ContentBalancer $balancer,
        private EapEstimator $estimator = new EapEstimator,
        private QuadratureGrid $grid = new QuadratureGrid,
        private int $firstItemK = 5,
        private int $laterK = 3,
    ) {}

    /**
     * @param  list<Candidate>  $candidates  butir yang belum disajikan di sesi ini
     * @param  list<Response>  $responses  jawaban sejauh ini
     * @param  list<string>  $administeredDimensions  dimensi butir yang sudah disajikan
     * @param  int  $sequence  nomor butir yang sedang dipilih, mulai dari 1
     */
    public function select(
        array $candidates,
        array $responses,
        array $administeredDimensions,
        int $sequence,
        int $rngSeed,
    ): SelectionResult {
        if ($candidates === []) {
            throw new NoCandidateException('Bank habis: tidak ada butir tersisa untuk sesi ini.');
        }

        // Butir pertama tidak melewati penyeimbang isi: belum ada butir tersaji,
        // jadi proporsi amatannya 0/0 dan setiap sesi akan memilih dimensi yang
        // sama. Membiarkannya akan menyempitkan kolam butir pertama sampai
        // di bawah k = 5 dan memusatkan eksposur butir pembuka pada segelintir
        // butir. Lihat SPEC §3.
        if ($sequence === 1) {
            $pool = $this->restrictToMidRange($candidates);
            $dimension = null;
        } else {
            [$pool, $dimension] = $this->restrictToNeediestDimension($candidates, $administeredDimensions);
        }

        $useMpwi = $sequence <= self::MPWI_UNTIL_SEQUENCE;
        $scored = $useMpwi
            ? $this->scoreByPosteriorWeightedInformation($pool, $responses)
            : $this->scoreByInformationAtTheta($pool, $this->estimator->estimate($responses)->theta);

        $k = min($sequence === 1 ? $this->firstItemK : $this->laterK, count($scored));
        $top = array_slice($scored, 0, $k);

        $chosen = $top[$this->drawIndex($k, $sequence, $rngSeed)];

        return new SelectionResult(
            candidate: $chosen['candidate'],
            information: $chosen['information'],
            selectionRule: sprintf('%s+randomesque-k%d', $useMpwi ? 'MPWI' : 'MFI', $k),
            candidatePool: array_map(
                static fn (array $row): array => [
                    'item_id' => $row['candidate']->itemId,
                    'information' => $row['information'],
                ],
                $top
            ),
            balancedDimension: $dimension ?? $chosen['candidate']->dimension,
        );
    }

    /**
     * @param  list<Candidate>  $candidates
     * @return list<Candidate>
     */
    private function restrictToMidRange(array $candidates): array
    {
        $middle = array_values(array_filter(
            $candidates,
            static fn (Candidate $c): bool => abs($c->parameter->b) <= self::FIRST_ITEM_MAX_ABS_B
        ));

        return $middle === [] ? $candidates : $middle;
    }

    /**
     * @param  list<Candidate>  $candidates
     * @param  list<string>  $administeredDimensions
     * @return array{0: list<Candidate>, 1: string}
     */
    private function restrictToNeediestDimension(array $candidates, array $administeredDimensions): array
    {
        foreach ($this->balancer->orderedByDeficit($administeredDimensions) as $dimension) {
            $subset = array_values(array_filter(
                $candidates,
                static fn (Candidate $c): bool => $c->dimension === $dimension
            ));

            if ($subset !== []) {
                return [$subset, $dimension];
            }
        }

        // Dimensi di luar target (bank berubah di tengah jalan): jangan menolak
        // menyajikan butir hanya karena penyeimbang tidak mengenalinya.
        return [$candidates, $candidates[0]->dimension];
    }

    /**
     * MPWI: Σ_q I_i(θ_q) · w_q memakai posterior berjalan.
     *
     * @param  list<Candidate>  $candidates
     * @param  list<Response>  $responses
     * @return list<array{candidate: Candidate, information: float}>
     */
    private function scoreByPosteriorWeightedInformation(array $candidates, array $responses): array
    {
        $posterior = $this->estimator->posterior($responses);
        $points = $this->grid->points();

        return $this->sortDescending(array_map(
            static function (Candidate $candidate) use ($posterior, $points): array {
                $model = new ResponseModel($candidate->parameter);
                $information = 0.0;

                foreach ($points as $q => $theta) {
                    $information += $model->information($theta) * $posterior[$q];
                }

                return ['candidate' => $candidate, 'information' => $information];
            },
            $candidates
        ));
    }

    /**
     * @param  list<Candidate>  $candidates
     * @return list<array{candidate: Candidate, information: float}>
     */
    private function scoreByInformationAtTheta(array $candidates, float $theta): array
    {
        return $this->sortDescending(array_map(
            static fn (Candidate $candidate): array => [
                'candidate' => $candidate,
                'information' => (new ResponseModel($candidate->parameter))->information($theta),
            ],
            $candidates
        ));
    }

    /**
     * @param  list<array{candidate: Candidate, information: float}>  $scored
     * @return list<array{candidate: Candidate, information: float}>
     */
    private function sortDescending(array $scored): array
    {
        // item_id sebagai pemecah seri: dua butir dengan informasi identik harus
        // selalu diurutkan sama, kalau tidak sesi berseed sama bisa menyimpang.
        usort($scored, static fn (array $x, array $y): int => $y['information'] <=> $x['information']
            ?: $x['candidate']->itemId <=> $y['candidate']->itemId);

        return $scored;
    }

    /**
     * Satu aliran RNG per sesi, dimajukan sebanyak butir yang sudah dipilih.
     * Prosesnya harus tanpa keadaan — tiap permintaan HTTP adalah proses baru —
     * tetapi hasilnya harus sama persis dengan satu aliran berkelanjutan, supaya
     * sesi dapat direproduksi dari rng_seed saja.
     */
    private function drawIndex(int $k, int $sequence, int $rngSeed): int
    {
        $randomizer = new Randomizer(new Mt19937($rngSeed));

        for ($i = 1; $i < $sequence; $i++) {
            $randomizer->nextInt();
        }

        return $randomizer->getInt(0, $k - 1);
    }
}
