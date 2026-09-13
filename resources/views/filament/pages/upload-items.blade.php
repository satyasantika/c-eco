<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="text-base font-semibold text-gray-950">Berkas yang boleh diunggah</h2>
            <p class="mt-2 text-sm text-gray-600">
                Hanya <strong>.{{ \App\Support\ItemPackageFormat::EXTENSION }}</strong>,
                paling besar {{ $this->maxMegabytes() }} MB, UTF-8.
                Ditolak: {{ implode(', ', $this->rejectedExtensions()) }}.
            </p>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-gray-700">
                @foreach ($this->rules() as $rule)
                    <li>{{ $rule }}</li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="text-base font-semibold text-gray-950">Contoh struktur</h2>
            <p class="mt-2 text-sm text-gray-600">
                Contoh memakai kode X-91 dan X-92. Ganti kode jika sudah terpakai.
                Unduh berkas siap pakai dari tombol di atas, lalu sunting.
            </p>
            <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs leading-relaxed text-gray-800">{{ $this->sampleJson() }}</pre>
        </section>
    </div>
</x-filament-panels::page>
