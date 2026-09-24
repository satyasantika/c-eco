@extends('manual.layout')

@section('title', 'Pilih peran')

@section('content')
    <h1 class="manual-title">Panduan Pengguna</h1>
    <p class="lede">Pilih peran Anda. Setiap panduan berisi langkah berurutan dan tangkapan layar dari aplikasi yang sebenarnya.</p>

    <ul class="manual-roles">
        @foreach ($roles as $key => $role)
            <li>
                <a href="{{ route('manual.show', $key) }}">
                    <span class="manual-role-name">{{ $role['label'] }}</span>
                    <span class="manual-role-tagline">{{ $role['tagline'] }}</span>
                    <span class="manual-role-count">{{ count($role['sections']) }} langkah</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
