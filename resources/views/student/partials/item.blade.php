{{--
  Fragmen butir. Ditukar utuh oleh htmx (hx-swap="outerHTML"), jadi setiap
  atribut yang dibutuhkan skrip harus ada di elemen terluar ini — termasuk
  data-sequence, yang dipakai untuk merekonsiliasi outbox saat halaman dimuat.
--}}
<form id="butir"
      data-sequence="{{ $item->sequence }}"
      hx-post="{{ route('student.answer', $token) }}"
      hx-target="#butir"
      hx-swap="outerHTML">
    <input type="hidden" name="sequence" value="{{ $item->sequence }}">

    {{-- Panjang tes adaptif tidak tetap, jadi tanpa "dari 20". --}}
    <p class="progress">Butir ke-{{ $item->sequence }}</p>

    <div class="stem">
        <div class="scroll-x">{!! $item->stemHtml !!}</div>
        @if ($item->mediaPath)
            <img src="{{ asset('storage/'.$item->mediaPath) }}" alt="">
        @endif
    </div>

    <fieldset>
        <ul class="options">
            @foreach ($item->options as $option)
                <li>
                    <label class="option">
                        <input type="radio" name="option" value="{{ $option['label'] }}">
                        <span>
                            <span class="tag">{{ $option['label'] }}.</span>
                            <span class="body">{!! $option['body_html'] !!}</span>
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
    </fieldset>

    <div class="sticky">
        <div class="inner">
            <button type="submit" disabled>Lanjut</button>
        </div>
    </div>
</form>
