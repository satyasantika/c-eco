<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\QrCodeRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Slip token untuk dicetak dan digunting, 8 per halaman A4.
 */
class SlipController extends Controller
{
    public function __construct(private readonly QrCodeRenderer $qr) {}

    public function __invoke(Request $request, TestConfig $config): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageRoster(), 403);

        $sessions = TestSession::query()
            ->where('test_config_id', $config->id)
            ->whereNull('exam_group_id')
            ->when($request->query('class'), fn ($q, $class) => $q->whereRelation('participant', 'class_name', 'like', "%{$class}%"))
            ->when($request->query('school'), fn ($q, $school) => $q->whereRelation('participant.school', 'name', 'like', "%{$school}%"))
            ->with('participant.school')
            ->get()
            ->sortBy([
                fn (TestSession $s): string => $s->participant->class_name,
                fn (TestSession $s): string => $s->participant->display_name,
            ])
            ->values();

        $slips = $sessions->map(function (TestSession $session): array {
            $url = route('student.show', $session->access_token);

            return [
                'session' => $session,
                'url' => $url,
                'qr' => $this->qr->svg($url),
            ];
        });

        return view('admin.slips', [
            'config' => $config,
            'slips' => $slips,
        ]);
    }
}
