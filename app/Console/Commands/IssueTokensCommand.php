<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\TestConfig;
use App\Services\TokenIssuer;
use Illuminate\Console\Command;
use RuntimeException;

class IssueTokensCommand extends Command
{
    protected $signature = 'cat:issue-tokens
        {--school= : Nama sekolah, boleh sebagian}
        {--class= : Nama kelas, boleh sebagian}
        {--config= : Id test_config; wajib bila jenjang tidak jelas dari kelas}
        {--grade= : Jenjang X, XI, atau XII, dipakai bila --config kosong}';

    protected $description = 'Menerbitkan token akses untuk peserta yang belum punya sesi';

    public function handle(TokenIssuer $issuer): int
    {
        try {
            $config = $this->config();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $participants = Participant::query()
            ->when($this->option('school'), fn ($q, $name) => $q->whereRelation('school', 'name', 'like', "%{$name}%"))
            ->when($this->option('class'), fn ($q, $class) => $q->where('class_name', 'like', "%{$class}%"))
            ->orderBy('class_name')
            ->orderBy('display_name')
            ->get();

        try {
            $sessions = $issuer->issue($participants, $config);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $fresh = $sessions->filter(fn ($session): bool => $session->wasRecentlyCreated)->count();

        $this->table(
            ['Kelas', 'Nama', 'Token'],
            $sessions->map(fn ($session): array => [
                $session->participant->class_name,
                $session->participant->display_name,
                $session->access_token,
            ])->all(),
        );

        $this->info("{$fresh} token baru, ".($sessions->count() - $fresh).' peserta sudah punya sesi.');
        $this->info('Cetak slip: '.route('admin.slips', ['config' => $config->id]));

        return self::SUCCESS;
    }

    private function config(): TestConfig
    {
        if ($this->option('config') !== null) {
            return TestConfig::query()->findOrFail((int) $this->option('config'));
        }

        $grade = $this->option('grade');

        if ($grade === null) {
            throw new RuntimeException('Sebutkan --config atau --grade.');
        }

        $config = TestConfig::query()
            ->whereRelation('itemBank', 'grade', (string) $grade)
            ->where('is_active', true)
            ->first();

        if ($config === null) {
            throw new RuntimeException("Tidak ada test_config aktif untuk jenjang {$grade}.");
        }

        return $config;
    }
}
