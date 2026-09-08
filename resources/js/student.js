import htmx from 'htmx.org';

/*
 * Sisi siswa: htmx + JavaScript seperlunya. Tidak ada Livewire di sini —
 * itu milik Filament di /admin (KA-8).
 *
 * Bagian terpenting berkas ini adalah outbox. Jaringan seluler kerap
 * memutus koneksi tepat setelah permintaan berangkat, sehingga server sudah
 * menyimpan jawaban tetapi klien tidak pernah tahu. Tanpa outbox, siswa akan
 * diminta menjawab ulang butir yang sudah tercatat.
 */

window.htmx = htmx;

// htmx memanggil eval() untuk atribut hx-on:*, hx-vals:js, dan hx-headers:js.
// Kita tidak memakai satu pun, dan CSP halaman siswa melarang 'unsafe-eval'
// (lihat App\Http\Middleware\SecurityHeaders). Mematikannya di sini membuat
// atribut semacam itu gagal terang-terangan saat dikembangkan, bukan diam-diam
// diblokir peramban siswa pada hari-H.
htmx.config.allowEval = false;

const root = document.getElementById('tes');

if (root) {
  const token = root.dataset.token;
  const answerUrl = root.dataset.answerUrl;
  const eventUrl = root.dataset.eventUrl;
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const outboxKey = `c-eco:outbox:${token}`;
  const backoff = [1000, 3000, 8000];

  let retry = 0;
  let retryTimer = null;

  /* ---------- outbox ---------- */

  const readOutbox = () => {
    try {
      return JSON.parse(localStorage.getItem(outboxKey) || 'null');
    } catch {
      return null;
    }
  };

  const writeOutbox = (entry) => {
    try {
      localStorage.setItem(outboxKey, JSON.stringify(entry));
    } catch {
      /* localStorage penuh atau ditolak: server tetap sumber kebenaran. */
    }
  };

  const clearOutbox = () => {
    try {
      localStorage.removeItem(outboxKey);
    } catch {
      /* diabaikan */
    }
  };

  /* ---------- spanduk ---------- */

  const banner = document.getElementById('banner');
  const bannerText = document.getElementById('banner-text');
  const bannerRetry = document.getElementById('banner-retry');

  const showBanner = (text, kind, withRetry) => {
    bannerText.textContent = text;
    banner.className = `banner ${kind}`;
    banner.dataset.open = 'true';
    bannerRetry.hidden = !withRetry;
  };

  const hideBanner = () => {
    banner.dataset.open = 'false';
  };

  const flashSaved = () => {
    showBanner('Jawaban tersimpan.', 'ok', false);
    setTimeout(hideBanner, 1500);
  };

  /* ---------- pengiriman ---------- */

  const currentSequence = () => {
    const form = document.getElementById('butir');
    return form ? Number(form.dataset.sequence) : null;
  };

  const send = (entry) => {
    htmx.ajax('POST', answerUrl, {
      target: '#butir',
      swap: 'outerHTML',
      values: { sequence: entry.sequence, option: entry.option, client_ts: entry.client_ts },
    });
  };

  const scheduleResend = (entry) => {
    clearTimeout(retryTimer);
    const wait = backoff[Math.min(retry, backoff.length - 1)];
    retry += 1;
    retryTimer = setTimeout(() => send(entry), wait);
  };

  bannerRetry.addEventListener('click', () => {
    const entry = readOutbox();
    if (entry) {
      retry = 0;
      showBanner('Mencoba mengirim ulang…', 'warn', false);
      send(entry);
    }
  });

  /* ---------- kaitan htmx ---------- */

  document.body.addEventListener('htmx:configRequest', (event) => {
    event.detail.headers['X-CSRF-TOKEN'] = csrf;
  });

  // Outbox ditulis SEBELUM permintaan berangkat. Kalau ditulis setelah
  // respons, jawaban yang hilang di jaringan tidak akan pernah tercatat.
  document.body.addEventListener('htmx:beforeRequest', (event) => {
    const params = event.detail.requestConfig?.parameters;
    if (!params || !params.sequence) return;

    writeOutbox({
      token,
      sequence: Number(params.sequence),
      option: params.option,
      client_ts: params.client_ts || Math.floor(Date.now() / 1000),
    });
  });

  document.body.addEventListener('htmx:afterRequest', (event) => {
    if (!event.detail.successful) return;

    clearTimeout(retryTimer);
    retry = 0;
    clearOutbox();
    hideBanner();
    flashSaved();
  });

  const onFailure = () => {
    const entry = readOutbox();
    if (!entry) return;

    // Outbox TIDAK dihapus: jawaban ini mungkin sudah sampai ke server.
    // Mengirim ulang aman karena /answer idempoten (R3).
    showBanner('Koneksi terputus — jawabanmu tersimpan, kami coba lagi.', 'warn', true);
    scheduleResend(entry);
  };

  document.body.addEventListener('htmx:responseError', onFailure);
  document.body.addEventListener('htmx:sendError', onFailure);
  document.body.addEventListener('htmx:timeout', onFailure);

  /* ---------- rekonsiliasi saat halaman dimuat ---------- */

  const reconcile = () => {
    const sequence = currentSequence();
    const entry = readOutbox();

    if (!entry || sequence === null) return;

    if (entry.sequence === sequence) {
      // Jawaban ini mungkin belum sampai. Kunci interaksi sampai server
      // mengonfirmasi, supaya siswa tidak menjawab dua kali.
      root.classList.add('syncing');
      showBanner('Menyinkronkan jawaban terakhir…', 'warn', false);
      send(entry);
      return;
    }

    if (entry.sequence < sequence) {
      // Server sudah maju melewati butir itu: jawabannya berhasil.
      clearOutbox();
    }
  };

  document.body.addEventListener('htmx:afterSwap', () => {
    root.classList.remove('syncing');
    bindItem();
  });

  window.addEventListener('online', () => {
    const entry = readOutbox();
    if (entry) {
      retry = 0;
      showBanner('Koneksi kembali, mengirim jawaban…', 'warn', false);
      send(entry);
    }
  });

  /* ---------- peristiwa sesi ---------- */

  const report = (type, payload) => {
    const body = JSON.stringify({ type, payload: payload || {} });

    if (navigator.sendBeacon) {
      navigator.sendBeacon(eventUrl, new Blob([body], { type: 'application/json' }));
      return;
    }

    fetch(eventUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body,
      keepalive: true,
    }).catch(() => {});
  };

  document.addEventListener('visibilitychange', () => {
    report(document.hidden ? 'visibility_hidden' : 'visibility_visible');
  });

  if (navigator.connection) {
    navigator.connection.addEventListener('change', () => {
      report('connection_change', { effective_type: navigator.connection.effectiveType });
    });
  }

  /* ---------- interaksi butir ---------- */

  function bindItem() {
    const form = document.getElementById('butir');
    if (!form) return;

    const submit = form.querySelector('button[type="submit"]');
    const inputs = form.querySelectorAll('input[type="radio"]');

    // Tombol Lanjut nonaktif sampai satu opsi ditap.
    inputs.forEach((input) => {
      input.addEventListener('change', () => {
        submit.disabled = false;
      });
    });

    submit.disabled = !form.querySelector('input[type="radio"]:checked');
  }

  /* ---------- tanpa navigasi mundur (aturan R9) ---------- */

  history.pushState(null, '', location.href);
  window.addEventListener('popstate', () => history.pushState(null, '', location.href));

  bindItem();
  reconcile();
}
