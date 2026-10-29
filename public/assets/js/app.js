/* HRIS Asia Raya — progressive enhancement only. */
(function () {
  'use strict';

  /* ---------- sidebar (mobile) ---------- */
  function toggleNav(force) {
    var open = typeof force === 'boolean' ? force : !document.body.classList.contains('nav-open');
    document.body.classList.toggle('nav-open', open);
  }
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-nav-toggle]');
    if (trigger) { e.preventDefault(); toggleNav(); return; }
    if (e.target.classList.contains('sidebar-backdrop')) { toggleNav(false); }
  });

  /* ---------- dropdowns ---------- */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-dropdown]');
    var menus = document.querySelectorAll('.dropdown.open');
    if (trigger) {
      e.preventDefault();
      var parent = trigger.closest('.dropdown');
      var wasOpen = parent.classList.contains('open');
      menus.forEach(function (m) { m.classList.remove('open'); });
      if (!wasOpen) { parent.classList.add('open'); }
      return;
    }
    if (!e.target.closest('.dropdown__menu')) {
      menus.forEach(function (m) { m.classList.remove('open'); });
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.dropdown.open').forEach(function (m) { m.classList.remove('open'); });
      toggleNav(false);
    }
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      var s = document.querySelector('.topbar__search input');
      if (s) { e.preventDefault(); s.focus(); }
    }
  });

  /* ---------- flash dismiss ---------- */
  document.addEventListener('click', function (e) {
    var close = e.target.closest('.alert__close');
    if (close) { close.closest('.alert').remove(); }
  });

  /* ---------- confirm on destructive actions ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  /* ---------- auto submit filters ---------- */
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el.matches('[data-auto-submit]')) {
      if (el.form) { el.form.submit(); }
    }
  });

  /* ---------- open form panels from buttons ---------- */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-open-form]');
    if (!trigger) return;
    var target = trigger.getAttribute('href');
    if (!target || target.charAt(0) !== '#') return;
    var panel = document.querySelector(target);
    if (!panel || panel.tagName !== 'DETAILS') return;
    e.preventDefault();
    panel.open = true;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var field = panel.querySelector('input:not([type=hidden]), select, textarea');
    if (field) { setTimeout(function () { field.focus({ preventScroll: true }); }, 260); }
  });

  /* ---------- password reveal ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    e.preventDefault();
    var input = document.querySelector(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('is-on', input.type === 'text');
  });

  /* ---------- live clock ---------- */
  function tick() {
    var el = document.querySelector('[data-clock]');
    if (!el) return;
    var now = new Date();
    var pad = function (n) { return String(n).padStart(2, '0'); };
    el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
  }
  if (document.querySelector('[data-clock]')) {
    tick();
    setInterval(tick, 1000);
  }

  /* ---------- geolocation helper ---------- */
  window.HRIS = window.HRIS || {};
  window.HRIS.distance = function (lat1, lon1, lat2, lon2) {
    var R = 6371000, toRad = function (d) { return d * Math.PI / 180; };
    var dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
  };

  window.HRIS.locate = function (target) {
    var status = document.querySelector(target || '[data-geo-status]');
    if (!status) return;
    if (!navigator.geolocation) {
      status.innerHTML = '<span class="badge badge-rose">GPS tidak tersedia</span> Browser tidak mendukung geolokasi.';
      return;
    }
    status.innerHTML = '<span class="badge badge-amber">Mencari lokasi…</span> Mohon izinkan akses lokasi.';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var lat = pos.coords.latitude, lng = pos.coords.longitude;
      var acc = Math.round(pos.coords.accuracy || 0);
      document.querySelectorAll('[data-geo-lat]').forEach(function (i) { i.value = lat; });
      document.querySelectorAll('[data-geo-lng]').forEach(function (i) { i.value = lng; });
      document.querySelectorAll('[data-geo-accuracy]').forEach(function (i) { i.value = acc; });

      var officeLat = parseFloat(status.getAttribute('data-lat'));
      var officeLng = parseFloat(status.getAttribute('data-lng'));
      var radius = parseInt(status.getAttribute('data-radius') || '50', 10);
      var bypass = status.getAttribute('data-bypass') === '1';
      var distance = null;
      if (!isNaN(officeLat) && !isNaN(officeLng)) {
        distance = window.HRIS.distance(lat, lng, officeLat, officeLng);
        var distEl = document.querySelector('[data-geo-distance]');
        if (distEl) { distEl.textContent = distance + ' m'; }
      }
      var inside = distance === null || distance <= radius || bypass;
      status.innerHTML =
        '<span class="badge ' + (inside ? 'badge-teal' : 'badge-rose') + '">' +
        (inside ? (distance === null ? 'Lokasi terdeteksi' : 'Dalam area kantor') : 'Di luar area kantor') +
        '</span> Jarak ' + (distance === null ? '—' : distance + ' m') + ' · akurasi ±' + acc + ' m' +
        (bypass ? ' · <b>WFH: geofence dilewati</b>' : '');
      document.querySelectorAll('[data-geo-submit]').forEach(function (b) {
        b.disabled = !inside;
      });
    }, function (err) {
      status.innerHTML = '<span class="badge badge-rose">GPS gagal</span> ' + (err.message || 'Izin lokasi ditolak.');
      document.querySelectorAll('[data-geo-submit]').forEach(function (b) { b.disabled = true; });
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  };

  /* ---------- selfie camera + client side compression ---------- */
  window.HRIS.camera = function (opts) {
    var video = document.querySelector(opts.video);
    var canvas = document.querySelector(opts.canvas);
    var preview = document.querySelector(opts.preview);
    var input = document.querySelector(opts.input);
    var startBtn = document.querySelector(opts.start);
    var captureBtn = document.querySelector(opts.capture);
    var retakeBtn = document.querySelector(opts.retake);
    var maxWidth = opts.maxWidth || 720;
    var quality = opts.quality || 0.7;
    var stream = null;

    function stop() {
      if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    }

    function start() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        if (startBtn) { startBtn.textContent = 'Kamera tidak didukung browser'; }
        return;
      }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 } }, audio: false })
        .then(function (s) {
          stream = s;
          video.srcObject = s;
          video.classList.remove('hidden');
          if (preview) preview.classList.add('hidden');
          video.play();
          if (captureBtn) captureBtn.disabled = false;
          if (startBtn) startBtn.classList.add('hidden');
          if (retakeBtn) retakeBtn.classList.remove('hidden');
        })
        .catch(function (err) {
          alert('Tidak dapat mengakses kamera: ' + (err.message || err.name) + '\nPastikan situs diakses via HTTPS dan izin kamera diberikan.');
        });
    }

    function capture() {
      if (!stream) { return; }
      var w = video.videoWidth || 640, h = video.videoHeight || 480;
      var scale = Math.min(1, maxWidth / w);
      canvas.width = Math.round(w * scale);
      canvas.height = Math.round(h * scale);
      var ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      // stamp time + coordinates for auditability
      var stamp = new Date().toLocaleString('id-ID');
      ctx.fillStyle = 'rgba(8,25,42,.62)';
      ctx.fillRect(0, canvas.height - 34, canvas.width, 34);
      ctx.fillStyle = '#fff';
      ctx.font = 'bold 14px sans-serif';
      ctx.fillText(stamp, 12, canvas.height - 12);

      var dataUrl = canvas.toDataURL('image/jpeg', quality);
      if (input) { input.value = dataUrl; }
      if (preview) {
        preview.src = dataUrl;
        preview.classList.remove('hidden');
      }
      video.classList.add('hidden');
      stop();
      if (captureBtn) captureBtn.disabled = true;
      if (startBtn) startBtn.classList.remove('hidden');
    }

    if (startBtn) startBtn.addEventListener('click', function (e) { e.preventDefault(); start(); });
    if (captureBtn) captureBtn.addEventListener('click', function (e) { e.preventDefault(); capture(); });
    if (retakeBtn) retakeBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (input) input.value = '';
      start();
    });

    return { start: start, capture: capture, stop: stop };
  };

  /* ---------- lonceng notifikasi (polling ringan 60 dtk) ---------- */
  var bell = document.querySelector('[data-bell]');
  if (bell && window.fetch) {
    var bellCount = bell.querySelector('[data-bell-count]');

    var paintBell = function (unread) {
      if (unread > 0) {
        if (!bellCount) {
          bellCount = document.createElement('span');
          bellCount.className = 'icon-btn__count';
          bellCount.setAttribute('data-bell-count', '');
          bell.appendChild(bellCount);
        }
        bellCount.textContent = unread > 9 ? '9+' : String(unread);
      } else if (bellCount) {
        bellCount.remove();
        bellCount = null;
      }
    };

    var pollBell = function () {
      if (document.hidden) { return; }
      fetch((window.HRIS_BASE || '') + '/notifications/latest', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(function (r) {
        return r.ok ? r.json() : null;
      }).then(function (data) {
        if (data && typeof data.unread === 'number') { paintBell(data.unread); }
      }).catch(function () { /* offline: biarkan badge terakhir */ });
    };

    setInterval(pollBell, 60000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { pollBell(); }
    });
  }

  /* ---------- service worker ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register((window.HRIS_BASE || '') + '/sw.js').catch(function () { /* offline mode optional */ });
    });
  }

  /* ---------- copy to clipboard ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) return;
    e.preventDefault();
    var text = btn.getAttribute('data-copy');
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject())
      .then(function () {
        var old = btn.innerHTML;
        btn.innerHTML = 'Tersalin';
        setTimeout(function () { btn.innerHTML = old; }, 1400);
      })
      .catch(function () { window.prompt('Salin manual:', text); });
  });
})();
