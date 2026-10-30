# PROMPT — Aplikasi Android “HRIS Asia Raya” (Kotlin)

> **Cara pakai:** tempelkan seluruh dokumen ini sebagai pesan pertama di sesi baru
> (agen yang mengerjakan aplikasi Android). Bila sesi tersebut punya akses ke
> repository `cahyadiami26/asiaraya`, sertakan juga berkas **`docs/openapi.yaml`**
> (spesifikasi API Batch 21) — dokumen ini adalah pelengkapnya: keputusan stack,
> design system, spesifikasi layar, dan kriteria penerimaan.

---

## 1. Misi

Bangun **aplikasi Android native berbahasa Kotlin** untuk karyawan PT Asia Raya
Group yang memakai **REST API `/api/v1`** dari sistem HRIS yang sudah ada
(hasil Batch 21). Aplikasi harus terasa seperti versi mobile dari portal
karyawan di web: **gaya visual identik** (navy + teal, kartu putih, sudut
membulat, tipografi Inter), **native** (bukan WebView), dan **alamat server
dapat diatur pengguna**.

Fitur wajib (sama dengan cakupan Batch 21):

1. Login & sesi perangkat (token + refresh, logout)
2. Pengumuman / informasi perusahaan
3. Jadwal & shift kerja
4. Pengajuan perubahan jadwal (buat, lihat, batalkan)
5. Presensi online (GPS geofence + selfie, **tahan jaringan buruk**)
6. Data presensi bulanan (riwayat + rekap)
7. Pengajuan lembur (buat, lihat, batalkan)
8. Pengajuan cuti (saldo, buat, lihat, batalkan)
9. Slip gaji (daftar, rincian komponen, unduh PDF)
10. Klaim / reimbursement (kategori, buat + bukti, lihat, batalkan)
11. Bukti potong pajak 1721-A1 (daftar, rincian, unduh PDF)
12. Notifikasi in-app
13. **Kotak masuk persetujuan** untuk atasan langsung, HR, dan Finance
    (setujui/tolak cuti, lembur, perubahan jadwal, klaim)

---

## 2. Sumber kebenaran

| Hal | Sumber |
|---|---|
| Kontrak API (path, field, enum, error) | `docs/openapi.yaml` di repo HRIS (OpenAPI 3.1, 41 path / 45 operasi) |
| Perilaku bisnis (geofence, saldo cuti, approval 2 tahap) | kode PHP `app/Core/ApprovalEngine.php`, `app/Models/*` — **jangan diduplikasi di klien**, cukup ikuti aturan server |
| Design system | `public/assets/css/app.css` (Bab 5 dokumen ini sudah merangkumnya) |
| Referensi UI mobile | halaman `me/attendance`, `me/schedule`, `me/leave`, `me/payslip`, `login` di web |

**Aturan:** jangan mengarang endpoint/field. Bila ada kebutuhan yang tidak
tersedia di API, tulis usulan perubahan backend (endpoint + payload) sebagai
catatan terpisah — **jangan** mengubah repo backend dari sesi Android.

---

## 3. Keputusan teknis wajib

| Aspek | Ketentuan |
|---|---|
| Bahasa | Kotlin (100%), tanpa Java baru |
| UI | **Jetpack Compose + Material 3**, `dynamicColor = false` (warna brand dipaksa) |
| Arsitektur | MVVM + Repository, `StateFlow`, `UiState` (Loading/Content/Empty/Error), satu arah (UDF) |
| DI | Hilt (KSP, bukan kapt) |
| HTTP | Retrofit 2 + OkHttp 4 + **kotlinx.serialization** |
| Penyimpanan | DataStore (Preferences) untuk pengaturan & sesi; **Room** untuk antrean presensi offline |
| Background | WorkManager (sinkronisasi antrean presensi) |
| Kamera | CameraX (`ImageCapture`, kamera depan) |
| Lokasi | `FusedLocationProviderClient` (Google Play Services Location) |
| Gambar | Coil |
| PDF | Unduh ke cache → `PdfRenderer` in-app + opsi “buka di aplikasi lain”/bagikan |
| SDK | `minSdk 26`, `compileSdk 35`, `targetSdk 35` |
| Build | Gradle Kotlin DSL, AGP 8.7+, Kotlin 2.0+, JDK 17 |
| Identitas | `applicationId = "id.co.asiaraya.hris"`, label **HRIS Asia Raya**, `versionName = "1.0.0"` |
| Bahasa aplikasi | Bahasa Indonesia saja (`values/strings.xml`), format angka/tanggal Indonesia |
| Repo baru | `asiaraya-android` (pisah dari repo HRIS) + README cara build |

**Jangan** membuat aplikasi berbasis WebView/Cordova/Flutter/React Native.
**Jangan** memakai warna default Material You, font Roboto, atau aksen ungu.

---

## 4. Aturan main

1. **Inkremenal per fase** (Bab 13). Setiap fase harus: compile, `lint` bersih,
   unit test lulus, diuji manual terhadap server demo, lalu commit.
2. **Design fidelity:** bandingkan dengan web pada lebar ~390 dp. Palet, radius,
   ketebalan garis, dan warna badge harus sama (lihat Bab 5).
3. **Amplop API** selalu `{success, data, meta}` / `{success, code, message,
   errors, meta}` — buat satu `ApiEnvelope<T>` dan parser error terpusat.
4. Semua teks UI berbahasa Indonesia, sopan, ringkas (contoh nada: “Pengajuan
   cuti dikirim ke atasan langsung.”).
5. Tiap layar wajib punya 4 keadaan: **loading** (skeleton/shimmer), **empty**
   (ikon + kalimat + CTA bila relevan), **error** (pesan server + tombol “Coba
   lagi”), **offline** (banner “Anda sedang offline. Data terakhir ditampilkan.”).
6. Tidak ada rahasia di repo (token, password, keystore). Keystore signing hanya
   lewat `keystore.properties` yang di-`.gitignore`.
7. Aksesibilitas: target sentuh ≥ 48 dp, `contentDescription` untuk ikon aksi,
   dukungan font scaling, kontras teks ≥ 4.5:1.
8. Jangan mencatat token/Authorization di log; logging HTTP hanya di `debug`.

---

## 5. Design system (port dari web)

### 5.1 Palet warna (persis dari `app.css`)

