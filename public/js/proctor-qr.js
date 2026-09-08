(function () {
  var root = document.getElementById('qr-live');
  if (!root) {
    return;
  }

  var pollUrl = root.getAttribute('data-poll-url');
  var started = root.getAttribute('data-started') === '1';
  var shown = started ? (root.getAttribute('data-token') || '') : '';
  var stopped = false;
  var inFlight = false;

  function countEl() {
    return document.getElementById('qr-count');
  }

  function panel() {
    return document.getElementById('qr-panel');
  }

  function waitBox() {
    return document.getElementById('qr-wait');
  }

  function progress() {
    return document.getElementById('qr-progress');
  }

  function setHidden(el, hidden) {
    if (!el) {
      return;
    }
    if (hidden) {
      el.setAttribute('hidden', 'hidden');
    } else {
      el.removeAttribute('hidden');
    }
  }

  function renderWait(data) {
    started = false;
    shown = '';
    setHidden(waitBox(), false);
    setHidden(panel(), true);
    setHidden(progress(), true);
    var now = document.getElementById('qr-now');
    if (now && data.now_label) {
      now.textContent = data.now_label;
    }
  }

  function renderLive(data) {
    started = true;
    setHidden(waitBox(), true);
    setHidden(panel(), false);
    setHidden(progress(), false);

    var count = countEl();
    if (count) {
      count.textContent = String(data.opened != null ? data.opened : 0);
    }

    var host = panel();
    if (!host) {
      return;
    }

    if (data.done || !data.token) {
      host.innerHTML = '<p class="done" id="qr-done">Semua token rombongan ini sudah terpakai.</p>';
      shown = '';
      stopped = true;
      return;
    }

    host.innerHTML =
      '<div class="qr" id="qr-svg">' + data.qr + '</div>' +
      '<p class="token" id="qr-token">' + data.spaced + '</p>' +
      '<p class="muted" id="qr-hint">Sodorkan ke siswa. Begitu halaman siswa terbuka, kode berikutnya muncul sendiri.</p>';
    shown = data.token;
  }

  function tick() {
    if (stopped || inFlight) {
      return;
    }

    inFlight = true;
    var url = started && shown ? pollUrl + '?since=' + encodeURIComponent(shown) : pollUrl;

    fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        if (!data) {
          return;
        }
        if (!data.started) {
          renderWait(data);
          return;
        }
        if (!started || data.token !== shown || data.done) {
          renderLive(data);
        } else if (countEl()) {
          countEl().textContent = String(data.opened != null ? data.opened : 0);
        }
      })
      .catch(function () {})
      .then(function () {
        inFlight = false;
        if (!stopped) {
          setTimeout(tick, started ? 200 : 10000);
        }
      });
  }

  tick();
})();
