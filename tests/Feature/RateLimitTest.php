<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

/**
 * Jaring pengaman aturan R2.
 *
 * Kalau uji ini gagal, 300 siswa di satu operator seluler akan saling
 * memblokir pada hari-H dan gejalanya tidak akan terlihat seperti rate limit.
 */
class RateLimitTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
        RateLimiter::clear('cat-state');
    }

    /** SPEC §10 uji #7. */
    public function test_one_ip_with_a_hundred_tokens_is_never_throttled(): void
    {
        $tokens = $this->issueTokens(100);

        foreach ($tokens as $token) {
            $this->withServerVariables(['REMOTE_ADDR' => '114.10.0.1'])
                ->getJson("/api/t/{$token}/state")
                ->assertOk();
        }
    }

    public function test_a_single_token_is_throttled_once_it_goes_past_its_own_budget(): void
    {
        $token = $this->issueTokens(1)[0];

        for ($i = 0; $i < 60; $i++) {
            $this->getJson("/api/t/{$token}/state")->assertOk();
        }

        $this->getJson("/api/t/{$token}/state")->assertStatus(429);
    }

    /**
     * @return list<string>
     */
    private function issueTokens(int $count): array
    {
        $tokens = [];

        for ($i = 0; $i < $count; $i++) {
            $token = str_pad((string) $i, 4, '0', STR_PAD_LEFT).'ABCD';
            $this->makeSession(token: $token);
            RateLimiter::clear('cat-state'.$token);
            $tokens[] = $token;
        }

        $this->assertSame($count, TestSession::query()->count());

        return $tokens;
    }
}
