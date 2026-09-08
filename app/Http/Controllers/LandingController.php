<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function show(): View
    {
        return view('welcome');
    }

    public function enter(Request $request): RedirectResponse
    {
        $token = strtoupper((string) $request->input('token', ''));
        $token = preg_replace('/[^A-Z0-9]/', '', $token) ?? '';

        if (strlen($token) !== 8) {
            return back()
                ->withErrors(['token' => 'Token berisi delapan huruf. Salin dari slip, jangan dari ingatan.'])
                ->withInput();
        }

        if (! TestSession::query()->where('access_token', $token)->exists()) {
            return back()
                ->withErrors(['token' => 'Token itu tidak ada. Tanyakan slip cadangan ke pengawas.'])
                ->withInput();
        }

        return redirect()->route('student.show', $token);
    }
}
