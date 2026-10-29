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
| **14** | Pelatihan & sertifikasi: katalog program (kategori, durasi, biaya), jadwal sesi dengan kuota & pendaftaran daring, nominasi peserta oleh HR, presensi, nilai, sertifikat PDF, riwayat & sertifikat karyawan, export CSV | ✅ selesai |
| **15** | Rekrutmen & onboarding (ATS): lowongan multi-unit dengan kuota & status, pipeline pelamar APPLIED → SCREENING → INTERVIEW → OFFER, jadwal & pewawancara, rating, CV, konversi otomatis pelamar diterima menjadi karyawan (NIK, akun, notifikasi), export CSV | ✅ selesai |
| **16** | Offboarding & exit: pengunduran diri mandiri, PHK/akhir kontrak/pensiun oleh HR, persetujuan atasan → HR, checklist serah terima (aset, dokumen, akses, keuangan), exit interview + penyelesaian akhir, penonaktifan karyawan otomatis, export CSV | ✅ selesai |
| **17** | Register aset & BAST offboarding: daftar aset/inventaris yang dipegang karyawan (kode, qty, kondisi), status pengembalian per item, **BAST PDF** bertanda tangan, blokir penutupan offboarding bila aset belum kembali, serta halaman **Reset Sistem** (bersihkan data uji, akun admin dipertahankan) | ✅ selesai |

Seluruh tujuh belas batch sudah aktif — tidak ada lagi tautan mati atau menu
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

### 2.2.1 Berkas konfigurasi & data demo di repository
* `app/config/env.php` **ikut di-commit** sebagai profil pratinjau (SQLite +
  `demo_mode`, tanpa kredensial). Aman dibaca siapa pun; jangan menaruh kata
  sandi produksi di sana — pakai environment variables `HRIS_*`
  (`HRIS_DB_HOST`, `HRIS_DB_DATABASE`, `HRIS_DB_USERNAME`, `HRIS_DB_PASSWORD`,
  `HRIS_GOOGLE_CLIENT_SECRET`, dst.). Daftar kunci lengkap ada di
  `app/config/env.example.php`.
* `database/demo-data.sql` — **dump data demo** (6.335 baris pada 38 tabel,
  `INSERT` saja) untuk MySQL/MariaDB, dibuat dari database pratinjau:

```bash
mysql -u user -p asiaraya_hris < database/schema.sql
mysql -u user -p asiaraya_hris < database/demo-data.sql
```

  Berkas diawali `SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES'` agar isi JSON
  (mis. snapshot BAST) tersimpan apa adanya. Impor ke database kosong.
* `database/demo-preview.sqlite` — salinan database SQLite pratinjau untuk
  menjalankan aplikasi tanpa instalasi:

```bash
cp database/demo-preview.sqlite storage/database.sqlite
```

* Isi data demo sepenuhnya fiktif (118 karyawan contoh, 5 pengajuan offboarding,
  presensi 26 hari kerja, payroll, pelatihan, rekrutmen, BAST). Untuk memulai
  dari nol, login sebagai HR lalu buka **Reset Sistem** (`/system/reset`).

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

## 2.16 Modul Batch 14 — Pelatihan & Sertifikasi

### Katalog pelatihan (`/trainings/catalog`) — HR
* Master program: kode unik, nama, kategori (**Teknis**, **Soft Skill**,
  **Kepemimpinan**, **Kepatuhan & Regulasi**, **Onboarding**), jenis
  (internal/eksternal/online), penyedia/narasumber, deskripsi, durasi jam, biaya
  acuan per peserta, penanda **wajib**, dan status aktif.
* Program yang sudah dipakai pada sesi tidak dapat dihapus — nonaktifkan saja
  agar riwayat peserta & sertifikat tetap utuh.

### Jadwal pelatihan (`/trainings`) — HR
* Statistik: program aktif, sesi berjalan, peserta terdaftar, total jam, biaya
  kumulatif, tingkat kehadiran, dan jumlah pendaftaran menunggu persetujuan.
* Tabel sesi dengan filter status/program/mode/pencarian: program + kategori,
  rentang tanggal & batas pendaftaran, mode/lokasi, keterisian kuota (bar), status.
