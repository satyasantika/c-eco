<x-filament-panels::page>
    @php
        /** @var \App\Models\ExamSimulation $simulation */
        $simulation = $this->getRecord()->loadMissing([
            'users',
            'examGroups.supervisor',
            'examGroups.testSessions',
            'testConfig.packageItems.itemBank',
            'school',
        ]);
        $allocation = $this->allocation();
        $config = $simulation->testConfig;
        $items = $config?->packageItems ?? collect();
    @endphp

    <div class="space-y-6">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="text-base font-semibold text-gray-950">Gelombang</h2>
            <p class="mt-2 text-sm text-gray-700">
                {{ $simulation->students }} siswa · {{ $simulation->rooms }} kelas
                · mulai {{ $simulation->starts_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                · sekolah {{ $simulation->school?->name }}
            </p>
            <p class="mt-1 text-sm text-gray-600">
                Paket: {{ $config?->name }} · {{ $simulation->grade_share_x }}% X
                · {{ $simulation->grade_share_xi }}% XI
                · {{ $simulation->grade_share_xii }}% XII
                · {{ $simulation->pool_size }} butir
                ({{ $allocation['X'] }} X + {{ $allocation['XI'] }} XI + {{ $allocation['XII'] }} XII).
                Terambil {{ $items->count() }} butir dari bank, tidak disalin.
            </p>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="text-base font-semibold text-gray-950">Akun khusus simulasi</h2>
            <p class="mt-2 text-sm text-gray-600">
                Kata sandi bersama: <strong>{{ $simulation->plain_password }}</strong>
            </p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-4">Peran</th>
                            <th class="py-2 pr-4">Nama</th>
                            <th class="py-2">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($simulation->users->sortBy('email') as $user)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pr-4">{{ $user->role->label() }}</td>
                                <td class="py-2 pr-4">{{ $user->name }}</td>
                                <td class="py-2 font-mono">{{ $user->email }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="text-base font-semibold text-gray-950">Kelas dan kartu QR</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-4">Kelas</th>
                            <th class="py-2 pr-4">Ruang</th>
                            <th class="py-2 pr-4">Pengawas</th>
                            <th class="py-2 pr-4">Kursi</th>
                            <th class="py-2">Kartu QR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($simulation->examGroups->sortBy('room') as $group)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pr-4">{{ $group->name }}</td>
                                <td class="py-2 pr-4">{{ $group->room }}</td>
                                <td class="py-2 pr-4">{{ $group->supervisor?->email }}</td>
                                <td class="py-2 pr-4">{{ $group->seatCount() }} / {{ $group->capacity }}</td>
                                <td class="py-2">
                                    <a href="{{ route('proctor.qr', $group) }}" class="text-primary-600 underline" target="_blank" rel="noreferrer">
                                        Buka kartu
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
