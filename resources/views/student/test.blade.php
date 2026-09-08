@extends('student.layout')

@section('content')
    <div id="tes"
         data-token="{{ $session->access_token }}"
         data-answer-url="{{ route('student.answer', $session->access_token) }}"
         data-event-url="{{ url("/api/t/{$session->access_token}/event") }}">

        @if ($item)
            @include('student.partials.item', ['item' => $item, 'token' => $session->access_token])
        @else
            @include('student.partials.finished', ['feedback' => $feedback])
        @endif
    </div>

    <div id="banner" class="banner" data-open="false" role="status" aria-live="polite">
        <span id="banner-text"></span>
        <button type="button" id="banner-retry" hidden>Coba kirim sekarang</button>
    </div>
@endsection
