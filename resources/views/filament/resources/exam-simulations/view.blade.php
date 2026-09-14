<x-filament-panels::page>
    @php
        /** @var \App\Models\ExamSimulation $wave */
        $wave = $this->getRecord()->loadMissing([
            'users',
            'examGroups.supervisor',
            'examGroups.testSessions',
            'testConfig.packageItems.itemBank',
            'school',
        ]);
        $allocation = $this->allocation();
        $items = $wave->testConfig?->packageItems ?? collect();
    @endphp

    <div class="ceco-roll">
        <section class="ceco-roll-paper">
            <p>
                <time datetime="{{ $wave->starts_at?->toIso8601String() }}">{{ $wave->whenLabel() }}</time>
                —
                {{ $wave->students }} siswa menempati {{ $wave->rooms }} ruang
                di {{ $wave->school?->name }}.
                Paket {{ $allocation['X'] }} X, {{ $allocation['XI'] }} XI, {{ $allocation['XII'] }} XII
                ({{ $items->count() }} dari bank).
                {{ $wave->hasStarted() ? 'Pengawas boleh menyodorkan kartu QR.' : 'Kartu QR masih tertutup sampai jam server.' }}
            </p>

            <div class="ceco-roll-pass">
                <p>Sandi bersama, untuk pengawas dan operator gelombang ini saja.</p>
                <p class="ceco-roll-passphrase">{{ $wave->plain_password }}</p>
            </div>
        </section>

        <section class="ceco-roll-paper">
            <h2>Daftar jaga</h2>
            <div class="ceco-roll-register-wrap">
                <table class="ceco-roll-register">
                    <thead>
                        <tr>
                            <th>Tugas</th>
                            <th>Nama</th>
                            <th>Masuk dengan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($wave->users->sortBy('email') as $user)
                            <tr>
                                <td>{{ $user->role->label() }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <h2>Denah ruang</h2>
        <p>Satu pengawas, satu kartu. Porsi paket: {{ $wave->mixLabel() }}.</p>

        <div class="ceco-roll-rooms">
            @foreach ($wave->examGroups->sortBy('room') as $group)
                @php
                    $taken = $group->testSessions->count();
                    $capacity = max($taken, (int) $group->capacity);
                @endphp
                <article class="ceco-roll-room">
                    <div class="ceco-roll-room-head">
                        <div>
                            <h2>{{ $group->room }}</h2>
                            <p>{{ $taken }} kursi terisi dari {{ $capacity }}. {{ $group->supervisor?->email }}</p>
                        </div>
                        <a class="ceco-roll-stamp" href="{{ route('proctor.qr', $group) }}" target="_blank" rel="noreferrer">Kartu QR</a>
                    </div>
                    <div class="ceco-roll-seats">
                        @for ($i = 1; $i <= $capacity; $i++)
                            <span class="ceco-roll-seat {{ $i <= $taken ? 'is-taken' : '' }}" title="Kursi {{ $i }}"></span>
                        @endfor
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
