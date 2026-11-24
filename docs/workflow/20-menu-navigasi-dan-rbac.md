# 20 — Menu Navigasi & RBAC

> Bagaimana sidebar dibentuk, mengapa sebagian menu tidak muncul untuk suatu
> peran, dan bagaimana hak akses ditegakkan berlapis (route → controller → data).

## 1. Fungsi

* **Katalog menu** terpusat di `app/Core/Nav.php` (12 grup, 60 item; beberapa route
  dipakai lebih dari satu peran dengan kunci berbeda) — setiap item punya
  route, label, ikon, daftar `roles`, opsi `badge_key`, `requires_reports`, dan
  kunci khusus untuk route yang dipakai beberapa peran.
* **Visibilitas dapat dikonfigurasi** tanpa mengubah kode: nilai bawaan di
  `app/config/menu.php` + penimpaan dari **Pengaturan → Menu Navigasi**
  (`menu_hidden_items`, `menu_hidden_groups`).
* **Badge** jumlah pekerjaan menunggu (cuti, lembur, jadwal, klaim, payroll,
  pengumuman belum dibaca) dihitung `Controller::notificationContext()`.
* **Penjaga berlapis**: RBAC di router (`roles`), pemeriksaan peran/relasi di
  controller, dan filter data (karyawan hanya data sendiri, atasan hanya timnya).
* **Menu terkunci**: *Dashboard* dan *Pengaturan* selalu tampil agar HR tidak
  kehilangan jalan kembali.

## 2. Role yang berperan

Grup **Portal** dipecah otomatis untuk peran non-karyawan: item layanan pribadi
menjadi **Layanan Mandiri**, item yang butuh bawahan menjadi **Persetujuan Tim**.
Karyawan tetap melihat satu grup **Portal Karyawan**.

| Role | Cakupan menu | Catatan |
|------|--------------|---------|
| HR | Dashboard + 11 grup: Organisasi, Kepegawaian, Operasional, Penggajian, Pajak & Jamsos, Rekrutmen, Kinerja & Pengembangan, Offboarding, Komunikasi, **Layanan Mandiri**, Sistem & Audit | Tanpa *Persetujuan Payroll* dan *Persetujuan Tim* |
| FIN | Dashboard + 8 grup: Operasional (perubahan jadwal), Penggajian (+ *Persetujuan Payroll*), Pajak & Jamsos, Kinerja & Pengembangan, Offboarding, Komunikasi, **Layanan Mandiri**, **Persetujuan Tim** | Tanpa Kepegawaian, Rekrutmen, dan Sistem |
| KARYAWAN | Dashboard + 5 grup: Operasional (perubahan jadwal), Kinerja & Pengembangan, Offboarding, Komunikasi, **Portal Karyawan** | Portal berisi 12 menu: 10 layanan pribadi + 2 persetujuan tim (bila punya bawahan) |
| Atasan (relasi) | *Persetujuan Tim* (FIN) atau 2 menu di *Portal Karyawan* (KARYAWAN) | Hanya muncul bila `Auth::hasReports()`; HR tidak memakai menu ini |

## 3. Workflow

### 3.1 Pembentukan sidebar

```text
Nav::catalog()  ← app/config/menu.php (hidden, hidden_groups, locked)
        │
        ├─ Settings::get('menu_hidden_items'|'menu_hidden_groups') → gabungkan
        ▼
Nav::forUser(user, badges)
        │ 1. buang grup/item yang tidak memuat role user
        │ 2. buang item dengan requires_reports bila user bukan HR dan tak punya bawahan
        │    (Auth::hasReports)
        │ 3. buang item/grup yang disembunyikan (kecuali item terkunci)
        │ 4. grup 'portal' dipecah untuk non-KARYAWAN:
        │    Layanan Mandiri (pribadi) + Persetujuan Tim (butuh bawahan)
        │ 5. sisipkan badge + tandai item 'live'
        ▼
   sidebar (topbar + mobile drawer) dirender
```

### 3.2 Mengubah tampilan menu

```text
[HR] /settings?tab=menu → centang item/grup yang ditampilkan → Simpan
        │
        ▼
Settings::set('menu_hidden_items', [...]) · Nav::flush()
        │
        └─ [Kembalikan ke bawaan] → Settings::forget(...) → ikut app/config/menu.php
```

