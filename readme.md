# HRIS Enterprise — PT Asia Raya Group

Sistem Informasi Sumber Daya Manusia terpadu: data kepegawaian, jadwal & shift,
presensi geofence + selfie (PWA), lembur, cuti, komponen gaji, dan payroll
multi-unit dengan slip gaji ber-QR.

* **Backend** — PHP 8 (native, tanpa framework), arsitektur MVC ringan buatan sendiri
* **Database** — MySQL 8 / MariaDB 10.4+
* **PDF** — dompdf, dipasang **manual** di `vendor/dompdf` (bukan via Composer)
* **QR code** — library online (`qrserver`) dengan fallback lokal `phpqrcode`
* **UI** — CSS & JS tulisan sendiri, tanpa framework/CDN, responsif (mobile-first PWA)

---

## 1. Status pengembangan (batch)

| Batch | Cakupan | Status |
|-------|---------|--------|
| **1** | Fondasi: autentikasi (username/password + Google), RBAC (HR/FIN/Karyawan), layout & design system, dashboard HR / Finance / Karyawan, profil, pencarian, notifikasi, mesin approval 2 tahap, installer | ✅ selesai |
| **2** | Pengaturan perusahaan, unit organisasi (business unit, departemen, posisi, logo), format ID karyawan otomatis, direktori & CRUD karyawan (kontrak, foto, resign) | ✅ selesai |
| **3** | Shift & jadwal kerja, penjadwalan per unit/departemen/karyawan (matriks harian), pengajuan & approval perubahan jadwal 2 tahap | ✅ selesai |
| **4** | Presensi PWA (GPS geofence + selfie ber-cap waktu), live monitoring, presensi harian, rekap bulanan + export CSV, pengaturan toleransi/denda/alpha | ✅ selesai |
| **5** | Lembur: pengajuan mandiri, approval dua tahap, lembur terjadwal massal, jam aktual, rekap & export CSV | ✅ selesai |
| 6 | Cuti: jenis & jatah, pengajuan, approval, saldo | ⏳ |
| 7 | Komponen gaji (CRUD & assign), PPh, BPJS | ⏳ |
| 8 | Payroll: periode per unit, kalkulasi, rekap per unit/departemen, slip gaji PDF ber-QR, alur HR → FIN → PAID | ⏳ |
| 9 | Laporan & cetak (PDF Excel-ready), log aktivitas, pengaturan lanjutan | ⏳ |

Modul yang belum aktif tetap dapat diklik dan menampilkan halaman "modul
dijadwalkan" — tidak ada tautan mati. Skema database untuk seluruh modul sudah
dipasang sejak batch 1 sehingga batch berikutnya tidak memerlukan migrasi besar.

---

## 2. Instalasi produksi (Apache/Nginx + PHP-FPM + MySQL)

### 2.1 Kebutuhan server
* PHP **8.0+** dengan ekstensi: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `json`, `zip`
  (opsional: `curl` untuk Google SSO, `dom` untuk dompdf)
* MySQL 8 / MariaDB 10.4+
* Web server Apache (mod_rewrite) atau Nginx

### 2.2 Langkah
1. Upload seluruh repository ke server, arahkan **document root** ke folder `public/`.
2. Pasang dompdf secara manual (tanpa Composer):

```bash
cd vendor
curl -L -o dompdf.zip https://github.com/dompdf/dompdf/releases/download/v3.1.6/dompdf-3.1.6.zip
unzip dompdf.zip && mv dompdf-3.1.6 dompdf && rm dompdf.zip
# dependensi wajib dompdf (juga manual):
#   vendor/php-font-lib        -> dompdf/php-font-lib (tag 1.0.2)
#   vendor/php-svg-lib         -> dompdf/php-svg-lib
#   vendor/php-css-parser      -> sabberworm/php-css-parser
#   vendor/html5               -> Masterminds/html5-php
```

   Autoloader di `app/bootstrap.php` sudah memetakan namespace
   `Dompdf\`, `FontLib\`, `Svg\`, `Sabberworm\CSS\`, dan `Masterminds\` ke folder
   tersebut sehingga dompdf langsung dapat dipakai tanpa Composer.

3. QR code lokal (opsional, bila tidak ingin memakai API online):

```bash
cd vendor && curl -L -o qr.zip https://github.com/t0k4rt/phpqrcode/archive/refs/heads/master.zip
unzip qr.zip && mv phpqrcode-master phpqrcode && rm qr.zip
```

4. Beri izin tulis pada `storage/`:

```bash
chmod -R 775 storage app/config
```

5. Buka `https://domain-anda/install.php`, isi kredensial MySQL, jalankan instalasi.
   Atau salin `app/config/env.example.php` → `app/config/env.php` lalu import skema:

