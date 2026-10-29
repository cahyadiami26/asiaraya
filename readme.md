# HRIS Enterprise — PT Asia Raya Group

Sistem Informasi Sumber Daya Manusia terpadu: data kepegawaian, jadwal & shift,
presensi geofence + selfie (PWA), lembur, cuti, komponen gaji, klaim &
reimbursement, papan pengumuman + notifikasi in-app, serta payroll multi-unit
dengan slip gaji ber-QR.

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
| **6** | Cuti: jenis & jatah, saldo tahunan (carry over), pengajuan + lampiran, approval dua tahap, integrasi presensi | ✅ selesai |
| **7** | Komponen gaji: master tunjangan/potongan, penugasan per karyawan/unit/departemen, simulasi take-home (PPh 21 TER/flat/progresif + BPJS), pengaturan payroll & BPJS | ✅ selesai |
| **8** | Payroll: periode per unit, kalkulasi slip dari presensi/lembur/komponen, rekap per departemen, slip gaji PDF ber-QR + verifikasi publik, alur HR → FIN → PAID | ✅ selesai |
| **9** | Laporan & cetak (PDF & CSV Excel-ready) untuk presensi/lembur/cuti/payroll, log aktivitas (audit trail) + retensi, pengaturan lanjutan (penandatangan laporan) | ✅ selesai |
| **10** | Keamanan akun & sesi: reset kata sandi mandiri (tautan sekali pakai), ganti sandi dengan verifikasi sandi lama, "ingat perangkat" 30 hari, batas sesi 8 jam / idle 2 jam, proteksi brute-force | ✅ selesai |
| **11** | Klaim & reimbursement: kategori dengan plafon, pengajuan + bukti, persetujuan atasan → HR/Finance, aksi massal, export CSV, integrasi otomatis ke slip payroll | ✅ selesai |
| **12** | Pengumuman & notifikasi in-app: papan pengumuman dengan audiens (semua/unit/departemen/jabatan/peran), jadwal terbit, pin, pelacakan dibaca, pusat notifikasi + lonceng | ✅ selesai |
| **13** | Penilaian kinerja: periode penilaian (self → atasan → kalibrasi HR → tutup), master indikator berbobot, self assessment karyawan, penilaian atasan, kalibrasi/nilai final + rating A–E, riwayat & konfirmasi hasil, export CSV/PDF | ✅ selesai |

Seluruh tiga belas batch sudah aktif — tidak ada lagi tautan mati atau menu
"Segera". Skema database dipasang sejak batch 1 sehingga setiap batch baru
cukup menambah query, rute, dan tampilan; penambahan tabel baru mengikuti
migrasi otomatis bernomor versi (`Schema::VERSION`).

---

## 2. Instalasi produksi (Apache/Nginx + PHP-FPM + MySQL)

### 2.1 Kebutuhan server
* PHP **8.0+** dengan ekstensi: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `json`, `zip`, `dom`
  (opsional: `curl` untuk Google SSO dan QR online)
* MySQL 8 / MariaDB 10.4+
* Web server Apache (mod_rewrite) atau Nginx

### 2.2 Langkah
1. Upload seluruh repository ke server, arahkan **document root** ke folder `public/`.
2. **Pustaka pihak ketiga sudah disertakan** di `vendor/` (dipasang manual, bukan Composer):

```
vendor/autoload.php    # autoloader manual (PSR-4 + classmap dompdf)
vendor/dompdf/         # dompdf 3.1.6 (LGPL-2.1) + font DejaVu Sans/Serif
vendor/php-font-lib/   # dompdf/php-font-lib 1.0.2
vendor/php-svg-lib/    # dompdf/php-svg-lib 1.0.2
vendor/html5-php/      # masterminds/html5 2.12.0
vendor/phpqrcode/      # fallback QR lokal
```

   Tidak ada langkah `composer install`. Autoloader `app/bootstrap.php` memetakan
   namespace `Dompdf`, `FontLib`, `Svg`, dan `Masterminds` ke folder di atas, termasuk
   `vendor/dompdf/lib/Cpdf.php`. Bila ingin memperbarui dompdf, ganti isi `vendor/dompdf`
   (tag `v3.1.6`) beserta ketiga dependensinya.

3. QR code slip gaji memakai **API online** `api.qrserver.com` (default) dan otomatis
   beralih ke `vendor/phpqrcode` bila server tidak dapat mengakses internet. Hasil QR
   di-cache di `storage/uploads/qr/`.

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

## 2.8 Modul Batch 6 — Cuti

### Perhitungan hari cuti
* Hanya **hari kerja** yang dihitung: Sabtu, Minggu, dan tanggal pada tabel `holidays`
  dilewati. Pengajuan Jumat→Senin bernilai **2 hari**, bukan 4.
