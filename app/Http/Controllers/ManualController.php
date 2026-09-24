<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\UserManual;
use Illuminate\View\View;

/** Manual pengguna publik: pilih peran, lalu baca langkahnya. Tanpa login. */
class ManualController extends Controller
{
    public function index(): View
    {
        return view('manual.index', ['roles' => UserManual::roles()]);
    }

    public function show(string $role): View
    {
        $manual = UserManual::role($role);
        abort_if($manual === null, 404);

        return view('manual.show', [
            'roles' => UserManual::roles(),
            'key' => $role,
            'manual' => $manual,
        ]);
    }
}