| Token | Hex | Pemakaian |
|---|---|---|
| `navy950` | `#08192A` | gradient sidebar bawah, splash |
| `navy900` | `#0B2135` | header gelap, judul besar |
| `navy850` | `#0D2739` | gradient tengah |
| `navy800` | `#0F2C45` | **tombol primer**, permukaan gelap |
| `navy700` | `#163B57` | tombol primer pressed |
| `navy600` | `#1E4D6B` | garis/aksen navy |
| `teal700` | `#0F766E` | teks tautan, ikon aktif |
| `teal600` | `#0D9488` | **tombol teal**, avatar |
| `teal500` | `#14B8A6` | ring fokus input |
| `teal400` | `#2DD4BF` | aksen terang |
| `teal100` | `#CBF5EC` | border chip teal |
| `teal50` | `#E7FBF7` | latar badge/chip teal, tombol soft |
| `ink` | `#0F172A` | teks utama |
| `ink2` | `#263449` | teks sekunder kuat, label form |
| `muted` | `#63748C` | teks sekunder |
| `muted2` | `#8B9BB1` | placeholder, ikon pasif |
| `line` | `#E5EAF2` | border kartu/pemisah |
| `lineStrong` | `#D5DDE9` | border input |
| `bg` | `#F4F6FA` | latar halaman |
| `bg2` | `#EEF2F8` | latar blok sekunder |
| `card` | `#FFFFFF` | permukaan kartu |
| `amber600` / `amber50` | `#B45309` / `#FEF6E7` | badge “Menunggu” |
| `rose600` / `rose50` | `#BE123C` / `#FEECEF` | badge “Ditolak”, error |
| `indigo600` / `indigo50` | `#4338CA` / `#EEF0FF` | badge “Terjadwal/Cuti” |
| `slate50` + teks `#475569` | `#F1F5F9` / `#475569` | badge “Dibatalkan/Libur” |

Latar halaman memakai `bg`; kartu putih dengan **border 1 dp `line`** + shadow
halus (elevasi 1–2 dp). Header/jumbotron boleh memakai gradient navy:
`linear-gradient(180deg, #0B2135 0%, #0D2739 45%, #08192A 100%)`.

### 5.2 Tipografi

- Font: **Inter** (bundel `Regular 400`, `Medium 500`, `SemiBold 600`, `Bold 700`
  di `res/font/`; tanpa font provider).
- Skala (padanan web → Android):

| Peran | Web | Android |
|---|---|---|
| Judul halaman | 23 px / 700 / tracking −0.03em | `22.sp` SemiBold, letterSpacing `-0.5.sp` |
| Judul kartu | 14.5 px / 700 | `15.sp` SemiBold |
| Body | 14 px / 400 | `14.sp` Regular, lineHeight `21.sp` |
| Body kecil / caption | 12 px | `12.sp` Regular |
| Label form | 11.6 px / 650 | `12.sp` Medium, warna `ink2` |
| Badge | 10.6 px / 700 | `11.sp` SemiBold |
| Label KPI (uppercase) | 10.5 px / 700 / tracking .1em | `10.sp` Bold, letterSpacing `1.sp`, `muted` |
| Angka uang besar | — | `20.sp` SemiBold, tabular nums |

### 5.3 Bentuk, jarak, elevasi

- Radius: kartu **14 dp**, tombol & input **9 dp**, badge **6 dp**, sheet/dialog **20 dp**,
  foto/avatar bulat penuh.
- Jarak halaman: padding horizontal **16 dp**, antar kartu **12 dp**, dalam kartu
  **16 dp**. Skala spacing: 4/8/12/16/20/24.
- Tinggi komponen: tombol **48 dp** (target sentuh), input **52 dp**, bottom bar
  **64 dp** + `navigationBarsPadding()`, top bar **56 dp**.
- Shadow kartu: `Modifier.shadow(1.dp, RoundedCornerShape(14.dp))` + border `line`.

### 5.4 Komponen wajib (buat sekali, pakai ulang)

`HrisTheme` · `CardSurface` · `SectionCard(title, subtitle, action)` ·
`KpiTile(label, value, icon)` · `StatusBadge(status)` · `PrimaryButton` (navy),
`TealButton`, `SoftButton` (teal-50), `OutlineButton`, `DangerButton`,
`HrisTextField` (label + hint + error 11 sp rose), `HrisDropdown`,
`DateField` (DatePicker Material 3, format `dd MMM yyyy`), `TimeField`,
`EmployeeAvatar` (inisial, latar teal-600), `EmptyState(icon, title, body, cta)`,
`ErrorState(message, onRetry)`, `LoadingSkeleton`, `OfflineBanner`,
`PunchStatusCard`, `ListRowLeadingIcon`, `BottomSheetMenu`, `ConfirmationDialog`
(gaya: judul 16 sp semibold, isi 13 sp muted, tombol kanan teal/merah).

### 5.5 Status → warna & label (sama dengan `status_badge()` di web)

| Status | Tone | Label |
|---|---|---|
| `PENDING` | amber | Menunggu |
| `MANAGER_APPROVED` | teal | Disetujui Atasan |
| `APPROVED` | teal | Disetujui |
| `REJECTED` | rose | Ditolak |
| `CANCELLED` | slate | Dibatalkan |
| `SCHEDULED` (klaim) | indigo | Terjadwal |
| `PAID` | teal | Lunas / Dibayarkan |
| `PRESENT` | teal | Hadir |
| `LATE` | amber | Terlambat |
| `ABSENT` | rose | Tidak Hadir |
| `LEAVE` | indigo | Cuti |
| `WFH` | indigo | WFH |
| `OFF` | slate | Libur |
| `FINANCE_APPROVED` | teal | Disetujui Finance |

Label detail klaim: `PENDING` → “Menunggu atasan”, `MANAGER_APPROVED` →
“Menunggu HR/Finance”, `APPROVED` → “Disetujui”, `SCHEDULED` → “Dijadwalkan
payroll”, `PAID` → “Dibayarkan”. Untuk cuti/lembur/jadwal: `PENDING` →
“Menunggu atasan”, `MANAGER_APPROVED` → “Menunggu HR”. (Server juga mengirim
`status_label`; pakai itu bila ada, tabel ini untuk fallback.)

### 5.6 Format angka & tanggal

- Uang: `Rp 15.830.000` (pemisah ribuan titik, tanpa desimal; desimal hanya bila
  ada sen). Ringkas untuk KPI: `Rp 15,8 jt`.
- Tanggal pendek: `30 Okt 2026`; tanggal panjang: `Jumat, 30 Oktober 2026`;
  bulan: `Oktober 2026`; jam: `08:45` (zona `Asia/Jakarta`, WIB).
- Angka desimal: koma (`3,5 hari`).

---

## 6. Struktur navigasi

```
Splash → (token valid?) → Login / Shell
Shell (Scaffold)
 ├─ Bottom bar: Beranda · Presensi · Jadwal · Cuti · Lainnya
 ├─ Top bar: judul + lonceng notifikasi (dot rose bila unread) + avatar → menu
 └─ Sheet "Lainnya": Slip Gaji, Lembur, Klaim, Pajak (1721-A1),
    Pengumuman, Notifikasi, Persetujuan (bila can_approve), Profil & Pengaturan
```

- Beranda juga menampilkan pintasan: Slip terakhir, Pengajuan terakhir,
  Kotak persetujuan (jika atasan/HR/FIN).