* Formulir sesi: program, mode (tatap muka/daring/hybrid), lokasi atau tautan,
  tanggal mulai–selesai, batas pendaftaran, kuota (0 = tanpa batas), catatan.
* Alur status: **DRAF** → **Pendaftaran Dibuka** → **Berlangsung** → **Selesai**,
  dengan **Dibatalkan** sebagai jalur pembatalan. Sesi draf/batal dapat dihapus.

### Detail sesi (`/trainings/{id}`) — HR
* Panel informasi sesi, statistik peserta (terdaftar, hadir, absen, rata-rata
  nilai, biaya, jam), dan keterisian kuota.
* **Nominasi peserta**: pilih beberapa karyawan aktif sekaligus; peserta nominasi
  HR langsung berstatus *Terdaftar* dan menerima notifikasi.
* **Persetujuan pendaftaran mandiri**: setujui/tolak per peserta atau
  **Setujui Semua**; pendaftar yang disetujui otomatis diberi notifikasi.
* **Presensi**: tandai hadir/absen per peserta atau **Tandai Semua Hadir**.
* **Hasil & sertifikat**: nilai 0–100, jam hadir, nomor sertifikat, catatan, serta
  unggah berkas sertifikat (PDF/JPG/PNG maks 4 MB) yang menggantikan berkas lama.
* Ekspor peserta ke CSV (`/trainings/{id}/export`).

### Pelatihan Saya (`/me/trainings`) — semua peran
* Kartu ringkasan: pelatihan selesai, jam pelatihan, jumlah sertifikat, dan
  rata-rata nilai.
* **Pendaftaran dibuka**: sesi yang masih menerima pendaftaran beserta sisa kursi;
  karyawan mendaftar sendiri dan menunggu persetujuan HR.
* **Jadwal mendatang**: status pendaftaran (menunggu persetujuan/terdaftar) dan
  tombol pembatalan selama sesi belum berlangsung.
* **Riwayat pelatihan**: kehadiran, jam, nilai, nomor sertifikat, dan tautan
  unduh berkas sertifikat.

### Catatan teknis
* Tabel baru: `trainings`, `training_sessions`, dan `training_participants`
  (satu baris per karyawan per sesi), plus index unik
  `training_participants (session_id, employee_id)`.
* Berkas sertifikat disimpan di `storage/uploads/trainings` (di luar document
  root) dan disajikan lewat `/media/trainings/{file}` untuk pengguna yang login.
* Badge navigasi `training` menampilkan jumlah pendaftaran mandiri yang menunggu
  persetujuan HR.
* Notifikasi: pendaftaran dibuka (seluruh karyawan aktif), nominasi/disetujui
  (karyawan terkait), dan pembatalan sesi (peserta terdampak).
* Ekspor CSV memakai argumen *escape* eksplisit agar bebas peringatan PHP 8.4.
* Upgrade skema otomatis (versi 14) saat basis data lama dibuka — idempoten dan
  tidak menyentuh data operasional.

---

## 2.17 Modul Batch 15 — Rekrutmen & Onboarding (ATS)

### Lowongan & pipeline (`/recruitment`) — HR
* Statistik rekrutmen: lowongan dibuka, pelamar aktif, pelamar baru (belum
  disaring), wawancara mendatang, dan pelamar diterima.
* Daftar lowongan dengan filter status/pencarian: kode lowongan, posisi, unit &
  departemen, jenis kepegawaian, mode kerja (onsite/hybrid/remote), kuota,
  keterisian, hiring manager, periode buka–tutup, dan status.
* Formulir lowongan: kode unik, judul, unit/departemen/posisi, jenis
  kepegawaian (**PKWTT**, **PKWTT (Probation)**, **PKWT**, **Magang**,
  **Outsourcing**), mode kerja, lokasi, kuota, rentang gaji, tanggal buka/tutup,
  hiring manager, deskripsi & kualifikasi.
* Alur status: **DRAF** → **Dibuka** → **Ditahan** → **Ditutup**. Perpindahan
  status tercatat di audit trail dan dinotifikasi ke hiring manager.

