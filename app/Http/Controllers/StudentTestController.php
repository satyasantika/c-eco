<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\SequenceConflictException;
use App\Exceptions\SessionCompletedException;
use App\Models\TestSession;
use App\Services\CatSession;
use App\Services\StudentFeedbackBuilder;
use App\Support\SessionState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Alur pengerjaan siswa di /t/{token}.
 *
 * Blade + htmx, tanpa Filament dan tanpa Livewire (KA-8). Endpoint JSON di
 * SPEC §7 tetap ada untuk logika dan pengujian; halaman ini memakai rute
 * terpisah yang membalas fragmen HTML, bukan membedakan lewat Accept header,
 * supaya perubahan pada satu sisi tidak diam-diam mengubah sisi lain.
 */
class StudentTestController extends Controller
{
    public function __construct(
        private readonly CatSession $cat,
        private readonly StudentFeedbackBuilder $feedback,
    ) {}

    public function show(string $token): View
    {
        $session = $this->session($token);

        if ($session->status === 'completed') {
            return view('student.done', [
                'session' => $session,
                'feedback' => $this->feedback->for($session),
            ]);
        }

        if ($session->status === 'pending') {
            return $session->participant->consent_at === null
                ? view('student.welcome', ['session' => $session])
                : view('student.practice', ['session' => $session]);
        }

        return $this->testView($this->cat->state($session));
    }

    public function consent(Request $request, string $token): RedirectResponse
    {
        $request->validate(['consent' => ['accepted']]);

        $session = $this->session($token);
        $session->participant->forceFill(['consent_at' => Carbon::now()])->save();

        return redirect()->route('student.practice', $token);
    }

    public function practice(string $token): View
    {
        $session = $this->session($token);

        if ($session->status !== 'pending') {
            return $this->testView($this->cat->state($session));
        }

        return view('student.practice', ['session' => $session]);
    }

    /** Butir latihan tidak dinilai dan tidak menyentuh session_items. */
    public function begin(string $token): RedirectResponse
    {
        $session = $this->session($token);

        $this->cat->start($session, [
            'user_agent' => (string) request()->userAgent(),
            'ip' => (string) request()->ip(),
        ]);

        return redirect()->route('student.show', $token);
    }

    public function answer(Request $request, string $token): View
    {
        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1'],
            'option' => ['required', 'string', 'size:1'],
            'client_ts' => ['nullable', 'numeric'],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = $this->session($token);

        try {
            $state = $this->cat->answer(
                session: $session,
                sequence: (int) $validated['sequence'],
                displayLabel: strtoupper($validated['option']),
                meta: $validated,
            );
        } catch (SessionCompletedException) {
            $state = $this->cat->state($session->fresh());
        } catch (SequenceConflictException) {
            // Klien dan server berbeda pendapat tentang butir yang sedang
            // ditunggu. Server menang: kirim butir yang sebenarnya menunggu,
            // jangan biarkan siswa terjebak di layar yang salah.
            $state = $this->cat->state($session->fresh());
        }

        return $this->fragment($state, $token);
    }

    private function fragment(SessionState $state, string $token): View
    {
        if ($state->item === null) {
            return view('student.partials.finished', [
                'feedback' => $this->feedback->for($state->session),
            ]);
        }

        return view('student.partials.item', ['item' => $state->item, 'token' => $token]);
    }

    private function testView(SessionState $state): View
    {
        return view('student.test', [
            'session' => $state->session,
            'item' => $state->item,
            'feedback' => $state->item === null
                ? $this->feedback->for($state->session)
                : null,
        ]);
    }

    private function session(string $token): TestSession
    {
        return TestSession::query()->where('access_token', $token)->firstOrFail();
    }
}