- Badge angka pada ikon “Lainnya”/menu bila ada pengajuan menunggu.
- Deep link (opsional, fase 6): `hris://payslip/{id}`, `hris://leave/{id}`,
  `hris://approvals`.

---

## 7. Spesifikasi layar

Untuk setiap layar: endpoint, isi, keadaan, dan **kriteria penerimaan**.

### S1 — Splash / Boot
- Baca sesi dari DataStore; bila ada token → muat `GET /app/config` (cache
  boleh dipakai bila offline) → Beranda. Tanpa token → Login.
- Latar gradient navy, logo mark (kotak rounded 20 dp, gradient navy + border
  teal 35%), teks “HRIS Asia Raya” putih, sub “Enterprise HRIS Gateway” teal-300.
- **DoD:** cold start < 2 s; token kedaluwarsa → langsung ke Login tanpa error.

### S2 — Login
- Field: **Alamat Server** (bisa dibuka/dilipat; menyimpan `https://hris.asiaraya.co.id`
  sebagai bawaan), **Email / NIK / Username**, **Kata Sandi** (ikon mata),
  tombol primary navy **Masuk**, tautan kecil “Uji koneksi server”.
- Endpoint: `POST /api/v1/auth/login`
  `{identifier, password, device_name, platform:"android", app_version}`.
- Simpan `access_token`, `refresh_token`, `expires_at`, profil ringkas.
- Error mapping: `401 INVALID_CREDENTIALS` → “Email/NIK atau kata sandi salah.”;
  `429 TOO_MANY_ATTEMPTS` → “Terlalu banyak percobaan. Coba lagi dalam 1 menit.”;
  jaringan gagal → “Server tidak dapat dihubungi. Periksa alamat server & koneksi.”
- Tampilkan versi aplikasi + versi server (`meta.version`) di bawah.
- **DoD:** alamat server salah → pesan jelas, tidak crash; login demo berhasil;
  keyboard `imeAction=Next/Done`, autofill password manager didukung.

### S3 — Beranda
- Endpoint: `GET /api/v1/auth/me` (+ `GET /announcements?per_page=3`,
  `GET /payslips` untuk kartu slip terakhir; boleh paralel).
- Isi: sapaan “Selamat pagi, Dimas” + tanggal panjang; kartu status presensi hari
  ini (jam masuk/keluar, badge status, tombol **Presensi Sekarang**);
  grid KPI (Hadir bulan ini, Terlambat, Sisa cuti, Pengajuan terbuka);
  kartu “Menunggu persetujuan Anda” (bila `permissions.can_approve`);
  pengumuman terbaru (maks 3) ; pintasan Slip & Pengajuan.
- Pull-to-refresh. Bila `attendance_today.attendance.status == LEAVE` tampilkan
  badge indigo “Cuti”.
- **DoD:** semua angka berasal dari API (tanpa dummy); badge approval hanya
  muncul untuk atasan/HR/FIN.

### S4 — Presensi (paling penting)
- Endpoint: `GET /attendance/today`, `POST /attendance/punch`.
- Tata letak mengikuti web: kartu jam besar (jam berjalan `08:45:12`,
  `tabular-nums`), status chip (“Belum presensi” / “Sedang bekerja” /
  “Presensi selesai”), kartu **Kamera** (CameraX preview 4:3, tombol “Ambil
  Foto”, “Ulangi”, tombol galeri), baris **lokasi** (“Mendeteksi lokasi…” →
  “Anda berada ±16 m dari Jakarta HQ (Pusat) · radius 50 m”), daftar detail
  (Shift hari ini, toleransi, radius), lalu tombol besar
  **Presensi Masuk** / **Presensi Keluar** (nonaktif bila `can_check_in/out`
  false, dengan alasan di bawah tombol).
- Alur punch: minta izin lokasi & kamera dengan penjelasan → ambil foto (selfie
  wajib bila `geofence.selfie_required`) → kompres ke lebar `attendance.photo_max_width`
  (720 px) kualitas 70 → base64 data URL `data:image/jpeg;base64,…` → kirim
  JSON dengan header **`Idempotency-Key: <uuid>`**.
- Payload: `{type:"in"|"out", lat, lng, accuracy, photo, note, device,
  captured_at, offline}`. `captured_at` (ISO-8601) hanya untuk jejak antrean
  offline — **cap waktu presensi tetap ditentukan server**.
- Sukses: tampilkan hasil server (`data.punch.geofence`, `late_minutes`,
  `work_minutes`) di snackbar/kartu, perbarui status.
- `422 PUNCH_REJECTED` (geofence/duplikat/selfie) → dialog dengan `message`
  server apa adanya + solusi (“Mendekatlah ke lokasi kantor…”).
- **Offline:** simpan di antrean Room (lihat Bab 9) dan tampilkan
  “Presensi tersimpan, akan dikirim otomatis.”
- **DoD:** presensi berhasil di dalam radius; di luar radius ditolak dengan pesan
  server; retry jaringan tidak membuat baris ganda (`meta.idempotent_replay`
  ditampilkan sebagai “Tersimpan sebelumnya”).

### S5 — Riwayat & Rekap Presensi
- `GET /attendance/summary?month=YYYY-MM` (ringkasan) +
  `GET /attendance/history?month=YYYY-MM` (daftar harian).
- Header: pemilih bulan (chevron kiri/kanan, maks 12 bulan ke belakang),
  4 KPI: Hadir, Terlambat, Absen, Jam kerja; daftar kartu per hari
  (tanggal + hari, jam masuk–keluar, badge status, menit terlambat, foto kecil).
- **DoD:** pindah bulan memuat ulang; total cocok dengan `meta.totals`.

### S6 — Jadwal
- `GET /schedule/today` + `GET /schedule?from&to` (maks 92 hari).
- Tampilan: header “Jadwal Saya” + rentang; strip 7 hari (Sen–Min) dengan
  indikator hari kerja/libur; daftar per hari: tanggal, nama shift + jam,
  chip `operational_type` (NORMAL/WFH/FIELD/SHIFT); tombol
  **Ajukan Perubahan** (S7).
- **DoD:** hari tanpa shift menampilkan “Tidak ada jadwal”; libur ditandai slate.

### S7 — Pengajuan Perubahan Jadwal
- `GET /schedule-requests`, `POST /schedule-requests`, `POST /schedule-requests/{id}/cancel`,
  `GET /shifts` untuk pilihan shift.
- Form: Shift pengganti (dropdown dari `/shifts`, tampil `code · name · 08:00–17:00`),
  Tanggal mulai & selesai (date range), Alasan (opsional, 255).
- Aturan klien (cermin server, tetap server yang memutus): tidak boleh tanggal
  lampau; tidak boleh ada pengajuan terbuka (`meta.has_open_request`);
  shift sama dengan jadwal saat ini ditolak.