### Detail lowongan (`/recruitment/jobs/{id}`) — HR
* **Papan pipeline** empat kolom (Applied, Screening, Interview, Offer) dengan
  kartu pelamar: nama, sumber, pendidikan, pengalaman, rating bintang, dan jadwal
  wawancara. Klik kartu untuk membuka detail pelamar.
* **Corong seleksi** (funnel) berisi jumlah pelamar per tahap beserta konversi
  persentase terhadap total pelamar lowongan.
* Daftar pelamar terbaru, ringkasan kuota vs pelamar diterima, serta aksi cepat
  buka/tahan/tutup lowongan.

### Data pelamar (`/recruitment/candidates`) — HR
* Kartu ringkasan status (baru, screening, wawancara, ditawarkan, diterima) dan
  tabel pelamar dengan filter lowongan/tahap/sumber/pencarian serta paginasi.
* **Tambah pelamar**: pilih lowongan, identitas (nama, email, telepon, tanggal
  lahir, jenis kelamin), pendidikan, pengalaman, perusahaan/posisi saat ini,
  ekspektasi gaji, sumber (**Website Karier**, **Referensi Karyawan**,
  **LinkedIn**, **Portal Lowongan**, **Walk-in**, **Lainnya**), rating awal,
  unggah CV, dan catatan pelamar.
* Email pelamar unik antar pelamar untuk mencegah entri ganda.
* Ekspor CSV seluruh pelamar terfilter (`/recruitment/export`).

### Detail pelamar (`/recruitment/candidates/{id}`) — HR
* Profil lengkap + tautan CV, rating bintang, riwayat tahapan (timeline audit:
  tahap asal → tujuan, catatan, aktor, waktu), serta formulir edit data pelamar
  (termasuk ganti CV dan rating 1–5).
* **Pindahkan tahap**: Applied → Screening → Interview → Offer, dengan jadwal
  wawancara (`datetime-local`), mode wawancara (tatap muka/daring/telepon),
  pewawancara (dipilih dari karyawan aktif), dan catatan tahap. Perpindahan ke
  tahap wawancara mewajibkan jadwal.
* **Terima sebagai karyawan**: satu klik mengubah pelamar menjadi karyawan —
  NIK dibuat otomatis lewat `EmployeeCode::next()` (format per unit/departemen),
  username dari email, peran **KARYAWAN**, kata sandi awal `Password123!`, unit,
  departemen, posisi, atasan langsung, status kepegawaian, gaji pokok, tanggal
  bergabung, dan akhir kontrak (wajib untuk PKWT/Magang/Outsourcing).
* **Tolak** dengan alasan wajib, atau **mengundurkan diri**; kedua status akhir
  mengunci pipeline namun menyimpan riwayat untuk audit.

### Catatan teknis
* Tabel baru: `job_vacancies`, `candidates`, dan `candidate_stages` (satu baris
  per perpindahan tahap), dengan index unik `job_vacancies.code` dan index pada
  `candidates(job_id)`, `candidates(status)`, `candidates(email)`, serta
  `candidate_stages(candidate_id)`.
* CV disimpan di `storage/uploads/recruitment` (di luar document root) dan
  disajikan lewat `/media/recruitment/{file}` untuk pengguna yang login.
* Badge navigasi `recruitment` menampilkan jumlah pelamar baru yang belum
  disaring pada lowongan yang masih dibuka.
* Notifikasi: lowongan dibuka/ditahan/ditutup (hiring manager), pelamar masuk
  tahap wawancara/penawaran, dan sambutan akun untuk karyawan baru hasil konversi.
* Ekspor CSV memakai argumen *escape* eksplisit agar bebas peringatan PHP 8.4.
* Upgrade skema otomatis (versi 15) saat basis data lama dibuka — idempoten dan
  tidak menyentuh data operasional.

---

## 2.18 Modul Batch 16 — Offboarding & Exit Management

### Antrean HR (`/offboarding`) — HR
* Statistik: pengajuan menunggu atasan, menunggu HR, serah terima berjalan,
  selesai bulan ini, jumlah karyawan keluar dalam 30 hari, dan rata-rata masa
  pemberitahuan (dibandingkan standar `resign_notice_days`).