```bash
mysql -u user -p asiaraya_hris < database/schema.sql
```

6. **Hapus/rename `public/install.php`** setelah selesai.
7. Login pertama: `hr@asiaraya.co.id` / `Password123!` (ganti kata sandi segera).

### 2.3 Login dengan Google Workspace
1. Buat OAuth Client (Web application) di Google Cloud Console.
2. Authorized redirect URI: `https://domain-anda/login/google/callback`.
3. Isi `google_client_id` & `google_client_secret` pada `app/config/env.php`
   atau melalui halaman **Pengaturan HR & Payroll**.
4. Pemetaan akun memakai **field email karyawan** — user Google hanya bisa masuk
   bila emailnya terdaftar pada tabel `employees` dengan flag `ACTIVE`.
   Domain Workspace perusahaan otomatis dibatasi melalui parameter `hd`.

---

## 2.4 Modul Batch 2

### Unit Organisasi (`/organisation`)
* Tab **Business Unit** — kode unik (dipakai token `{BU}`), tipe, kota, alamat,
  koordinat + radius geofence, cut-off & tanggal pembayaran payroll per unit, logo.
* Tab **Departemen** — kode (token `{DEPT}`), kepala departemen, filter per unit.
* Tab **Posisi & Level** — standar gaji pokok yang otomatis mengisi form karyawan.
* Penghapusan dijaga: unit/departemen/posisi yang masih dipakai akan menolak
  dihapus dengan pesan dependensi; gunakan status nonaktif sebagai alternatif.

### Format ID Karyawan
* Pola default `ARG-{BU}-{SEQ:4}` → `ARG-JKT-0124`.
* Token tersedia: `{COMPANY}` `{BU}` `{DEPT}` `{YEAR}` `{YY}` `{MONTH}` `{SEQ:n}`.
* Nomor urut disimpan di tabel `id_sequences`; cakupan `BU` (per unit) atau
  `GLOBAL`. Counter di-seed otomatis dari data karyawan yang sudah ada, sehingga
  aman diterapkan pada database lama. Menyimpan format baru mereset counter.

### Data Karyawan (`/employees`)
* Direktori dengan pencarian, filter unit/departemen/status/flag, pengurutan, dan
  paginasi 15 baris.
* Kartu ringkas: karyawan aktif, kontrak ≤ 45 hari, habis kontrak, resign.
* Form tambah/ubah dibagi per bagian: identitas, penempatan & atasan langsung,
  kontrak & remunerasi (`PKWT`/`Magang`/`Outsourcing` wajib tanggal akhir),
  bank/BPJS/NPWP, role & kata sandi, serta foto (GD resize ≤ 640 px).
* Halaman detail: masa kerja, status kontrak, presensi bulan ini, saldo cuti,
  slip gaji terakhir, log aktivitas, dan aksi **tandai resign** (soft, data tetap).
* Flag kontrak diperbarui otomatis saat direktori dibuka.

---

## 2.5 Modul Batch 3

### Jadwal Kerja & Shift (`/shifts`)
* Kartu shift: jam masuk/pulang, durasi kerja bersih (dikurangi istirahat), jam
  istirahat, toleransi keterlambatan, hari kerja, tipe operasional
  (`NORMAL`/`SHIFT`/`WFH`/`FIELD`), warna penanda, dan cakupan unit.
* Shift lintas hari (mis. 22:00 → 07:00) dihitung otomatis.
* Shift yang sudah dipakai penugasan/pengajuan tidak dapat dihapus — nonaktifkan.

### Penjadwalan Karyawan (`/schedules`)
* **Matriks harian** karyawan × tanggal dengan chip shift berwarna; sel kosong
  dapat diklik untuk menetapkan shift pada tanggal tersebut (form terisi otomatis).
* **Penugasan massal** per karyawan terpilih, per business unit, per departemen,
  atau seluruh karyawan aktif, dengan opsi melewati hari non-kerja shift.
* **Pemecahan rentang otomatis**: mengubah satu atau beberapa hari di tengah
  rotasi bulanan akan memotong rentang lama menjadi sisa sebelum + penugasan baru
  + sisa sesudah, sehingga rotasi lain tidak hilang.
* Daftar penugasan berjalan dengan rentang, durasi hari, sumber, dan catatan.