- Daftar riwayat dengan badge status + tombol Batalkan (hanya `PENDING`).
- **DoD:** `409 OPEN_REQUEST_EXISTS`/`422 SAME_SHIFT` tampil sebagai pesan jelas.

### S8 — Cuti
- Tab **Saldo** (`GET /leave/balances?year=`): kartu per jenis (nama, kuota,
  terpakai, sisa, progress bar teal), tahun dapat diganti.
- Tab **Riwayat** (`GET /leaves?per_page=`): kartu (jenis, tanggal, jumlah hari,
  badge status, tanggal dibuat) → detail (`GET /leaves/{id}`) dengan alur
  keputusan atasan/HR, lampiran (buka URL), dan tombol Batalkan selama status
  `PENDING`/`MANAGER_APPROVED`.
- FAB/tombol **Ajukan Cuti** → form: Jenis (`/leave/types`, tampil sisa kuota +
  peringatan bila butuh lampiran), Tanggal mulai/selesai (hitung hari kerja
  klien sebagai pratinjau), Alasan (wajib, 255), Lampiran (opsional: PDF/JPG/PNG
  ≤ 5 MB, kamera atau file picker) → `POST /leaves`.
- Tampilkan `data.warnings[]` setelah sukses (mis. “Saldo menjadi minus”).
- **DoD:** validasi server (`422 LEAVE_REJECTED`) tampil apa adanya; unggah
  lampiran multipart **dan** fallback data URL berfungsi.

### S9 — Lembur
- `GET /overtimes?month=&status=`, `GET /overtimes/policy`, `POST /overtimes`,
  `POST /overtimes/{id}/cancel`.
- Header ringkasan bulan (total pengajuan, jam disetujui, nominal).
- Form: Tanggal kerja, Jam mulai & selesai (TimePicker 24 jam), Alasan;
  tampilkan pratinjau estimasi (`policy` + simulasi `/overtimes/policy`) dan
  peringatan batas (min 30 menit, maks 4 jam/hari — dari `policy`).
- Riwayat: badge status, jam rencana/aktual, kompensasi (`Rp`).
  Batalkan hanya bila `PENDING`/`MANAGER_APPROVED`; `409 INVALID_STATE` → pesan.
- **DoD:** jam selesai ≤ mulai ditolak di klien sebelum kirim (dan server tetap
  memvalidasi).

### S10 — Klaim / Reimbursement
- `GET /claim-categories`, `GET /claims?status=`, `GET /claims/{id}`,
  `POST /claims`, `POST /claims/{id}/cancel`.
- Ringkasan: total klaim, nominal berjalan, nominal dibayarkan bulan ini
  (`meta.stats`).
- Form: Kategori (dropdown dengan plafon `max_amount` + tanda wajib bukti),
  Tanggal transaksi (≤ hari ini, ≤ `max_age_days`), Nominal (input angka dengan
  pemisah ribuan otomatis), Keterangan (255), Bukti (kamera/galeri/PDF ≤ 5 MB).
- Detail: nomor klaim mono, kategori, tanggal, nominal besar, status timeline
  (Diajukan → Atasan → HR/Finance → Dibayarkan), bukti (lihat gambar/PDF).
- **DoD:** kategori berplafon menampilkan sisa; nominal > plafon ditolak dengan
  pesan server.

### S11 — Slip Gaji
- `GET /payslips?year=` → kartu per periode (`Oktober 2026`, `SLIP/JKT/202610/0009`,
  take home pay, status periode).
- Detail (`GET /payslips/{id}`): ringkasan bruto, potongan, PPh 21, neto;
  daftar komponen (pendapatan lalu potongan) rapi dua kolom; tombol
  **Unduh PDF** memakai `download_url` (URL bertanda tangan, berlaku 5 menit).
- PDF: unduh via OkHttp ke `cacheDir/payslip`, tampilkan dengan `PdfRenderer`
  (scroll per halaman, pinch zoom sederhana), plus aksi bagikan/buka eksternal
  melalui `FileProvider`.
- **DoD:** PDF terbuka tanpa login ulang; URL kedaluwarsa → minta ulang daftar
  lalu unduh lagi (bukan error mentah).

### S12 — Bukti Potong 1721-A1
- `GET /tax-slips?year=` (hanya yang sudah diterbitkan), `GET /tax-slips/{year}`
  (rincian: bruto, netto, PKP, PPh terutang/dipotong/selisih, NPWP, PTKP),
  `GET /tax-slips/{year}/pdf`.
- Bila `404`/`409 NOT_ISSUED` → tampilan informatif “Bukti potong belum
  diterbitkan HR.” dengan tombol hubungi HR (email dari `app/config`).
- **DoD:** sama seperti slip (unduhan & viewer).

### S13 — Pengumuman
- `GET /announcements?page&per_page&unread&q`, `GET /announcements/{id}`,
  `POST /announcements/{id}/read`.
- Daftar: kartu dengan judul, badge kategori, penanda 📌 bila `is_pinned`,
  cuplikan, tanggal terbit, dot teal bila belum dibaca. Pencarian di top bar
  (`q`) dan filter “Belum dibaca”.
- Detail: judul, penulis, tanggal, isi (HTML sederhana → teks kaya; pakai
  `HtmlCompat`), lampiran (buka), otomatis tandai dibaca saat dibuka.
- **DoD:** paginasi “muat lagi” mengikuti `meta.pagination.has_more`;
  `unread_count` diperbarui di top bar.

### S14 — Notifikasi
- `GET /notifications?unread&type&page`, `POST /notifications/{id}/read`,
  `POST /notifications/read-all`.
- List dengan ikon per tipe (info teal, warning amber, danger rose), judul,
  pesan, waktu relatif (“2 jam lalu”), dot belum dibaca; tap → tandai dibaca +
  navigasi ke layar tujuan (lihat pemetaan di Bab 8.7).
- **DoD:** badge lonceng & tab “Belum dibaca” sinkron.

### S15 — Kotak Masuk Persetujuan
- `GET /approvals?type=all|leave|overtime|schedule|claim`,
  `GET /approvals/summary`, `POST /approvals/{type}/{id}/decide`.
- Hanya untuk `permissions.can_approve == true`; selain itu menu disembunyikan.
- Tab: Semua · Cuti · Lembur · Jadwal · Klaim (badge angka per tab dari
  `meta.counts`).
- Kartu pengajuan: avatar + nama + NIK + departemen; ringkasan detail per tipe
  (cuti: jenis, tanggal, hari; lembur: tanggal, jam, durasi; jadwal: shift lama →
  baru, tanggal; klaim: kategori, tanggal, nominal, bukti); alasan; tombol
  **Setujui** (teal) dan **Tolak** (danger) → dialog catatan (opsional, 255) →
  kirim `{decision:"APPROVED"|"REJECTED", note}`.
