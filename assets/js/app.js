/* HRIS app.js - interaksi umum */
document.addEventListener('DOMContentLoaded', function () {
  // Toggle sidebar mobile
  var btn = document.getElementById('btnSidebar');
  var sb = document.getElementById('sidebar');
  var bd = document.getElementById('sidebarBackdrop');
  if (btn && sb) {
    btn.addEventListener('click', function () { sb.classList.toggle('show'); bd.classList.toggle('show'); });
  }
  if (bd) bd.addEventListener('click', function () { sb.classList.remove('show'); bd.classList.remove('show'); });

  // Confirm untuk form hapus
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Auto-dismiss alert
  setTimeout(function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (a) {
      new bootstrap.Alert(a).close();
    });
  }, 6000);

  // Filter tabel sederhana
  document.querySelectorAll('[data-table-filter]').forEach(function (inp) {
    inp.addEventListener('keyup', function () {
      var table = document.querySelector(inp.getAttribute('data-table-filter'));
      if (!table) return;
      var v = inp.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().includes(v) ? '' : 'none';
      });
    });
  });

  // Preview upload logo/foto
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var img = document.querySelector(inp.getAttribute('data-preview'));
      var f = inp.files[0];
      if (f && img) img.src = URL.createObjectURL(f);
    });
  });
});