### 3.3 Penegakan hak akses

```text
Request → Router: sesi valid? (auth) → role diizinkan? (roles) → 403
        ▼
Controller: requireRole()/isManagerOf()/employeeId() → 403 atau data terfilter
        ▼
Model/query: WHERE employee_id = ? / direct_manager_id = ? → data terbatas
```

## 4. Status & matriks akses

Tidak ada status domain di modul ini. Ringkasan lapisan:

| Lapisan | Berkas | Fungsi |
|---------|--------|--------|
| Route guard | `public/index.php` (`roles`) + `Router` | Menolak peran yang tidak berhak dengan 403 |
| Navigasi | `app/Core/Nav.php`, `app/config/menu.php` | Menyembunyikan menu (bukan pengaman data) |
| Controller | `app/Core/Controller.php` (`requireRole`, `isManagerOf`, `employeeId`) | Aturan relasional (atasan tim, pemilik data) |
| Data | Model/query | Filter baris sesuai pemilik/tim |

**Matriks ringkas**

| Modul | HR | FIN | KARYAWAN |
|-------|----|-----|----------|
| Dashboard, profil, notifikasi | ✔ | ✔ | ✔ |
| Kepegawaian, organisasi, shift, presensi HR | ✔ | — | — |
| Perubahan jadwal | ✔ | lihat | ajukan |
| Payroll | kelola | setujui & lihat | slip sendiri |
| Klaim | kelola | setujui & bayar | ajukan |
| Cuti/lembur | kelola semua | tim sendiri | ajukan |
| Pajak & BPJS | ✔ | ✔ | bukti potong sendiri |
| Rekrutmen, pelatihan, penilaian, offboarding | ✔ | sebagian (lihat) | mandiri |
| Laporan | ✔ | ✔ | — |
| Log, email, penjadwal, pengaturan | ✔ | — | — |

**Batas kewenangan yang tetap berlaku bagi HR/Finance**

Role menentukan *cakupan data*, bukan hak memutus pengajuan sendiri. Aturan
berikut dijalankan di model/engine sehingga berlaku juga untuk REST API:

* Tidak ada pengguna yang dapat menyetujui atau menolak pengajuannya sendiri
  (cuti, lembur, perubahan jadwal, klaim, offboarding).
* HR tidak boleh melompati tahap atasan pada pengajuan rekan HR yang sudah
  punya atasan langsung.
* Pengajuan milik approver final (HR; HR/Finance untuk klaim) **selesai di
  atasan langsung** — persetujuan atasan langsung sudah final (lihat
  `README.md` → *Bila yang mengajukan adalah HR sendiri*).
* Tombol/centang keputusan di UI hanya dirender bila `ApprovalEngine::canAct()`
  (atau `ExpenseModel::canDecide()` untuk klaim) menyatakan pengguna berhak.

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /settings?tab=menu` | `SettingsController@index` | Tab menu navigasi |
| `POST /settings/menu` | `SettingsController@saveMenu` | Simpan visibilitas / reset |
| `GET /` (dashboard) | `DashboardController@index` | Badge & ringkasan |
| `GET /notifications/latest` | `NotificationController@latest` | Polling lonceng |

Berkas: `app/Core/Nav.php`, `app/config/menu.php`, `app/Core/Router.php`,
`app/Core/Auth.php`, `app/Core/Controller.php`, `app/Views/layouts/{app,auth}.php`,
`app/Views/partials/{sidebar,topbar}.php`.

## 6. Catatan

* **Menyembunyikan menu bukan pengaman.** URL yang disembunyikan tetap bisa
  dibuka peran berhak; peran tak berhak tetap ditolak 403 oleh router.
* `requires_reports` hanya berlaku untuk non-HR: HR selalu melihat menu
  persetujuan walau tidak punya bawahan langsung.
* Saat satu route dipakai beberapa peran, kunci menu dibedakan (mis.
  `leave.manage` untuk HR, `leave.approvals` untuk atasan) agar visibilitas
  dapat diatur terpisah.
* Setelah mengubah `app/config/menu.php`, cache navigasi (`Nav::flush()`)
  perlu dijalankan — UI pengaturan melakukannya otomatis.
