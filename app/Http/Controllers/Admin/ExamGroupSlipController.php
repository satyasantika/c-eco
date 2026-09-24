<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamGroup;
use App\Models\TestSession;
use App\Models\User;
use App\Services\QrCodeRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Slip QR per kursi rombongan, boleh dicetak kapan saja sebelum hari-H.
 *
 * Mencetak hanya membaca token: tidak mengisi opened_at, claimed_at, atau
 * resume_token. Pintu masuk siswa tetap dijaga examWindowOpen(), jadi slip
 * yang dipindai sebelum starts_at hanya menampilkan "Tes belum dimulai".
 */
class ExamGroupSlipController extends Controller
{
    public function __construct(private readonly QrCodeRenderer $qr) {}

    public function __invoke(Request $request, ExamGroup $examGroup): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canProctorExamGroups(), 403);

        if ($user->isPengawas()) {
            abort_unless($examGroup->supervisor_id === $user->id, 403);
        }

        $examGroup->load(['school', 'testConfig']);
        $sessions = $examGroup->testSessions()->orderBy('id')->get();
        $total = $sessions->count();

        $slips = $sessions->values()->map(function (TestSession $session, int $index) use ($total): array {
            $url = route('student.show', $session->access_token);

            return [
                'seat' => $index + 1,
                'total' => $total,
                'token' => $session->access_token,
                'spaced' => implode(' ', str_split($session->access_token, 4)),
                'url' => $url,
                'qr' => $this->qr->svg($url),
                'used' => $session->opened_at !== null || $session->claimed_at !== null,
            ];
        });

        $tz = (string) config('app.timezone');

        return view('admin.group-slips', [
            'group' => $examGroup,
            'slips' => $slips,
            'opensLabel' => $examGroup->starts_at?->timezone($tz)->translatedFormat('l, d M Y H:i'),
            'started' => $examGroup->hasStarted(Carbon::now()),
            'usedCount' => $slips->where('used', true)->count(),
        ]);
    }
}