### Perubahan Jadwal (`/schedules/requests`, `/me/schedule`)
* Karyawan melihat kalender jadwal pribadi (30 hari, bisa diubah rentangnya),
  shift hari ini, dan riwayat pengajuan.
* Pengajuan: shift pengganti, rentang tanggal, alasan. Satu karyawan hanya boleh
  memiliki satu pengajuan berjalan; tanggal lampau ditolak.
* Alur approval: **atasan langsung** (`PENDING → MANAGER_APPROVED`) → **HR**
  (`→ APPROVED`). Hanya persetujuan final HR yang mengubah tabel `schedules`,
  dan hanya pada rentang yang diminta.
* Setiap keputusan mengirim notifikasi ke karyawan; penolakan tidak mengubah jadwal.

---

## 2.6 Modul Batch 4 — Presensi

### Presensi Saya (`/me/attendance`) — PWA
* Halaman mobile-first: jam live, shift hari ini, radius geofence unit, dan tombol
  **Check In / Check Out** yang aktif hanya bila syarat terpenuhi.
* **Geofence**: jarak dihitung dengan formula haversine antara koordinat GPS
  (`watchPosition`, akurasi tinggi) dan titik kantor unit + radius. Di luar radius
  presensi ditolak dengan pesan jarak sebenarnya; shift bertipe `WFH`/`FIELD` dapat
  melewati validasi ini (dapat dimatikan di pengaturan).
* **Selfie**: diambil dari kamera (`getUserMedia`) atau unggahan foto, dikompres di
  browser (lebar & kualitas dari pengaturan), lalu diproses ulang di server dengan GD
  dan diberi **cap waktu** di sudut foto. Berkas disimpan di
  `storage/uploads/attendance` dan disajikan lewat `/media/attendance/...`.
* Status dihitung server-side: `PRESENT` bila masih dalam toleransi, `LATE` beserta
  jumlah menit terlambat, `work_minutes` dan `early_leave_minutes` saat check-out.
* Mode demo sandbox menyediakan tombol **Simulasi presensi** (hanya ketika
  `demo_mode` aktif) untuk mencoba alur tanpa kamera/GPS sungguhan.

### Live Monitoring (`/attendance/live`)
* KPI: tingkat kehadiran, karyawan masih di kantor (sudah check-in belum check-out),
  belum presensi, dan status geofence per unit.
* Bar kehadiran per business unit, daftar karyawan yang belum check-in, dan tabel
  log presensi dengan jarak geofence, menit terlambat, selfie (klik untuk ukuran
  penuh), sumber presensi, serta penyegaran otomatis tiap 30 detik.

### Presensi Harian (`/attendance/daily`)
* Filter tanggal, unit, departemen, status (termasuk *masih di kantor* dan *belum
  presensi*), serta pencarian nama/NIK.
* Tombol **Tandai Alpha** membuat baris `ABSENT` bagi karyawan aktif tanpa catatan
  pada tanggal tersebut, dan **Export CSV** (Excel-ready, `;` separator + BOM).

### Rekap Bulanan (`/attendance/rekap`)
* Tren kehadiran harian (grafik batang), total hadir/telat/alpha/cuti, jam kerja, dan
  **denda keterlambatan per karyawan** mengikuti kebijakan aktif.
* Export CSV per periode, filter unit/departemen, navigasi bulan sebelumnya/berikutnya.

### Pengaturan Presensi (`/attendance/settings`)
* Jam kerja standar & toleransi (dipakai bila karyawan tanpa jadwal shift).
* Validasi: wajib selfie, wajib radius geofence, bypass untuk WFH/kerja lapangan.
* Denda keterlambatan: `PER_MINUTE`, `PER_DAY`, `FIXED`, atau `PERCENT`, lengkap
  dengan tabel simulasi; serta penanganan alpha (potong cuti/gaji).
* Kualitas foto: lebar maksimum dan kualitas JPEG.
* Rincian radius tiap unit ditampilkan di sini; perubahan koordinat dilakukan pada
  halaman Unit Organisasi.

> **Catatan produksi:** kamera dan GPS memerlukan **HTTPS** (secure context) serta
> izin pengguna pada browser. Semua perhitungan jarak, status, dan denda tetap
> divalidasi ulang di server sehingga tidak dapat dimanipulasi dari sisi klien.

---

## 2.7 Modul Batch 5 — Lembur