* Tabel pengajuan dengan filter status/jenis/unit/departemen/pencarian: nomor
  pengajuan, karyawan, jenis, hari terakhir kerja + hitungan hari tersisa,
  notice, progres checklist, dan status. Baris dengan sisa ≤ 7 hari disorot.
* Panel **Kontrak Berakhir ≤ 90 Hari** menampilkan karyawan aktif yang kontraknya
  segera habis beserta tombol **Proses** (form terisi otomatis) atau tautan ke
  pengajuan yang sudah berjalan.
* **Mulai offboarding**: karyawan, jenis (**Mengundurkan Diri**, **PHK**,
  **Akhir Kontrak**, **Pensiun**, **Mangkir**), kategori alasan, tanggal
  pengajuan, hari terakhir kerja, penerima serah terima, dan catatan. Pengajuan
  buatan HR langsung masuk tahap *Menunggu HR* (tanpa approval atasan).
* Ekspor CSV seluruh pengajuan terfilter (`/offboarding/export`).

### Portal karyawan (`/me/offboarding`) — semua peran
* Kartu ringkasan status pengajuan aktif, hari terakhir kerja, progres serah
  terima, dan standar masa pemberitahuan.
* **Ajukan pengunduran diri**: hari terakhir kerja (minimal hari ini), alasan
  utama, penerima serah terima, dan catatan; pengajuan otomatis diteruskan ke
  atasan langsung (atau HR bila tidak ada atasan).
* **Batalkan pengajuan** selama proses belum ditutup.
* **Umpan balik keluar** (exit feedback + rekomendasi) yang dapat diisi kapan
  saja sebelum proses selesai.
* **Persetujuan tim**: atasan langsung melihat dan meninjau pengajuan anggota
  timnya langsung dari portal ini.
* Riwayat seluruh pengajuan atas nama karyawan.

### Detail pengajuan (`/offboarding/{id}`)
* Stepper status: Pengajuan → Persetujuan Atasan → Serah Terima → Selesai
  (plus penanda merah untuk status Ditolak/Dibatalkan).
* Ringkasan: profil karyawan, unit/departemen/posisi, masa kerja, jenis, alasan,
  notice, penerima serah terima, status kepegawaian & akhir kontrak, waktu
  persetujuan atasan dan HR, serta kronologi.
* **Checklist serah terima**: progress bar + item per kategori (**Serah Terima
  Pekerjaan**, **Aset & Perangkat**, **Dokumen**, **Keuangan**, **Akses
  Sistem**, **Lainnya**) dengan PIC (HR/atasan/Finance/IT) dan status
  *Belum Selesai / Selesai / Dikecualikan*. HR dapat menambah item, menandai
  semua selesai, atau menghapus item. **Finance** dapat membuka halaman detail
  dan hanya menyelesaikan item berkategori *Keuangan* (item lain ditolak 403).
* **Exit interview & penyelesaian akhir**: jadwal wawancara, pewawancara,
  rekomendasi (ya/mungkin/tidak), catatan wawancara, nominal + rincian
  penyelesaian (gaji prorata, sisa cuti, potongan).
* **Aksi**: setujui sebagai atasan, setujui HR (membuat checklist otomatis),
  tutup offboarding (wajib checklist 100%), tolak dengan alasan, batalkan
  (karyawan/HR), dan hapus pengajuan draf/ditolak/dibatalkan.
* **Timeline** seluruh perpindahan status beserta aktor dan catatan.

### Catatan teknis
* Tabel baru: `offboarding_requests`, `offboarding_clearance`, dan
  `offboarding_stages`, dengan index unik `offboarding_requests.request_number`.
* Menutup offboarding (`complete`) berjalan dalam satu transaksi: status
  pengajuan menjadi **COMPLETED** dan karyawan di-set `flag = 'RESIGN'` dengan
  `resign_date` = hari terakhir kerja.
* Satu karyawan hanya boleh memiliki satu pengajuan aktif (DRAFT/SUBMITTED/
  MANAGER_APPROVED/IN_PROGRESS) — pengajuan kedua ditolak dengan pesan jelas.
* Badge navigasi `offboarding` menampilkan jumlah pengajuan yang menunggu
  keputusan (atasan/HR).
