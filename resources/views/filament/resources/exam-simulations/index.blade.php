<x-filament-panels::page>
    @php($demo = $this->demo())
    <p class="ceco-roll-demo-status {{ $demo ? 'is-active' : '' }}" data-demo-status="{{ $demo ? 'active' : 'inactive' }}">
        @if ($demo)
            Simulasi demo <strong>aktif</strong> sejak {{ $demo->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}
            — {{ $demo->label() }}.
            <a href="{{ \App\Filament\Resources\ExamSimulations\ExamSimulationResource::getUrl('view', ['record' => $demo]) }}">Lihat akun dan sandi</a>.
        @else
            Simulasi demo <strong>tidak aktif</strong>. Tombol "Buat Simulasi" menyiapkan akun tiap peran dan sesi tes contoh.
        @endif
    </p>

    <div class="ceco-roll">
        @forelse ($this->waves() as $wave)
            <article class="ceco-roll-slip">
                <a class="ceco-roll-slip-main" href="{{ \App\Filament\Resources\ExamSimulations\ExamSimulationResource::getUrl('view', ['record' => $wave]) }}">
                    <h2>{{ $wave->name }}</h2>
                    <time class="ceco-roll-slip-when" datetime="{{ $wave->starts_at?->toIso8601String() }}">{{ $wave->whenLabel() }}</time>
                    <p>
                        {{ $wave->students }} siswa menempati {{ $wave->rooms }} ruang.
                        {{ $wave->hasStarted() ? 'Pengawas boleh menyodorkan kartu QR.' : 'Kartu QR masih tertutup sampai jam itu.' }}
                    </p>
                </a>
                <div class="ceco-roll-slip-foot">
                    <span>{{ $wave->staffLabel() }}</span>
                    <div class="ceco-roll-slip-tools">
                        <button
                            type="button"
                            class="ceco-roll-reschedule"
                            wire:click="mountAction('reschedule', { waveId: {{ $wave->id }} })"
                        >
                            Ubah jam
                        </button>
                        <button
                            type="button"
                            class="ceco-roll-erase"
                            wire:click="removeWave({{ $wave->id }})"
                            wire:confirm="Hapus gelombang ini beserta akun, kursi, dan paketnya? Tes asli tidak berubah."
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <article class="ceco-roll-slip ceco-roll-slip--empty">
                <h2>Belum ada denah</h2>
                <p>Tulis gelombang baru: jumlah siswa, jumlah ruang, dan jam mulai. Lembar ini bisa dihapus utuh kapan saja.</p>
            </article>
        @endforelse
    </div>

    {{-- Halaman tabel biasanya merender modal aksi lewat $this->table; tampilan
         kustom ini tidak memanggil tabel, jadi modal (Ubah jam, Buat/Hapus
         Simulasi) harus dirender sendiri. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