### Manajemen Lembur (`/overtime`) — HR
* KPI: menunggu atasan, menunggu HR, jam lembur disetujui, dan kompensasi bulan berjalan.
* Grafik sebaran jam lembur harian (akhir pekan/hari besar ditandai) + navigasi bulan.
* Tabel pengajuan dengan tab status (`Perlu Tindakan`, `Menunggu Atasan`, `Menunggu HR`,
  `Disetujui`, `Ditolak`), filter unit/departemen/jenis/pencarian, dan **export CSV**
  (siap dibuka di Excel; pemisah `;` + BOM).
* Aksi per baris: setujui/tolak (tahap HR), isi **jam aktual**, tombol *isi dari presensi*
  (menghitung selisih check-out dengan jam selesai lembur), dan pembatalan.
* **Keputusan massal**: centang beberapa pengajuan → setujui/tolak sekaligus dengan catatan.
* Panel kanan: kebijakan lembur, simulasi tarif, jam per unit, dan 5 karyawan dengan jam terbanyak.

### Lembur Terjadwal (`/overtime/schedules`) — HR
* Form penugasan massal: cakupan **seluruh unit**, **departemen**, atau **karyawan terpilih**
  (multi-select), rentang tanggal (maks 31 hari), jam mulai–selesai, preset cepat 1–4 jam.
* Opsi **lewati Sabtu/Minggu**, **lewati hari besar**, dan **langsung disetujui**.
* Validasi otomatis per karyawan: bentrok jam, duplikat tanggal, durasi < batas minimum,
  dan batas maksimum jam/hari — entri yang gagal dilewati dan dilaporkan.
* Daftar entri terjadwal dengan jam aktual, kompensasi, kalender jam bulanan, dan rekap per unit.
* Karyawan menerima notifikasi in-app untuk setiap penugasan terjadwal.

### Lembur Saya (`/me/overtime`) — semua peran
* Ringkasan bulan: jam disetujui, pengajuan berjalan, kompensasi, dan batas harian.
* Formulir pengajuan (tanggal, jam, uraian) dengan **estimasi durasi & kompensasi real-time**
  mengikuti tarif pribadi karyawan.
* Riwayat lengkap (pengajuan + penugasan HR) dengan indikator progres atasan → HR,
  serta pembatalan mandiri selama masih `PENDING`.

### Aturan perhitungan
| Komponen | Nilai |
| --- | --- |
| Upah per jam | gaji pokok ÷ `overtime_divisor` (default 173) |
| Jam pertama | ×`overtime_first_hour_multiplier` (1,5) |
| Jam berikutnya | ×`overtime_next_hour_multiplier` (2) |
| Hari libur (Sabtu/Minggu & `holidays`) | dikali `overtime_holiday_multiplier` (2) |
| Batas jam per hari | `overtime_max_hours` (default 4 jam) — **ditolak** bila durasi melebihi batas |
| Kompensasi maksimum | `overtime_max_compensation` (default Rp3.500.000) — nominal dipotong, `0` = tanpa batas |
| Durasi minimum | `overtime_min_minutes` (30 menit) |

Status: `PENDING` → `MANAGER_APPROVED` → `APPROVED` (final), dengan `REJECTED` /
`CANCELLED` sebagai akhir alternatif. Kompensasi baru dihitung saat status menjadi
`APPROVED` dan dihitung ulang ketika jam aktual diubah — nilai inilah yang dipakai payroll.
Rumus kompensasi dipusatkan di `OvertimeModel::compensation()` sehingga approval engine,
halaman lembur, dan payroll selalu konsisten.

### Batas jam & kompensasi (dapat diatur approver)
* **Batas jam** ditegakkan pada semua jalur: pengajuan karyawan, entri manual HR,
  penjadwalan massal, perubahan jam rencana, dan pengisian jam aktual. Permintaan di atas
  batas **ditolak dengan pesan jelas** (bukan dipotong diam-diam), menyebutkan batas aktif.
* **Kompensasi maksimum** per pengajuan diterapkan saat perhitungan: nominal penuh tetap
  dihitung sebagai `amount_uncapped`, lalu dipotong ke batas. Baris yang terkena batas
  ditandai `batas Rp…`; lembur yang sudah disetujui sebelum kebijakan berubah tidak
  dihitung ulang dan diberi keterangan `di atas batas baru`.
* **Approver (HR) mengatur nilai maksimum** langsung dari panel *Kebijakan Lembur →
  Ubah batas & multiplier* di `/overtime` (route `POST /overtime/policy`): maksimum jam/hari
  (1–12), maksimum kompensasi (0 = tanpa batas), durasi minimum, pembagi upah, dan
  multiplier jam pertama/berikutnya/hari libur. Validasi menolak nilai di luar rentang
  atau bila multiplier jam berikutnya lebih kecil dari jam pertama, dan setiap perubahan
  dicatat pada log aktivitas (`overtime.policy`).