- Setelah aksi: snackbar pesan server (`data.message`), item hilang dari daftar,
  counter diperbarui.
- **DoD:** atasan melihat tahap 1; HR/Finance tahap 2; keputusan pada pengajuan
  sendiri/bukan haknya → pesan server (`422 DECISION_REJECTED`) tampil rapi,
  bukan crash.

### S16 — Profil & Pengaturan
- Data karyawan dari `/auth/me`: foto/avatar, nama, NIK, jabatan, departemen,
  unit, atasan langsung, email, telepon.
- **Server:** tampilkan alamat aktif, tombol **Ubah Server** (dialog input +
  “Uji koneksi”), “Kembalikan ke bawaan”, peringatan bahwa mengganti server
  mengakhiri sesi.
- **Sesi:** perangkat aktif (`api_tokens` tidak diekspos — cukup tampilkan
  perangkat ini), tombol **Keluar** (konfirmasi → `POST /auth/logout`),
  “Keluar dari semua perangkat” bila nanti tersedia di API.
- **Tentang:** versi aplikasi, versi server (`meta.version`), kebijakan privasi
  singkat, info demo mode.
- **DoD:** logout menghapus token & cache; ganti server memaksa login ulang.

---

## 8. Kontrak API & perilaku jaringan

### 8.1 Amplop & error

```json
{ "success": true,  "data": { }, "meta": { "request_id": "req_…", "server_time": "2026-10-30T06:34:05+07:00", "version": "2.17.0" } }
{ "success": false, "code": "VALIDATION_ERROR", "message": "Alasan cuti wajib diisi.",
  "errors": { "reason": "Alasan cuti wajib diisi." }, "meta": { } }
```

- `X-Request-Id`: kirim UUID per request (dipakai untuk korelasi log); simpan
  untuk pesan error “Kode: req_…”.
- Tampilkan `message` server sebagai pesan utama; `errors` dipetakan ke field
  form yang sesuai (kunci = nama field).

### 8.2 Base URL dinamis

- Simpan `serverUrl` (tanpa `/api/v1`, tanpa trailing slash), bangun endpoint
  dengan `baseUrl + "/api/v1/"`.
- Terima input `hris.asiaraya.co.id`, `https://…`, atau URL dengan sub-path
  (`https://domain/asiaraya`) — normalisasi ke skema `https`.
- **URL relatif dari server** (`photo_url`, `attachment_url`, `receipt_url`,
  `download_url`, `logo_url` — semuanya berbentuk `/media/…` atau `/api/v1/…`)
  harus diresolusi terhadap origin server yang aktif, bukan terhadap base URL
  API. Buat helper `resolveServerUrl(path)`.
- **Debug build** boleh `http://` (untuk server lokal) dan `cleartextTrafficPermitted`
  hanya untuk host loopback/LAN; **release build** wajib HTTPS (tolak `http://`
  dengan pesan “Alamat server harus HTTPS.”).
- Emulator: `http://10.0.2.2:8080` (server pratinjau repo HRIS). Perangkat fisik:
  `http://<IP-LAN>:8080` atau domain produksi.

### 8.3 Autentikasi

- Header: `Authorization: Bearer <access_token>`.
- `expires_at` (ISO-8601): refresh proaktif bila < 5 menit tersisa.
- **401 `UNAUTHENTICATED`** → panggil `POST /auth/refresh {refresh_token}` sekali
  (single-flight dengan `Mutex`), lalu ulangi request semula **sekali**.
  Gagal (`401 INVALID_REFRESH_TOKEN`) → hapus sesi → Login dengan pesan
  “Sesi berakhir. Silakan masuk kembali.”
- Jangan menyimpan token di `SharedPreferences` biasa: enkripsi dengan Android
  Keystore (AES/GCM) atau `security-crypto`; aktifkan `android:allowBackup="false"`.
- Logout: `POST /auth/logout` (best-effort) → bersihkan DataStore, Room, cache PDF.

### 8.4 Idempotensi presensi

- Setiap percobaan presensi mendapat **satu** `Idempotency-Key` (UUID v4) yang
  disimpan bersama item antrean; retry **memakai key yang sama**.
- Respons `200` dengan `meta.idempotent_replay: true` = sudah pernah tersimpan →
  tampilkan “Presensi sebelumnya sudah tersimpan”, bukan error.

### 8.5 Unggah berkas

- Foto selfie: JSON `photo` berupa data URL base64 (maks 4 MB setelah kompres;
  target ~150–400 KB) **atau** multipart `photo` (bila lebih nyaman).
- Lampiran cuti/klaim: multipart `attachment`/`receipt` (PDF/JPG/PNG ≤ 5 MB),
  `content-type` sesuai; tampilkan progres unggah.
- Kompresi gambar: `Bitmap.compress(JPEG, 70)` setelah downscale 720 px, jaga
  orientasi EXIF (selfie kamera depan → mirror seperti di web).

### 8.6 PDF & URL bertanda tangan

- `download_url` pada slip/bukti potong sudah bertanda tangan (HMAC, 5 menit,
  terikat employee + resource). Bisa dipakai tanpa header Authorization.
- Unduh dengan OkHttp ke `cacheDir`, validasi `Content-Type: application/pdf`,
  ukuran > 1 KB, lalu buka viewer internal. Jika `401` → ambil daftar ulang
  untuk URL baru (jangan simpan URL kedaluwarsa).

### 8.7 Pemetaan tautan notifikasi (field `link` berisi path web)

| `link` | Tujuan aplikasi |
|---|---|
| `/me/attendance` | Presensi |
| `/me/schedule`, `/schedules/…` | Jadwal |
| `/me/leave`, `/leave/…` | Cuti (detail bila ada id) |
| `/me/overtime`, `/overtime/…` | Lembur |
| `/me/payslip`, `/payroll/…` | Slip Gaji |
| `/claims/{id}` | Detail Klaim |
| `/announcements/{id}` | Detail Pengumuman |
| lainnya | Beranda |

### 8.8 Paginasi & retry

- `page`, `per_page` (default 20, maks 100); gunakan `meta.pagination.has_more`
  untuk “muat lagi”.
- Retry otomatis hanya untuk error jaringan/5xx, maks 2×, backoff 1s/2s.
  `429` → backoff 60 s dan tampilkan pesan; jangan spam.
- Timeout: connect 15 s, read 30 s, write 60 s.

---

## 9. Mode offline (antrean presensi)

1. Saat punch gagal karena jaringan, simpan entri Room `PendingPunch`:
   `id, type, lat, lng, accuracy, photoPath, note, capturedAt, idempotencyKey,
   status (QUEUED/SENDING/FAILED), attempts, lastError`.
2. `WorkManager` (unique work, `NetworkType.CONNECTED`, backoff eksponensial)
   mengirim antrean berurutan; kirim `offline: true` + `captured_at` asli.
