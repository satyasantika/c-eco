<x-filament-panels::page>
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
                    <button
                        type="button"
                        class="ceco-roll-erase"
                        wire:click="removeWave({{ $wave->id }})"
                        wire:confirm="Hapus gelombang ini beserta akun, kursi, dan paketnya? Tes asli tidak berubah."
                    >
                        Hapus
                    </button>
                </div>
            </article>
        @empty
            <article class="ceco-roll-slip ceco-roll-slip--empty">
                <h2>Belum ada denah</h2>
                <p>Tulis gelombang baru: jumlah siswa, jumlah ruang, dan jam mulai. Lembar ini bisa dihapus utuh kapan saja.</p>
            </article>
        @endforelse
    </div>
</x-filament-panels::page>