* Masa pemberitahuan resign dapat diatur HR di **Pengaturan → Laporan & Audit**
  (`resign_notice_days`, 7–180 hari); nilainya dipakai form karyawan, form
  offboarding HR, dan kartu statistik.
* Halaman detail karyawan menampilkan kartu **Offboarding & Exit** berisi
  riwayat pengajuan beserta tautan langsung ke prosesnya (tombol *Mulai
  Offboarding* mengisi form HR otomatis untuk karyawan tersebut).
* Notifikasi: karyawan (persetujuan/penolakan/penutupan), atasan (pengajuan baru,
  serah terima dimulai), HR (pengajuan baru & selesai), dan Finance (penyelesaian
  keuangan).
* Ekspor CSV memakai argumen *escape* eksplisit agar bebas peringatan PHP 8.4.
* Upgrade skema otomatis (versi 16) saat basis data lama dibuka — idempoten dan
  tidak menyentuh data operasional.

---

## 2.19 Modul Batch 17 — Register Aset, BAST & Reset Sistem

### Register aset & inventaris (`/offboarding/{id}#aset`) — HR
* Setiap pengajuan offboarding memiliki **register aset** terpisah dari checklist
  proses: nama aset, **kode aset/serial**, kategori (**Laptop/Komputer**,
  **Telepon/Gadget**, **Kartu Identitas**, **Kartu Akses/Kunci**, **Peralatan
  Kerja**, **Seragam/APD**, **Kendaraan**, **Lainnya**), jumlah (qty), kondisi
  (**Baik**, **Cukup**, **Rusak**, **Hilang**), dan catatan.
* Empat aset default dibuat otomatis saat HR menyetujui pengajuan (laptop &
  adaptor, ID card, kartu akses & kunci, seragam/APD) — dapat diedit/dihapus.
* Status per item: **Belum Kembali** → **Dikembalikan** / **Dikecualikan** /
  **Tidak Dipegang** / **Hilang/Diganti**, diubah langsung dari tabel
  (dropdown inline) dan tercatat siapa penerima serta kapan.
* Tombol **Semua Dikembalikan** menuntaskan sisa item sekaligus.
* Progress serah terima kini **gabungan**: 4 item checklist + 5 aset = 9 item;
  penutupan offboarding **ditolak** selama masih ada aset berstatus
  *Belum Kembali*.

### BAST — Berita Acara Serah Terima (`/offboarding/{id}#bast`)
* HR menerbitkan BAST setelah tahap serah terima dimulai. Penerbitan **ditolak**
  bila masih ada aset belum kembali, kecuali dicentang *"Terbitkan BAST dengan
  catatan aset belum kembali"* (dokumen memuat peringatan).
* Isi dokumen: kop perusahaan, nomor BAST (`BAST/{tahun}/{urut}`), data karyawan
  penyerah (NIK, jabatan, departemen, unit, jenis keluar, hari terakhir kerja),
  tabel aset lengkap (kode, nama, kategori, qty, kondisi, status, catatan),
  total unit, paragraf pernyataan, keterangan, dan tiga kolom tanda tangan
  (**Yang Menyerahkan**, **Yang Menerima**, **Saksi**) + footer penerbit.
* Dokumen dirender **dompdf** → `/offboarding/{id}/bast` (inline) dan
  `?download=1` (unduh). Data aset disimpan sebagai **snapshot JSON** saat
  terbit, sehingga dokumen tidak berubah bila register diperbarui kemudian.
* Bisa diterbitkan ulang (nomor urut baru) dengan penerima/saksi berbeda.

### Reset Sistem (`/system/reset`) — HR
* Halaman khusus untuk membersihkan **seluruh jejak ujicoba/demo** sebelum
  go-live, dengan ringkasan jumlah baris per kelompok data.
* Opsi penghapusan (masing-masing dapat dicentang):
  1. **Data operasional** — presensi, lembur, cuti & saldo, jadwal, payroll &
     slip, klaim, penilaian kinerja, pelatihan, rekrutmen, offboarding (BAST,
     register aset, checklist, riwayat), pengumuman, notifikasi, log aktivitas,
     token remember/password reset.
  2. **Master data organisasi** — unit, departemen, posisi, shift, komponen
     gaji, jenis cuti, kategori klaim, katalog pelatihan, indikator penilaian,
     hari libur, sequence nomor ID.
  3. **Seluruh karyawan kecuali administrator** — akun & profil lain dihapus.
  4. **Berkas unggahan** — foto, bukti klaim, sertifikat, CV, lampiran
     (foto administrator dipertahankan).