* Tabel simulasi pada panel kebijakan langsung memakai batas baru sehingga approver dapat
  melihat dampak perubahan sebelum menyimpan.

---

## 3. Struktur proyek

```
app/
  bootstrap.php          # autoloader, config, session, DB, settings, auth
  config/config.php      # default + override env.php + environment variables
  Core/                  # Database, Router, Auth, View, Session, Settings,
                         # Validator, Schema, Audit, Nav, GoogleAuth, helpers
  Controllers/           # Auth, Dashboard, Approval, Org, Employee, Media, Shift, Schedule, Attendance, Overtime, Placeholder
  Models/                # Dashboard, Org, Employee, Schedule, Attendance, Overtime (query/aggregation layer)
  Views/                 # layouts, partials, auth, dashboard, errors
database/
  schema.sql             # skema kanonik MySQL (portabel; dipakai SQLite preview)
  seed.php               # data contoh PT Asia Raya Group
public/
  index.php              # front controller (semua route terdaftar di sini)
  install.php            # wizard instalasi
  assets/{css,js,img}    # design system, progressive enhancement, aset visual
  manifest.webmanifest   # PWA
  sw.js                  # service worker (offline shell aset)
vendor/                  # dompdf & phpqrcode (manual, tidak via Composer)
tools/preview/           # runtime pratinjau sandbox (bukan untuk produksi)
storage/                 # logs, cache, uploads, database preview
```

---

## 4. Matriks hak akses

| Modul | HR | FIN | Karyawan |
|-------|----|-----|----------|
| Data karyawan (CRUD, kontrak, foto, resign) | ✅ | – | baca (diri sendiri) |
| Unit organisasi (unit/departemen/posisi), format ID, pengaturan perusahaan | ✅ | – | – |
| Shift & jadwal (master shift, matriks penjadwalan) | ✅ | – | lihat jadwal sendiri |
| Approval perubahan jadwal | ✅ | – | pengajuan + persetujuan atasan |
| Presensi (live monitoring, harian, rekap, pengaturan) | ✅ | – | presensi diri (PWA) |
| Lembur (approval, setting, terjadwal) | ✅ | – | pengajuan |
| Cuti (approval, jenis & jatah) | ✅ | – | pengajuan |
| Komponen gaji & payroll | ✅ proses & kirim | terima, bayar (PAID), cetak | slip sendiri |
| Laporan | ✅ | ✅ | – |
| Log aktivitas | ✅ | – | – |

Alur approval berjenjang: **Direct Manager → HR**. Finance hanya menerima data
payroll final, menandai pembayaran, dan mencetak laporan. Slip gaji ditandatangani
oleh HR dan FIN (nama + timestamp) serta disematkan QR validasi.

---

## 5. Pratinjau di sandbox (tanpa PHP/MySQL terpasang)

Sandbox Arena tidak menyediakan binary PHP maupun server MySQL, sehingga repo
menyertakan jembatan pratinjau berbasis **php-wasm (PHP 8.4)** + **SQLite**.
Kode aplikasi tetap ditulis untuk MySQL; SQL dijaga pada subset portabel.

```bash
cd tools/preview
npm install        # sekali saja
PORT=8080 node server.mjs
```

Server akan otomatis membuat `app/config/env.php` (profil SQLite + demo) bila
belum ada, memasang skema, dan mengisi data contoh. Folder ini **tidak**
diperlukan pada server produksi dan aman untuk dihapus.

---

## 6. Catatan keamanan
* Kata sandi: `password_hash()` bcrypt; CSRF token pada seluruh form POST.
* Semua query memakai prepared statement (PDO), tanpa konkatenasi input.
* Cookie sesi `HttpOnly`, `SameSite=Lax`, `Secure` otomatis pada HTTPS.
* Audit trail: login, login gagal, approval, perubahan data.
* Upload disimpan di `storage/uploads` (di luar document root) dan disajikan
  melalui rute aplikasi `/media/{folder}/{file}` yang memvalidasi sesi, ekstensi,
  dan memastikan path tetap berada di dalam folder upload. Pada produksi rute ini
  boleh dipetakan langsung oleh web server untuk performa.
* Matikan `demo_mode` dan hapus data contoh sebelum go-live.