3. Hasil:
   - `200` → tandai terkirim, hapus entri, notifikasi lokal “Presensi terkirim”.
   - `422 PUNCH_REJECTED` (mis. duplikat/geofence) → tandai `FAILED`, tampilkan
     di kartu “Presensi tertunda” dengan alasan server + tombol hapus.
   - `401` → refresh; gagal refresh → tahan antrean (jangan dibuang) sampai login.
4. UI: banner “1 presensi menunggu dikirim” di Beranda & Presensi.
5. Batas: 3 entri per hari; entri > 24 jam diberi tanda “kedaluwarsa” (tetap
   boleh dikirim, server yang memutus).

Cache ringan (opsional): `/app/config`, jadwal 7 hari, pengumuman 3 terakhir
disimpan untuk tampilan offline; beri label “data tersimpan”.

---

## 10. Pengaturan URL server (detail)

- Layar Login **dan** Profil & Pengaturan punya akses ke pengaturan server.
- Dialog: input URL (placeholder `https://hris.asiaraya.co.id`), tombol
  **Uji Koneksi**, **Simpan**, **Bawaan**.
- “Uji Koneksi”: bila sudah login → `GET /api/v1/auth/me`; bila belum →
  lakukan `POST /api/v1/auth/login` saat submit. Kriteria server benar:
  ada `meta.version` dan (untuk me) `data.employee`.
- Normalisasi: buang spasi, tambahkan `https://` bila tanpa skema, buang
  trailing `/`, tolak path `/api/v1` ganda.
- Ganti server saat sesi aktif → konfirmasi → logout → buka Login.
- Tampilkan di Pengaturan: “Tersambung: https://… · HRIS v2.17.0”.

---

## 11. Keamanan & privasi

- Token di penyimpanan terenkripsi; `allowBackup=false`; `FLAG_SECURE` hanya
  pada layar PDF slip/pajak (opsional, beri catatan trade-off screenshot).
- Jangan log body yang memuat token/foto; redact `Authorization` di
  `HttpLoggingInterceptor` (aktif hanya `BuildConfig.DEBUG`).
- Bersihkan cache PDF & foto sementara saat logout.
- Hapus otomatis antrean presensi yang sudah sukses > 7 hari.
- Izin minimal: `INTERNET`, `ACCESS_NETWORK_STATE`, `ACCESS_FINE_LOCATION`,
  `ACCESS_COARSE_LOCATION`, `CAMERA` (tanpa lokasi background).
- Minta izin **saat dibutuhkan** (presensi), dengan layar penjelasan singkat.

---

## 12. Struktur proyek

```
app/src/main/java/id/co/asiaraya/hris/
  HrisApp.kt
  core/
    network/   ApiClient.kt ApiEnvelope.kt ErrorMapper.kt AuthInterceptor.kt TokenAuthenticator.kt
    auth/      SessionStore.kt SessionManager.kt TokenCrypto.kt
    data/      Result.kt
    ui/        theme/ (Color.kt Type.kt Shape.kt Theme.kt) components/ format/ (Money.kt Date.kt)
    di/        NetworkModule.kt StorageModule.kt
  data/
    api/       dto/ (semua model @Serializable) service/ (AuthApi, AttendanceApi, …)
    repo/      AuthRepository, AttendanceRepository, ScheduleRepository, LeaveRepository,
               OvertimeRepository, ClaimRepository, PayrollRepository, TaxRepository,
               AnnouncementRepository, NotificationRepository, ApprovalRepository
    local/     db/ (HrisDatabase, PendingPunchDao), prefs/ (SettingsDataStore)
    worker/    PunchSyncWorker.kt
  feature/
    splash/ login/ home/ attendance/ history/ schedule/ schedulerequest/
    leave/ overtime/ claim/ payslip/ tax/ announcement/ notification/ approval/ profile/
```
- Setiap fitur: `Screen.kt` (Compose), `ViewModel.kt`, `UiState.kt`.
- Navigasi: **Navigation Compose** dengan route string (`home`,
  `attendance`, `schedule`, `leave`, `payslip/{id}`, …).
- Semua string di `strings.xml` (kecuali label status dari server).

---

## 13. Rencana kerja bertahap + Definition of Done

> Kerjakan **satu fase per iterasi**, laporkan hasil, tunggu konfirmasi sebelum
> fase berikutnya (kebiasaan proyek ini).

| Fase | Isi | DoD |
|---|---|---|
| **0** | Skeleton Gradle, tema & komponen, DI, ApiClient + envelope + auth interceptor/authenticator, DataStore, pengaturan server, Login, Splash, Shell + bottom bar, Beranda | Login demo berhasil ke `http://10.0.2.2:8080`, refresh token teruji (token dipendekkan), ganti server → login ulang, `assembleDebug` + lint hijau |
| **1** | Presensi: today, CameraX, GPS, geofence UX, punch JSON, hasil, riwayat + rekap | Punch sukses di dalam radius, ditolak di luar radius, retry tidak dobel |
| **2** | Jadwal + pengajuan tukar shift | Ajukan & batalkan; `has_open_request` dihormati |
| **3** | Cuti (saldo, form, lampiran) + Lembur | Form valid, lampiran terunggah, `warnings` tampil |
| **4** | Klaim + Slip Gaji + Pajak (PDF viewer) | Bukti terunggah, PDF terbuka, URL kedaluwarsa ditangani |
| **5** | Pengumuman, Notifikasi, Persetujuan (atasan/HR/FIN) | Keputusan 2 tahap dari aplikasi terlihat di web |
| **6** | Offline queue + WorkManager, aksesibilitas, performa, uji perangkat, rilis APK | Uji offline → online; APK release tertandatangani; README final |

Setiap fase: `./gradlew assembleDebug lint testDebugUnitTest` harus lulus,
screenshot tiap layar disimpan di `docs/screenshots/`, commit dengan pesan
`feat(<area>): <ringkasan>`.

---

## 14. Pengujian

**Unit test (wajib):**
- `ApiEnvelope` parsing sukses/gagal; `ErrorMapper` untuk tiap `code`.
- `AuthInterceptor` menambahkan bearer; `TokenAuthenticator` single-flight
  refresh (MockWebServer: 401 → refresh → retry sekali → 401 berakhir logout).
- Formatter rupiah/tanggal/bulan Indonesia.
- `PendingPunch` idempotency key tetap sama saat retry.

**Manual (uji terhadap server demo):**

| Peran | Akun | Fokus |
|---|---|---|
| Karyawan | `dimas.aditya@asiaraya.co.id` | presensi, cuti, lembur, klaim, slip, pajak |
| Atasan | `budi.santoso@asiaraya.co.id` | kotak masuk tahap 1 (Dimas bawahannya) |
| HR | `hr@asiaraya.co.id` | tahap 2 semua jenis, lihat semua pengumuman |
| Finance | `finance@asiaraya.co.id` | persetujuan klaim tahap final |