* **Selalu dipertahankan:** akun administrator yang menjalankan reset (login,
  kata sandi, peran HR), tabel `settings` (identitas perusahaan, kebijakan
  presensi/payroll/BPJS, versi skema), dan data badan hukum perusahaan.
* Pengaman: wajib mengetik **RESET** + verifikasi **kata sandi** akun sendiri,
  konfirmasi dialog, CSRF, dan catatan audit `system.reset`. Halaman menampilkan
  waktu & pelaksana reset terakhir.
* Setelah reset, sesi tetap aktif dan HR langsung dapat membuat unit organisasi
  serta karyawan baru.

### Catatan teknis
* Tabel baru (versi skema 17): `offboarding_assets` dan `offboarding_bast`
  (index unik `bast_number`).
* `OffboardingModel::progress()` menghitung checklist + aset; `complete()`
  memblokir penutupan bila aset belum tuntas; penghapusan pengajuan ikut
  menghapus aset & BAST.
* Reset berjalan tanpa `FOREIGN KEY` (skema tidak memakai constraint fisik),
  tetapi relasi akun administrator (`business_unit_id`, `department_id`,
  `position_id`, `direct_manager_id`) dilepas sebelum master/karyawan dihapus.
* `resign_notice_days` dapat diubah di **Pengaturan → Laporan & Audit**.

---

## 3. Struktur proyek

```
app/
  bootstrap.php          # autoloader, config, session, DB, settings, auth
  config/config.php      # default + override env.php + environment variables
  Core/                  # Database, Router, Auth, View, Session, Settings,
                         # Validator, Schema, Audit, Nav, GoogleAuth, helpers
  Controllers/           # Auth, Dashboard, Approval, Org, Employee, Media, Shift, Schedule, Attendance, Overtime, Leave, Component, Settings, Payroll, Expense, Announcement, Notification, Performance, Training, Recruitment, Offboarding, System, Report, Log, Placeholder
  Models/                # Dashboard, Org, Employee, Schedule, Attendance, Overtime, Leave, Salary, Payroll, Expense, Announcement, Notification, Performance, Training, Recruitment, Offboarding, System, Report (query/aggregation layer)
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
| Pelatihan & sertifikasi | ✅ katalog, sesi, nominasi, presensi, sertifikat | – | daftar pelatihan, riwayat & sertifikat sendiri |
| Rekrutmen & onboarding | ✅ lowongan, pipeline, pelamar, konversi karyawan | – | – |
| Offboarding & exit | ✅ pengajuan, persetujuan, checklist serah terima, exit interview | lihat proses + selesaikan item keuangan | ajukan pengunduran diri, umpan balik, setujui tim |
| Register aset & BAST | ✅ register aset, status pengembalian, terbitkan BAST | baca BAST | unduh BAST sendiri |
| Reset sistem | ✅ bersihkan data uji (akun admin dipertahankan) | – | – |
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
* Berkas sensitif — bukti klaim (`claims`), sertifikat pelatihan (`trainings`), CV pelamar
  (`recruitment`), dan lampiran pengumuman (`announcements`) — disimpan di `storage/uploads` (di luar
  document root) dan hanya dapat diakses pengguna yang sudah login lewat rute
  `/media/{folder}/{file}`.
* Semua query memakai prepared statement (PDO), tanpa konkatenasi input.
* Cookie sesi `HttpOnly`, `SameSite=Lax`, `Secure` otomatis pada HTTPS.
* Audit trail: login, login gagal, approval, perubahan data.
* Upload disimpan di `storage/uploads` (di luar document root) dan disajikan
  melalui rute aplikasi `/media/{folder}/{file}` yang memvalidasi sesi, ekstensi,
  dan memastikan path tetap berada di dalam folder upload. Pada produksi rute ini
  boleh dipetakan langsung oleh web server untuk performa.
* Matikan `demo_mode` dan hapus data contoh sebelum go-live.