* Hari besar yang dilewati ditampilkan per pengajuan (mis. *"Melewati hari besar: Hari
  Kemerdekaan RI (tidak dihitung cuti)"*).

### Jenis & Jatah Cuti (`/leave/types`) — HR
* CRUD jenis cuti: kode, nama, jatah tahunan, batas per pengajuan, minimal pemberitahuan,
  warna label, serta flag **dibayar**, **wajib lampiran**, **carry over**, **potong gaji**,
  dan status aktif.
* Saat mengubah jatah, tersedia opsi **sinkronkan jatah** ke seluruh baris saldo tahun
  berjalan dan seterusnya.
* Jenis yang sudah dipakai pengajuan/saldo tidak dapat dihapus (ditolak dengan penjelasan);
  gunakan nonaktif agar riwayat tetap utuh.

### Saldo Cuti (`/leave/balances`) — HR
* Tabel saldo per karyawan × jenis cuti × tahun: jatah, carry over, terpakai, sisa, dengan
  penyuntingan langsung (jatah & carry) pada tiap baris.
* Filter unit/departemen/jenis/tahun serta sorotan **Menipis (≤2 hari)** dan **Minus**.
* **Generate saldo tahunan** untuk semua karyawan aktif × jenis aktif (opsional per unit,
  opsional setel ulang jatah). Baris yang sudah ada dilewati sehingga aman diulang dan
  tidak menghapus pemakaian.

### Manajemen Cuti (`/leave`)
* KPI: menunggu atasan, menunggu HR, hari cuti disetujui, saldo terpakai.
* Grafik hari cuti per jenis + daftar cuti berjalan (disetujui, tanggal ≥ hari ini).
* Antrean pengajuan dengan tab status, filter jenis/unit/departemen/year/pencarian,
  tombol **Setujui/Tolak** (atasan pada tahap 1, HR pada tahap final), lampiran yang bisa
  dibuka, pembatalan oleh HR (saldo dikembalikan otomatis), dan **Export CSV** per tahun.
* **Atasan langsung** membuka halaman yang sama dan hanya melihat pengajuan anggota timnya
  (blok KPI/filter HR disembunyikan); pengajuan milik sendiri tidak dapat diputus sendiri.

### Cuti Saya (`/me/leave`)
* Kartu saldo per jenis (sisa, jatah, terpakai, carry) + ringkasan tahun.
* Formulir pengajuan dengan **perhitungan hari kerja & validasi real-time** di browser
  (batas per pengajuan, minimal pemberitahuan, sisa saldo) yang tetap divalidasi ulang di
  server; unggah lampiran PDF/JPG/PNG (maks 5 MB) untuk jenis yang mewajibkannya.
* Riwayat 40 pengajuan terakhir dengan progres atasan → HR, lampiran, dan pembatalan
  mandiri selama masih `PENDING`.

### Efek persetujuan (integrasi lintas modul)
| Tahap | Efek |
| --- | --- |
| Atasan menyetujui | status `MANAGER_APPROVED`, notifikasi ke karyawan |
| HR menyetujui (final) | status `APPROVED`, **saldo `used` bertambah**, hari kerja pada rentang ditandai `LEAVE` di tabel presensi |
| Ditolak / dibatalkan | saldo **tidak** berubah; bila cuti yang sudah `APPROVED` dibatalkan, `used` dikurangi kembali dan penanda presensi dihapus |
| Presensi | baris yang sudah ada (mis. karyawan tetap masuk) **tidak** ditimpa; hanya baris kosong/`ABSENT` yang diubah menjadi `LEAVE` |

Validasi server pada pengajuan: jenis aktif, `end ≥ start`, rentang ≤ `leave_max_range_days`
(default 90 hari), minimal 1 hari kerja, batas `max_per_request`, minimal pemberitahuan,
alasan wajib, lampiran wajib bila jenis mensyaratkannya, tidak beririsan dengan pengajuan
lain, serta saldo mencukupi kecuali `leave_allow_negative` diaktifkan.

---

## 2.9 Modul Batch 7 — Komponen Gaji & Pengaturan Payroll

### Master komponen (`/components`) — HR
* Dua kelompok: **Tunjangan** (menambah bruto) dan **Potongan** (mengurangi take-home
  di luar BPJS & PPh).
* CRUD penuh: kode unik, nama, tipe perhitungan **nominal tetap / persentase / prorata**,
  dasar persentase (gaji pokok atau bruto), flag **objek PPh 21**, **dasar BPJS**,
  **terapkan otomatis** ke karyawan aktif, serta aktif/nonaktif.
* Nilai persentase dibatasi 0–100%; nominal tidak boleh negatif.
* Kode sistem (`BASIC`, `BPJS_KES_EE`, `BPJS_JHT_EE`, `BPJS_JP_EE`, `PPH21`) tidak dapat
  dihapus; komponen yang masih ditugaskan juga ditolak saat dihapus (nonaktifkan dulu).

### Penugasan komponen
* Tiga cakupan: **karyawan terpilih** (multi-select), **seluruh karyawan aktif pada unit**,
  atau **seluruh karyawan aktif pada departemen**.
* Setiap baris penugasan menyimpan `amount_override` (override nominal per karyawan,
  isi `0` untuk meniadakan), `effective_date`, `end_date`, dan status aktif.
* Penugasan massal **tidak menimpa** override/end-date khusus yang sudah ada: nilai kosong
  berarti "pertahankan nilai karyawan".
* Tabel penugasan bisa difilter komponen/unit/departemen/pencarian, diubah inline,
  atau dilepas satu per satu; tombol **Terapkan Komponen Default** menugaskan seluruh
  komponen bertanda default ke semua karyawan aktif sekaligus.
* KPI halaman: komponen aktif (tunjangan/potongan), jumlah penugasan, cakupan karyawan (%),
  dan jumlah komponen default; dilengkapi bilah cakupan per unit.

### Simulasi take-home pay
Mesin hitung `SalaryModel::preview()` (dipakai juga oleh modal **Simulasi** dan endpoint
`/components/simulate/{employee}`):

| Langkah | Rumus |
| --- | --- |
| Bruto | gaji pokok + seluruh tunjangan aktif (persentase berbasis BASIC dihitung lebih dulu, lalu berbasis GROSS) |
| BPJS karyawan | Kesehatan `min(upah, plafon) × %`, JHT `upah × %`, JP `min(upah, plafon) × %` |
| Dasar PPh | bruto − komponen tunjangan non-objek pajak − iuran BPJS karyawan |
| PPh 21 | **TER** (tarif efektif rata-rata berjenjang, PP 58/2023), **FLAT** (satu tarif), atau **PROGRESSIVE** (proyeksi setahun: bruto × 12 − biaya jabatan 5% (maks Rp 6 jt) − PTKP, lalu lapisan UU HPP 5/15/25/30/35%) |
| Potongan lain | seluruh komponen potongan aktif (mis. koperasi, absensi) |
| Take home | bruto − BPJS karyawan − PPh 21 − potongan lain |
| Biaya perusahaan | bruto + BPJS perusahaan (Kesehatan, JHT, JP, JKK, JKM) |

PTKP mengikuti status karyawan: TK/0 54 jt, TK/1 & K/0 58,5 jt, TK/2 & K/1 63 jt,
TK/3 & K/2 67,5 jt, K/3 72 jt. Pembulatan PPh mengikuti `payroll_pph_round` (default Rp 100).

### Pengaturan HR & Payroll (`/settings`) — HR
Empat tab, seluruhnya tersimpan di tabel `settings`:

| Tab | Isi |
| --- | --- |
| **Payroll** | tanggal cutoff (default 25), tanggal pembayaran (28), cutoff otomatis; ringkasan estimasi biaya 5 karyawan bergaji tertinggi |
| **PPh 21** | aktif/nonaktif, metode (TER/FLAT/PROGRESSIVE), tarif flat 0–35%, contoh perhitungan gaji terendah & tertinggi, tabel lapisan UU HPP |
| **BPJS** | toggle Kesehatan & Ketenagakerjaan, persentase karyawan/perusahaan (Kesehatan 1%/4%, JHT 2%/3,7%, JP 1%/2%, JKK 0,24%, JKM 0,3%), plafon upah Kesehatan Rp 12 jt & JP Rp 10 jt, pratinjau dampak ke take-home |
| **Integrasi & Login** | tombol Google Sign-In on/off, OAuth Client ID, URI callback, ringkasan identitas perusahaan (edit di `/company`) |

Validasi server: tanggal 1–31, metode PPh dikenal, tarif 0–35%, rentang persentase tiap
iuran, dan plafon upah 0–100 jt. Semua perubahan langsung dipakai simulasi berikutnya.

---

## 2.10 Modul Batch 8 — Payroll & Slip Gaji

### Periode Payroll (`/payroll`) — HR & Finance
* Satu periode = **satu business unit + satu bulan**; duplikat unit/bulan ditolak.
  Tanggal mulai/akhir, `cutoff_date`, dan `pay_date` otomatis mengikuti
  `payroll_cutoff_day` & `payroll_pay_day` (dipotong ke jumlah hari bulan tersebut).
* Daftar periode: filter unit/tahun/status/pencarian, KPI (periode aktif, menunggu
  finance, net & BPJS perusahaan tahun berjalan), tren net 6 periode, dan paginasi.
* HR membuat/menghapus periode; HR dan Finance sama-sama dapat membuka detail.

### Kalkulasi slip
`POST /payroll/{id}/calculate` menghitung ulang seluruh karyawan aktif pada unit itu:

| Komponen | Sumber |
| --- | --- |
| Gaji pokok | `employees.basic_salary` |
| Tunjangan/potongan | komponen aktif karyawan (`SalaryModel::preview`) |
| Bruto | gaji pokok + tunjangan + **lembur disetujui** (`overtime_requests.status = APPROVED`) |
| Kehadiran | `attendance`: hadir, absen, hari & menit terlambat, cuti |
| Denda keterlambatan | `late_minutes × attendance_late_deduction_value` (PER_MINUTE/PER_DAY/FIXED) |
| Potongan absen | opsional, aktif bila `attendance_absent_deduct_salary = 1` (upah harian = pokok/22) |
| Cuti tanpa upah | `leave_requests` APPROVED dengan jenis `is_paid = 0` atau `deduct_salary = 1` × upah harian |
| BPJS karyawan | Kesehatan + JHT + JP (plafon upah diterapkan) |
| PPh 21 | dihitung ulang atas dasar pengenaan **+ lembur**, metode TER/FLAT/PROGRESIF |
| Net | bruto − BPJS karyawan − PPh 21 − potongan komponen − denda/absen/cuti |

Setiap slip menyimpan nomor `SLIP/{KODE-UNIT}/{YYYYMM}/{urut}`, data presensi,
rincian komponen (`payroll_item_components`: `COMPONENT`/`OVERTIME`/`BPJS`/`TAX`/
`ATTENDANCE`), dan total periode (karyawan, bruto, potongan, net, BPJS perusahaan).

### Alur status
| Status | Aksi | Pelaku |
| --- | --- | --- |
| `DRAFT` | periode dibuat | HR |
| `CALCULATED` | **Hitung / Hitung Ulang** (boleh berkali-kali selama belum dikirim) | HR |
| `SENT_TO_FINANCE` | tanda tangan HR + notifikasi ke seluruh user Finance | HR |
| `FINANCE_APPROVED` | tanda tangan Finance; slip sudah dapat diunduh karyawan | FIN |
| `PAID` | semua item `payment_status = PAID` + notifikasi "slip tersedia" | FIN |

HR dapat **menarik kembali** periode `SENT_TO_FINANCE`/`FINANCE_APPROVED`
(tanda tangan dihapus, status kembali `CALCULATED`). Periode `PAID` tidak dapat diubah
dan periode yang sudah dikirim tidak dapat dihapus.

### Rekap & export
* Rekap per **departemen** (orang, bruto, potongan, net, PPh, BPJS) + komposisi
  potongan (PPh, BPJS karyawan, denda, absen/cuti).
* **Export CSV** `GET /payroll/{id}/export` — 16 kolom siap Excel (UTF-8 BOM): no. slip,
  NIK, nama, departemen, gaji pokok, hadir, absen, menit terlambat, jam lembur,
  tunjangan, bruto, BPJS karyawan, PPh 21, potongan, net, status.

### Slip gaji PDF ber-QR
* `GET /payroll/items/{id}/pdf` merender `app/Views/payroll/slip_pdf.php` dengan
  **dompdf** (A4, satu halaman): kop perusahaan + NPWP, data karyawan & rekening,
  kolom tunjangan/potongan berdampingan, blok take-home pay, ringkasan presensi,
  tanda tangan HR & Finance, dan QR verifikasi. `?download=1` memaksa unduhan.
* QR berisi URL **absolut** `GET /verify/slip/{id}/{token}`; token =
  `HMAC-SHA256("payslip:{id}", app_key)` sehingga tidak memerlukan tabel tambahan.
* Halaman verifikasi **publik** menampilkan "Dokumen Sah" dengan nama disamarkan,
  periode, unit, dan gaji bersih; token salah menampilkan "Dokumen tidak valid".
* Hak akses PDF: HR, Finance, atau karyawan pemilik slip — selain itu `404`.

### Slip Gaji Saya (`/me/payslip`) — semua peran
* Kartu ringkas (jumlah slip, total net, slip terakhir) + riwayat slip berstatus
  `FINANCE_APPROVED`/`PAID` dengan tautan detail dan unduh PDF.

## 2.11 Modul Batch 9 — Laporan & Cetak, Log Aktivitas

### Pusat Laporan (`/reports`) — HR & Finance
Empat laporan dengan tab terpisah, semuanya membaca data operasional terkini
(bukan tabel salinan): **Rekap Presensi**, **Rekap Lembur**, **Rekap Cuti**, dan
**Rekap Payroll**. Filter yang tersedia menyesuaikan jenis laporan:

| Laporan | Filter | Kolom kunci |
|---------|--------|-------------|
| Presensi | bulan, unit, departemen, pencarian | hadir, terlambat (hari/menit), absen, cuti, jam kerja, kehadiran %, tanpa check-out |
| Lembur | bulan, unit, departemen, pencarian | pengajuan, disetujui, menunggu atasan, menunggu HR, ditolak, jam disetujui, kompensasi |
| Cuti | tahun, unit, departemen, jenis cuti, pencarian | jatah, carry over, terpakai, sisa, disetujui, pengajuan menunggu + rekap per jenis |
| Payroll | tahun, unit, periode | ringkasan per periode/unit (bruto, potongan, net, BPJS perusahaan) atau detail slip bila periode dipilih |

* Setiap laporan punya baris **TOTAL** dan kartu KPI di atas tabel.
* `/reports/payroll` adalah alias langsung ke tab payroll (dipakai menu).

### Export CSV & PDF
* `GET /reports/{type}/csv` — CSV ber-BOM UTF-8 (langsung rapi di Excel),
  nama berkas `laporan-{type}-{periode}.csv`, satu baris TOTAL di akhir.
* `GET /reports/{type}/pdf` — dompdf A4 **landscape**, berkop identitas perusahaan
  (nama, alamat, telepon, email, NPWP dari `/company`), judul + periode,
  keterangan "Dicetak … oleh …", tabel zebra dengan angka rata kanan
  (format Rupiah/ribuan Indonesia), baris TOTAL, blok tanda tangan
  **Disusun oleh (HR)** & **Disetujui oleh (Finance)**, serta catatan kaki.
* Maksimal 400 baris per PDF (data lebih besar tetap lengkap di CSV); jumlah
  baris total selalu dicantumkan agar pemotongan transparan.
* Setiap ekspor dicatat ke audit trail (`report.export`).

### Log Aktivitas (`/logs`) — HR
* KPI: total log, aktivitas hari ini, pengguna aktif, login gagal.
* Filter: modul (login, payroll, komponen, pengaturan, karyawan, presensi,
  lembur, cuti, jadwal, organisasi), rentang tanggal, dan pencarian bebas
  (aksi/entitas/meta/IP) — 30 baris per halaman.
* Detail meta ditampilkan sebagai JSON yang bisa dibuka per baris; kolom IP dan
  perangkat membantu investigasi.
* `GET /logs/export` — CSV maksimal 5.000 baris terbaru sesuai filter.
* `POST /logs/purge` — hapus log lebih tua dari batas retensi (tombol
  "Bersihkan" dengan konfirmasi). Batas retensi diambil dari pengaturan,
  minimal 30 hari, dan aksi pembersihan itu sendiri ikut tercatat.

### Pengaturan Lanjutan (`/settings?tab=advanced`) — HR
* **Penandatangan laporan**: nama HR & Finance yang tercetak pada blok tanda
  tangan PDF (kosong = "HR Department" / "Finance Division").
* **Retensi log audit**: 30–1825 hari (default 180).
* Ringkasan status log (total baris, log terlama, batas retensi aktif) dan
  pintasan ke halaman log serta export CSV.

## 2.12 Modul Batch 10 — Keamanan Akun & Sesi

### Lupa kata sandi (`/forgot-password`) — publik
* Form email perusahaan. Respons **selalu sama** (tidak membocorkan apakah email
  terdaftar) dan diproses hanya untuk karyawan berstatus `ACTIVE`.
* Tautan reset dibuat dengan token 64 heksadesimal; yang disimpan hanya
  `sha256` token, berlaku **60 menit**, dan **sekali pakai** (`used_at`).
* Batas frekuensi: maksimal 1 permintaan/menit dan 5 per jam per email.
* Tanpa SMTP: tautan ditulis ke `storage/logs/password-reset.log` (dan
  ditampilkan langsung pada `demo_mode`). Setiap permintaan dicatat sebagai
  `password.request` di log aktivitas.

### Atur ulang kata sandi (`/reset-password?token=…`) — publik
* Token diverifikasi ulang saat form dibuka **dan** saat disimpan; token
  kedaluwarsa/dipakai ulang menampilkan halaman "Tautan tidak berlaku".
* Kata sandi baru minimal 8 karakter + konfirmasi, disimpan sebagai hash bcrypt
  dalam satu transaksi bersama penandaan `used_at` dan penghapusan token lain.
* Efek samping: seluruh token "ingat perangkat" milik karyawan dicabut
  (`password.reset` tercatat di audit trail), lalu pengguna diarahkan ke login.

### Ganti kata sandi dari profil (`/me/profile`)
* Mengisi kata sandi baru kini **wajib menyertakan kata sandi saat ini**;
  kesalahan verifikasi menampilkan pesan pada field tersebut dan tidak
  menyimpan apa pun.
* Perubahan dicatat sebagai `password.change`, dan semua perangkat lain yang
  memakai "ingat saya" otomatis dikeluarkan.

### Ingat perangkat (30 hari)
* Checkbox "Ingat perangkat ini selama 30 hari" membuat token
  `selector:validator` pada tabel `remember_tokens` (yang disimpan hanya
  `sha256(validator)`), cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` pada HTTPS).
* Setiap pemakaian token **dirotasi**; token kedaluwarsa/tidak cocok langsung
  dihapus. Logout dan reset kata sandi mencabut token terkait.
* Pemulihan sesi berjalan otomatis di bootstrap (`login_remember` di audit trail).

### Batas sesi & proteksi brute-force
* Sesi absolut **8 jam** (`session_life`) dan **idle 2 jam** (`session_idle`,
  dapat diubah di `app/config/config.php` atau env `HRIS_SESSION_IDLE`).
  Kedaluwarsa → sesi dibersihkan, `session.timeout` tercatat, pengguna kembali
  ke halaman login dengan pesan.
* Login dibatasi **5 kegagalan per 15 menit** untuk kombinasi identifier + IP.
  Percobaan berikutnya ditolak dengan pesan sisa waktu, dan aksi blokir
  tercatat sebagai `login_blocked` (aksi ini terlihat di `/logs`).
* Semua halaman autentikasi kini menampilkan pesan flash (mis. sesi berakhir,
  reset berhasil) — sebelumnya hanya halaman di dalam aplikasi.

## 2.13 Modul Batch 11 — Klaim & Reimbursement

### Kategori Klaim (`/claims/categories`) — HR
* Master kategori: kode, nama, deskripsi, **plafon per klaim** (opsional), dan
  penanda **wajib bukti**.
* Kategori yang sudah dipakai pengajuan tidak dapat dihapus — nonaktifkan agar
  riwayat tetap utuh (penghapusan hanya untuk kategori tanpa pemakaian).
* Contoh bawaan: Transportasi Lokal, Kesehatan & Medis, Perjalanan Dinas,
  Konsumsi & Jamuan, Komunikasi & Internet, Lain-lain.

### Klaim Saya (`/me/claims`) — semua peran
* Kartu ringkas: total klaim, nilai yang masih diproses, dibayarkan bulan ini,
  dan total reimbursement diterima.
* Formulir pengajuan: kategori, tanggal transaksi, nominal, keterangan, dan
  lampiran bukti (PDF/JPG/PNG maks 4 MB).
* Validasi: kategori aktif, nominal > 0 dan tidak melebihi plafon, tanggal tidak
  melewati hari ini dan tidak lebih lama dari **batas usia klaim** (default 90
  hari, diatur di `/settings?tab=advanced`), bukti wajib bila diminta kategori.
* Nomor klaim otomatis berformat `KLM/{UNIT}/{YYYYMM}/{0001}`.
* Atasan langsung otomatis mendapat **inbox "Persetujuan Klaim Tim"** di
  halaman yang sama; karyawan dapat membatalkan klaim selama belum disetujui final.

### Antrean Klaim (`/claims`) — HR & Finance
* Filter status (default: semua yang berjalan), kategori, unit, departemen,
  bulan transaksi, dan pencarian (nomor klaim/keterangan/nama/NIK).
* Lima kartu KPI: menunggu atasan, menunggu HR/Finance, siap dibayar (beserta
  nominal disetujui), masuk payroll, dan pembayaran bulan berjalan.
* **Aksi massal**: centang beberapa klaim → "Setujui Terpilih"/"Tolak Terpilih".
* `GET /claims/export` — CSV 5.000 baris terbaru sesuai filter, lengkap dengan
  baris TOTAL.

### Alur status
```
PENDING ──atasan──▶ MANAGER_APPROVED ──HR/Finance──▶ APPROVED
   │                      │                            │
   ├──ditolak atasan──▶ REJECTED                        ├──masuk payroll──▶ SCHEDULED ──payroll dibayar──▶ PAID
   └──dibatalkan pengaju──▶ CANCELLED                   └──transfer FIN──▶ PAID
```
Karyawan tanpa atasan langsung melewati tahap 1 (`PENDING` → `MANAGER_APPROVED`
otomatis). Setiap keputusan mengirim notifikasi ke pengaju; persetujuan atasan
juga memberi tahu HR.

### Integrasi payroll
* Saat periode payroll dihitung, klaim berstatus `APPROVED` yang tanggal
  transaksinya berada dalam rentang periode **otomatis masuk slip** sebagai
  komponen `REIMBURSEMENT` (jenis BENEFIT, tidak menambah dasar pengenaan PPh)
  dan ditandai `SCHEDULED`.
* Saat periode ditandai dibayar, klaim terkait berubah `PAID` beserta tanggal
  dan penerima pembayaran.
* Hitung ulang, reopen, atau hapus periode melepas kembali klaim menjadi
  `APPROVED` sehingga tidak ada tautan menggantung ke slip yang dihapus
  (idempoten — diuji hitung ulang dua kali tanpa duplikasi).
* Finance tetap dapat membayar klaim di luar payroll lewat tombol
  **"Tandai Dibayarkan"** pada halaman detail (khusus peran FIN).

### Detail klaim (`/claims/{id}`)
Halaman detail menampilkan rincian, lampiran (gambar dipratinjau, PDF dibuka di
tab baru), dan **timeline persetujuan**: pengajuan → atasan → HR/Finance →
pembayaran. Akses dibatasi untuk pemilik, atasan langsungnya, HR, dan Finance.

## 2.14 Modul Batch 12 — Pengumuman & Notifikasi In-App

### Papan pengumuman (`/announcements`) — semua peran
* Feed kartu berisi pengumuman yang **sesuai audiens** karyawan: semua karyawan,
  unit bisnis, departemen, jabatan, atau peran sistem. HR melihat seluruh
  pengumuman yang sudah terbit.
* Kategori: Informasi, Kebijakan, Acara & Kegiatan, Darurat, Payroll & Benefit —
  masing-masing dengan warna dan ikon sendiri.
* Pengumuman **penting (pin)** selalu tampil paling atas, penanda "Baru" muncul
  untuk yang belum dibaca, dan banner ringkas menampilkan jumlah belum dibaca.
* Filter: pencarian judul/isi, kategori, serta "hanya belum dibaca".
* Detail `/announcements/{id}`: isi terformat (paragraf, daftar `- `, penekanan
  `**teks**`), lampiran PDF/gambar, panel detail audiens, dan – untuk HR/Finance –
  statistik keterbacaan (berapa penerima sudah membuka).
* Tandai dibaca: otomatis saat detail dibuka, atau lewat tombol
  **Tandai Semua Dibaca**.

### Kelola pengumuman (`/announcements/manage`) — HR
* Statistik: terbit, draf, terjadwal, diarsipkan, total dibaca, rata-rata dibaca.
* Tabel pengumuman dengan filter status/kategori/pencarian, aksi cepat
  terbitkan, ubah, dan hapus (menghapus juga lampiran + riwayat baca).
* Formulir tulis/ubah: judul, kategori, **audiens tunggal**
  (`ALL` / `UNIT:{id}` / `DEPARTMENT:{id}` / `POSITION:{id}` / `ROLE:{kode}`),
  isi, lampiran (PDF/JPG/PNG maks 5 MB), penanda penting, dan pilihan publikasi:
  **terbit sekarang**, **jadwalkan** (tanggal + jam), atau **simpan draf**.
* Masa berlaku opsional (`expires_at`) — setelah tanggal tersebut pengumuman
  hilang dari feed karyawan.
* Saat terbit, notifikasi lonceng dikirim ke seluruh karyawan aktif pada audiens
  tersebut (sekali saja; pengumuman terjadwal baru mengirim notifikasi ketika
  waktunya tiba).

### Notifikasi in-app (`/notifications`)
* **Lonceng topbar** menampilkan jumlah belum dibaca + 5 notifikasi terbaru;
  badge diperbarui ringan tiap 60 detik lewat `GET /notifications/latest`
  (JSON, hanya untuk pengguna yang login, berhenti saat tab tidak aktif).
* Pusat notifikasi: filter belum dibaca/tipe/pencarian, tombol "Buka" untuk
  langsung menuju halaman terkait (sekaligus menandai dibaca), serta paginasi.
* Tipe notifikasi: informasi, berhasil, perhatian, penting, payroll, pengumuman,
  dan persetujuan — masing-masing dengan ikon/warna.
* Notifikasi dibuat modul lain (jadwal, lembur, cuti, payroll, klaim,
  pengumuman) dan bersifat best-effort: kegagalan menulis notifikasi tidak
  pernah memutus proses bisnis.

### Catatan teknis
* Tabel baru: `announcements` + `announcement_reads` (satu baris baca per
  karyawan per pengumuman) dan index `notifications (employee_id, read_at)`.
* Lampiran pengumuman disimpan di `storage/uploads/announcements` (di luar
  document root) dan diakses lewat `/media/announcements/{file}`.
* Upgrade skema otomatis (versi 12) saat basis data lama dibuka — idempoten dan
  tidak menyentuh data operasional.

---

## 2.15 Modul Batch 13 — Penilaian Kinerja (Performance Review)

### Ringkasan alur
**DRAFT** → **SELF** → **MANAGER** → **CALIBRATION** → **CLOSED**. Setiap periode
hanya menilai karyawan berstatus `ACTIVE` pada unit bisnis periode tersebut
(seluruh unit bila periode tidak dibatasi unit).

### Periode penilaian (`/performance/periods`) — HR
* Tabel periode: nama, jenis (Tahunan / Semesteran / Triwulan / Probation), rentang
  tanggal, tenggat self assessment & atasan, bobot self (%), unit bisnis, dan status
  dengan warna tahap.
* Formulir buat/ubah periode: nama, jenis, tanggal mulai–selesai, tenggat self &
  atasan, unit bisnis (opsional), bobot self 0–100, dan catatan.
* Aksi per periode: **Buka** (membuat baris penilaian untuk semua karyawan aktif
  pada cakupan unit, menyiapkan skor tiap indikator, dan mengirim notifikasi
  "Self assessment dibuka"), **Kalibrasi** (memindahkan tahap ke CALIBRATION),
  **Tutup** (CLOSED; periode dengan penilaian belum final tetap ditutup dengan
  peringatan jumlah yang tertinggal), dan **Hapus** (hanya untuk periode `DRAFT`).
* Periode yang sudah pernah dibuka tidak dapat dibuka ulang, dan penilaian pada
  periode `CLOSED` dikunci dari pengubahan.

### Indikator penilaian (`/performance/indicators`) — HR
* Master indikator dengan kode unik, nama, kategori (**Kompetensi**, **Hasil**,
  **Perilaku**), bobot, urutan tampil, dan status aktif.
* Bobot dipakai untuk merata-ratakan nilai per indikator menjadi nilai akhir
  karyawan; indikator nonaktif tidak lagi dipakai pada periode baru tetapi tetap
  tersimpan pada riwayat.
* Indikator yang sudah dipakai pada penilaian tidak dapat dihapus — nonaktifkan
  saja agar riwayat tetap utuh.

### Penilaian Tim (`/performance/team`) — atasan langsung
* Ringkasan bawahan: berapa yang belum mengisi self, menunggu dinilai, dan sudah
  final, plus tenggat periode berjalan.
* Daftar bawahan langsung dengan status tahap, nilai self, nilai atasan, dan
  nilai akhir bila ada.
* Formulir penilaian `/performance/team/{reviewId}`: skor 1–5 per indikator,
  panel panduan skala, ringkasan self assessment karyawan, serta catatan atasan.
  Hanya atasan langsung karyawan tersebut (atau HR) yang dapat menilai, dan hanya
  setelah self assessment terkirim.

### Penilaian Saya (`/me/performance`) — semua peran
* Kartu siklus berjalan: status tahap, tenggat self & atasan, bobot self, dan
  ajakan mengisi bila masih `PENDING`.
* Formulir self assessment dikelompokkan per kategori indikator (skor 1–5 +
  catatan), dengan panduan skala di sampingnya.
* Riwayat penilaian lintas periode, nilai akhir, rating (A–E), dan status
  konfirmasi karyawan.
* Detail `/me/performance/{id}`: rincian skor per indikator, nilai akhir,
  kalibrasi HR, dan tombol **Konfirmasi hasil**.
* **Nilai atasan & nilai akhir hanya tampil setelah HR menetapkan hasil final.**

### Daftar & detail penilaian (`/performance`, `/performance/{id}`) — HR
* Statistik: periode berjalan, penilaian menunggu, hasil final, dan rata-rata
  nilai akhir.
* Distribusi rating A–E dengan bar peringkat, filter periode/status/rating/unit
  dan pencarian nama atau NIK, serta ekspor **CSV/PDF** (`/performance/export`).
* Detail penilaian: rincian skor self vs atasan per indikator, nilai berbobot,
  panel kalibrasi, riwayat aktivitas (self terkirim → atasan menilai → final →
  dikonfirmasi), dan form penetapan hasil.
* Kalibrasi HR: nilai akhir dihitung dari
  `(self × bobot_self + atasan × (100 − bobot_self)) / 100`, atau **diisi manual**
  (1–5) untuk override; rating A≥4,5 · B≥3,5 · C≥2,5 · D≥1,5 · E<1,5. Menetapkan
  hasil mengubah status menjadi `FINAL` dan mengirim notifikasi ke karyawan.

### Catatan teknis
* Tabel baru: `review_periods`, `review_indicators`, `performance_reviews`, dan
  `performance_scores` (satu baris skor per indikator per penilaian), plus index
  `performance_reviews (period_id, status)` dan `(employee_id)`.
* Bobot indikator disalin ke `performance_scores.weight` saat periode dibuka,
  sehingga riwayat tidak berubah bila bobot master diperbarui.
* Badge navigasi: `performance` (HR, jumlah hasil menunggu kalibrasi) dan
  `performance_team` (atasan, jumlah bawahan yang menunggu dinilai).
* Notifikasi dikirim ke karyawan (self dibuka, hasil final) dan ke atasan
  (self assessment masuk).
* Upgrade skema otomatis (versi 13) saat basis data lama dibuka — idempoten dan
  tidak menyentuh data operasional.

---

## 3. Struktur proyek

```
app/
  bootstrap.php          # autoloader, config, session, DB, settings, auth
  config/config.php      # default + override env.php + environment variables
  Core/                  # Database, Router, Auth, View, Session, Settings,
                         # Validator, Schema, Audit, Nav, GoogleAuth, helpers
  Controllers/           # Auth, Dashboard, Approval, Org, Employee, Media, Shift, Schedule, Attendance, Overtime, Leave, Component, Settings, Payroll, Expense, Announcement, Notification, Performance, Report, Log, Placeholder
  Models/                # Dashboard, Org, Employee, Schedule, Attendance, Overtime, Leave, Salary, Payroll, Expense, Announcement, Notification, Performance, Report (query/aggregation layer)
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
vendor/                  # dompdf 3.1.6 + font/svg/html5 libs & phpqrcode (manual, tanpa Composer)
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
| Komponen gaji & pengaturan payroll/BPJS | ✅ | – | – |
| Payroll & slip gaji | ✅ proses & kirim | terima, bayar (PAID), cetak | slip sendiri |
| Klaim & reimbursement | ✅ semua + kategori | ✅ setujui & bayar | ajukan & pantau sendiri |
| Pengumuman | ✅ tulis, terbitkan, arsipkan | baca (sesuai audiens) | baca (sesuai audiens) |
| Notifikasi in-app | ✅ inbox + lonceng | ✅ inbox + lonceng | ✅ inbox + lonceng |
| Penilaian kinerja | ✅ periode, indikator, kalibrasi & final | – | self assessment, lihat hasil sendiri |
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
* Reset kata sandi: token acak 256-bit (disimpan sebagai hash), berlaku 60 menit,
  sekali pakai, dibatasi frekuensinya; ganti sandi mandiri mewajibkan sandi lama.
* Sesi: batas absolut 8 jam + idle 2 jam; "ingat perangkat" 30 hari dengan token
  yang dirotasi setiap pemakaian dan dicabut saat logout/ganti sandi.
* Login dibatasi 5 kegagalan/15 menit per identifier + IP (brute-force).
* Bukti klaim disimpan di `storage/uploads/claims` (di luar document root) dan
  hanya dapat diakses pengguna yang sudah login lewat rute `/media/{folder}/{file}`.
* Semua query memakai prepared statement (PDO), tanpa konkatenasi input.
* Cookie sesi `HttpOnly`, `SameSite=Lax`, `Secure` otomatis pada HTTPS.
* Audit trail: login, login gagal, approval, perubahan data.
* Upload disimpan di `storage/uploads` (di luar document root) dan disajikan
  melalui rute aplikasi `/media/{folder}/{file}` yang memvalidasi sesi, ekstensi,
  dan memastikan path tetap berada di dalam folder upload. Pada produksi rute ini
  boleh dipetakan langsung oleh web server untuk performa.
* Matikan `demo_mode` dan hapus data contoh sebelum go-live.