Kata sandi demo: `Password123!`. Token demo (tanpa login, untuk Postman):
`DEMO-API-EMPLOYEE-TOKEN-0004`, `DEMO-API-MANAGER-TOKEN-0002`,
`DEMO-API-HR-TOKEN-0001`.

**Matriks QA minimum:** 4 akun × (login, beranda, presensi, cuti, lembur,
klaim, slip, pajak, notifikasi, persetujuan) pada emulator API 30 & 34, plus
satu perangkat fisik; uji rotasi layar, dark mode OS (aplikasi tetap terang),
font scale 1.3, dan mode pesawat (banner offline).

---

## 15. Build, signing, rilis

- `versionName = "1.0.0"`, `versionCode = 1` (naikkan tiap rilis; samakan
  penomoran rilis dengan versi backend yang didukung, mis. catat “API 2.17.0”).
- Signing: `keystore.properties` (gitignored) dengan `storeFile`, `storePassword`,
  `keyAlias`, `keyPassword`; blok `signingConfigs` hanya di `release`.
- `release`: `isMinifyEnabled = true`, `isShrinkResources = true`; ProGuard/R8
  keep rules untuk `kotlinx.serialization` & Retrofit.
- Artefak: `app-release.apk` (dan AAB opsional) + `mapping.txt`; README memuat
  langkah build, cara set server, akun demo, dan tangkapan layar.
- CI (GitHub Actions): `assembleDebug`, `testDebugUnitTest`, `lint` pada PR.

---

## 16. Format laporan tiap fase

1. Ringkasan perubahan (layar/file baru).
2. Bukti: perintah build + hasil, screenshot.
3. Hasil uji manual (akun apa, endpoint apa, hasil apa).
4. Kendala/penyimpangan dari spesifikasi + usulan.
5. Pertanyaan terbuka (bila ada) — **jangan** menebak kontrak API.

---

## Lampiran A — Ringkasan endpoint `/api/v1`

```
POST   /auth/login                     {identifier,password,device_name,platform,app_version}
POST   /auth/refresh                   {refresh_token}
POST   /auth/logout
GET    /auth/me                        employee, counters, attendance_today, permissions
GET    /app/config                     app, company, features, attendance, leave, overtime, claims, limits

GET    /announcements?page&per_page&category&q&unread
GET    /announcements/{id}
POST   /announcements/{id}/read

GET    /schedule/today
GET    /schedule?from&to
GET    /shifts
GET    /schedule-requests
POST   /schedule-requests              {requested_shift_id,start_date,end_date,reason}
POST   /schedule-requests/{id}/cancel

GET    /attendance/today
POST   /attendance/punch               {type,lat,lng,accuracy,photo,note,device,captured_at,offline}
GET    /attendance/history?month=YYYY-MM
GET    /attendance/summary?month=YYYY-MM

GET    /overtimes?month&status&per_page
GET    /overtimes/policy
POST   /overtimes                      {work_date,planned_start,planned_end,reason}
POST   /overtimes/{id}/cancel          {note?}

GET    /leave/types
GET    /leave/balances?year
GET    /leaves?status&per_page
GET    /leaves/{id}
POST   /leaves                         {leave_type_id,start_date,end_date,reason,attachment?}
POST   /leaves/{id}/cancel

GET    /payslips?year
GET    /payslips/{id}
GET    /payslips/{id}/pdf

GET    /claim-categories
GET    /claims?status&per_page
GET    /claims/{id}
POST   /claims                         {category_id,claim_date,amount,description,receipt?}
POST   /claims/{id}/cancel

GET    /tax-slips?year
GET    /tax-slips/{year}
GET    /tax-slips/{year}/pdf

GET    /notifications?unread&type&page
POST   /notifications/{id}/read
POST   /notifications/read-all

GET    /approvals?type=all|leave|overtime|schedule|claim&page
GET    /approvals/summary
POST   /approvals/{type}/{id}/decide  {decision:"APPROVED"|"REJECTED",note?}
```

## Lampiran B — Contoh JSON penting

**Login (200)**
```json
{"success":true,"data":{
  "tokens":{"token_type":"Bearer","access_token":"ar_…","expires_at":"2026-10-30T14:33:56+07:00",
            "expires_in":28800,"refresh_token":"arr_…","refresh_expires_at":"2026-11-29T06:33:56+07:00"},
  "employee":{"id":4,"employee_code":"ARG-JKT-0421","full_name":"Dimas Aditya",
              "email":"dimas.aditya@asiaraya.co.id","role":"KARYAWAN","position":"Software Engineer",
              "department":"IT","business_unit":"Jakarta HQ (Pusat)",
              "manager":{"id":2,"name":"Budi Santoso"},"photo_url":null}},
 "meta":{"request_id":"req_…","server_time":"2026-10-30T06:33:56+07:00","version":"2.17.0"}}
```

**Presensi hari ini (200)**
```json
{"success":true,"data":{"date":"2026-10-30",
  "shift":{"id":1,"code":"SHIFT-PAGI","name":"Shift Pagi","date":"2026-10-30","start_time":"08:00",
           "end_time":"17:00","break_minutes":60,"late_tolerance":15,"operational_type":"NORMAL"},
  "shift_start":"08:00","shift_end":"17:00","late_tolerance":15,"operational_type":"NORMAL",
  "can_check_in":true,"can_check_out":false,
  "attendance":{"date":"2026-10-30","checked_in":false,"checked_out":false,"status":null},
  "geofence":{"required":true,"selfie_required":true,"unit":"Jakarta HQ (Pusat)",
              "lat":-6.2241,"lng":106.8089,"radius":50}}}
```

**Punch sukses (200)**
```json
{"success":true,"data":{
  "punch":{"type":"in","status":"PRESENT","late_minutes":0,"work_minutes":0,"distance":15.7,
           "geofence":"Anda berada 16 m dari titik kantor (radius 50 m).",
           "at":"2026-10-30T06:34:37+07:00","server_date":"2026-10-30"},
  "attendance":{"id":21007,"date":"2026-10-30","status":"PRESENT","checked_in":true,
                "check_in_at":"2026-10-30T06:34:37+07:00","check_in_photo_url":"/media/attendance/….jpg",
                "late_minutes":0,"source":"ANDROID"},
  "today":{ /* sama seperti attendance/today */ },
  "message":"Check-in tepat waktu. Selamat bekerja!"},
 "meta":{"idempotent_replay":false}}
```

**Punch ditolak (422)**
```json
{"success":false,"code":"PUNCH_REJECTED",
 "message":"Anda berada 37.245 m dari Jakarta HQ (Pusat), di luar radius 50 m. Mendekatlah ke lokasi kantor untuk melakukan presensi.",
 "errors":[],"meta":{}}
```

