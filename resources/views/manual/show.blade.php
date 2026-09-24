@extends('manual.layout')

@section('title', 'Panduan '.$manual['label'])

@section('content')
    <nav class="manual-tabs" aria-label="Peran">
        @foreach ($roles as $roleKey => $role)
            <a href="{{ route('manual.show', $roleKey) }}" @if ($roleKey === $key) aria-current="page" @endif>{{ $role['label'] }}</a>
        @endforeach
    </nav>

    <h1 class="manual-title">Panduan {{ $manual['label'] }}</h1>
    <p class="lede">{{ $manual['tagline'] }}</p>
    <p class="manual-access">{!! $manual['access'] !!}</p>

    <ol class="manual-sections">
        @foreach ($manual['sections'] as $i => $section)
            <li class="manual-section" id="langkah-{{ $i + 1 }}">
                <h2><span class="manual-no">{{ $i + 1 }}</span> {{ $section['title'] }}</h2>
                <ol class="manual-steps">
                    @foreach ($section['steps'] as $step)
                        <li>{!! $step !!}</li>
                    @endforeach
                </ol>

                @php($shot = $section['shot'] ?? null)
                @if ($shot !== null && \App\Support\UserManual::exists($shot['file']))
                    <figure class="manual-shot {{ ($shot['mobile'] ?? false) ? 'manual-shot--phone' : '' }}">
                        <a href="{{ \App\Support\UserManual::url($shot['file']) }}" target="_blank" rel="noopener">
                            <img src="{{ \App\Support\UserManual::url($shot['file']) }}"
                                 alt="Tangkapan layar: {{ $section['title'] }}"
                                 loading="lazy" decoding="async">
                        </a>
                        @isset($section['shot_note'])
                            <figcaption>{{ $section['shot_note'] }}</figcaption>
                        @endisset
                    </figure>
                @elseif ($shot !== null)
                    <p class="manual-missing">Tangkapan layar belum dibuat. Admin dapat menjalankan <code>php artisan manual:capture-screenshots</code>.</p>
                @elseif (isset($section['shot_note']))
                    <p class="manual-missing">{{ $section['shot_note'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
@endsection