**Kotak persetujuan (200)**
```json
{"success":true,"data":[
  {"type":"leave","type_label":"Pengajuan Cuti","id":6,"employee":{"id":4,"full_name":"Dimas Aditya",
    "employee_code":"ARG-JKT-0421","department":"IT","position":"Software Engineer","photo_url":null},
   "status":"PENDING","status_label":"Menunggu atasan","reason":"Keperluan keluarga",
   "created_at":"2026-10-30T06:34:45+07:00","decide_url":"/api/v1/approvals/leave/6/decide",
   "detail":{"leave_type":"Cuti Izin Penting","start_date":"2026-11-02","end_date":"2026-11-02","days":1}}],
 "meta":{"counts":{"leave":2,"overtime":2,"schedule":1,"claim":2},
         "pagination":{"page":1,"per_page":20,"total":7,"total_pages":1,"has_more":false}}}
```

**Keputusan (200)**
```json
{"success":true,"data":{"type":"leave","status":"MANAGER_APPROVED","stage":"MANAGER",
 "message":"Pengajuan Cuti telah disetujui atasan langsung dan menunggu HR."}}
```

**Slip gaji (200)**
```json
{"success":true,"data":[{"id":897,"slip_number":"SLIP/JKT/202610/0009",
  "period":{"year":2026,"month":10,"label":"Oktober 2026","pay_date":"2026-10-25","status":"PAID"},
  "business_unit":"Jakarta HQ (Pusat)","gross_salary":17800000,"total_deduction":1967000,
  "pph_amount":0,"net_salary":15830000,"take_home_pay":15830000,
  "download_url":"/api/v1/payslips/897/pdf?employee=4&expires=1793317201&signature=…&download=1"}],
 "meta":{"years":[2026],"total_net":145520000}}
```

## Lampiran C — Kode error yang harus ditangani

| Code | HTTP | Perlakuan UI |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Tandai field dari `errors`, tampilkan `message` |
| `INVALID_CREDENTIALS` | 401 | Pesan di form login |
| `TOO_MANY_ATTEMPTS` | 429 | Hitung mundur 60 s, nonaktifkan tombol |
| `UNAUTHENTICATED` | 401 | Refresh → retry → logout bila gagal |
| `INVALID_REFRESH_TOKEN` | 401 | Hapus sesi → Login |
| `FORBIDDEN` | 403 | Layar “Anda tidak memiliki akses” |
| `NOT_FOUND` | 404 | Empty state “Data tidak ditemukan” |
| `CONFLICT`/`OPEN_REQUEST_EXISTS` | 409 | Dialog informatif + CTA lihat pengajuan |
| `SAME_SHIFT` | 422 | Pesan di form |
| `INVALID_STATE` | 409 | Snackbar + refresh daftar |
| `PUNCH_REJECTED` | 422 | Dialog alasan geofence/duplikat/selfie |
| `LEAVE_REJECTED` / `OVERTIME_REJECTED` / `CLAIM_REJECTED` | 422 | Pesan aturan di form |
| `CANCEL_REJECTED` / `DECISION_REJECTED` | 422 | Snackbar + refresh |
| `UPLOAD_ERROR` | 422 | Pesan ukuran/format berkas |
| `NOT_FINAL` | 409 | “Slip belum final.” |
| `NOT_ISSUED` | 409 | “Bukti potong belum diterbitkan HR.” |
| `TOO_MANY_REQUESTS` | 429 | Backoff, pesan tenang |
| `PDF_ERROR` / 5xx | 500 | Error state + “Coba lagi” |

## Lampiran D — Checklist QA manual

- [ ] Login salah → pesan rapi; login benar → Beranda memuat data nyata.
- [ ] Ganti alamat server ke nilai salah → pesan jelas; kembali ke benar → berhasil.
- [ ] Matikan server → banner offline, data cache tampil, tidak crash.
- [ ] Presensi dalam radius + selfie → sukses; di luar radius → pesan jarak.
- [ ] Presensi dua kali → yang kedua “sudah check-in”, bukan baris ganda.
- [ ] Mode pesawat → presensi masuk antrean → nyalakan jaringan → terkirim otomatis.
- [ ] Ajukan cuti tanpa alasan → error field; dengan lampiran PDF → sukses.
- [ ] Klaim melebihi plafon → pesan plafon; bukti wajib → diblokir tanpa bukti.
- [ ] Slip gaji → PDF terbuka; URL kedaluwarsa → unduh ulang otomatis.
- [ ] Atasan menyetujui cuti → status “Menunggu HR”; HR menyetujui → “Disetujui”.
- [ ] Karyawan membuka menu Persetujuan → tidak tersedia (menu tersembunyi).
- [ ] Font scale 1.3 & rotasi → tidak ada teks terpotong/tombol tertutup.
- [ ] Logout → kembali ke Login, token & cache bersih (buka app lagi tetap Login).

## Lampiran E — Versi ringkas (bila perlu prompt pendek)

> Buat aplikasi Android **Kotlin + Jetpack Compose (Material 3)** untuk HRIS
> Asia Raya yang memakai REST API `/api/v1` (spesifikasi `docs/openapi.yaml`).
> Native, bukan WebView. Fitur: login token+refresh, pengumuman, jadwal &
> tukar shift, presensi GPS+selfie dengan `Idempotency-Key` dan antrean offline
> (Room + WorkManager), riwayat presensi, lembur, cuti, slip gaji PDF, klaim,
> bukti potong 1721-A1 PDF, notifikasi, serta kotak masuk persetujuan
> (atasan/HR/Finance). Alamat server **dapat diatur** di layar Login dan
> Pengaturan (default `https://hris.asiaraya.co.id`, emulator
> `http://10.0.2.2:8080`), HTTPS wajib di release. Gaya visual mengikuti web:
> navy `#0F2C45` untuk tombol primer, teal `#0D9488`/`#0F766E` untuk aksi &
> tautan, latar `#F4F6FA`, kartu putih radius 14 dp dengan border `#E5EAF2`,
> font Inter, badge status berwarna (amber menunggu, teal disetujui, rose
> ditolak, indigo cuti/terjadwal, slate dibatalkan), format Rupiah & tanggal
> Indonesia. Struktur: MVVM + Hilt + Retrofit/OkHttp + kotlinx.serialization,
> DataStore untuk sesi & pengaturan, token dienkripsi Keystore, bottom nav
> Beranda/Presensi/Jadwal/Cuti/Lainnya. Kerjakan bertahap (login & shell →
> presensi → jadwal → cuti/lembur → klaim/slip/pajak → pengumuman/notifikasi/
> persetujuan → offline & rilis), tiap fase harus build + lint + unit test lulus
> dan diuji ke server demo dengan akun `dimas.aditya@…`, `budi.santoso@…`,
> `hr@…`, `finance@…` (sandi `Password123!`).
