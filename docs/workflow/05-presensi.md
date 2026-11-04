# 05 — Presensi (GPS + Selfie)

> Presensi harian karyawan berbasis PWA: check-in/check-out dengan geofence dan
> selfie, monitoring live, rekap, dan pengaturan toleransi/denda.

## 1. Fungsi

* **Check-in / check-out** dari PWA (`/me/attendance`) dengan:
  * validasi **geofence** (radius unit atau radius khusus karyawan),
  * **selfie** sebagai bukti kehadiran,
  * pencatatan koordinat, waktu server, dan perangkat.
* **Status harian otomatis**: `PRESENT` (tepat waktu), `LATE` (terlambat),
  `ABSENT` (alpha), `LEAVE` (dibuat sistem saat cuti disetujui).
* **Denda keterlambatan** dengan lima metode: `PER_MINUTE`, `PER_DAY`, `FIXED`,
  `PERCENT`, dan `PROGRESSIVE` (tier 0–1 jam, 1–2 jam, 2–3 jam, >3 jam) dengan
  nominal & batas yang dapat diatur.
* **Live monitoring** HR (`/attendance/live`) — posisi & punch terakhir.
* **Presensi harian** (`/attendance/daily`) dan **rekap bulanan**
  (`/attendance/rekap`) + ekspor CSV.
* **Dashboard karyawan** (`/dashboard`) menampilkan tabel **riwayat presensi
  minggu berjalan** (Senin–Minggu): shift, jam masuk/keluar, durasi kerja, dan
  status per hari, dengan baris hari ini disorot serta ringkasan
  hadir/terlambat/cuti/jam kerja minggu itu.
* Penandaan alpha manual oleh HR (`POST /attendance/mark-absent`).
* Pengaturan presensi: jam kerja, toleransi, wajib geofence/selfie, bypass WFH,
  resolusi & kualitas foto, denda.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Karyawan | Pelaku presensi | Check-in/out, lihat riwayat sendiri |
| HR | Pengawas & pengelola | Live monitoring, harian, rekap, tandai alpha, pengaturan |
| FIN | Pengguna data | Melihat rekap presensi melalui laporan |

## 3. Workflow

### 3.1 Check-in

```text
Karyawan buka /me/attendance ──► ambil selfie + GPS
        │
        ├─ di luar radius? ──► ditolak (kecuali shift WFH & bypass aktif)
        ├─ belum ada jadwal? ──► memakai jam kerja default pengaturan
        ▼
tentukan status: PRESENT / LATE (+ menit keterlambatan & denda tier)
        │
        ├─ simpan attendance (employee, tanggal, check_in_at, koordinat, foto)
        └─ notifikasi & audit log
```

1. Sistem memuat shift hari ini (dari jadwal) atau jam default.
2. Hitung selisih waktu masuk terhadap jam shift + toleransi.
3. Bila terlambat, hitung denda sesuai tier progresif.
4. Simpan punch; satu karyawan satu baris per tanggal (unique index).

### 3.2 Check-out

1. Karyawan melakukan punch keluar.
2. Sistem menghitung `work_minutes` dan menandai pulang lebih awal bila ada.
3. Foto/koordinat keluar disimpan sebagai bukti.

### 3.3 Cuti & alpha

* Saat cuti **disetujui final**, sistem membuat baris presensi `LEAVE` untuk
  setiap tanggal cuti (dan menghapusnya bila approval dibatalkan).
* HR dapat menandai karyawan `ABSENT` untuk tanggal tanpa presensi.

### 3.4 Dashboard karyawan — minggu berjalan

```text
/dashboard (role KARYAWAN / FIN / HR dengan data sendiri)
        │  minggu berjalan = Senin s.d. Minggu (ISO week, zona Asia/Jakarta)
        ▼
tabel "Riwayat Presensi Minggu Ini"
  Senin … Minggu  ·  shift terjadwal  ·  masuk  ·  keluar  ·  durasi  ·  status
```

* **Selalu tujuh baris** agar tidak ada hari yang hilang. Hari yang belum punya
  baris presensi tetap tampil dengan shift terjadwal (bila ada) dan status
  kosong (“Belum tercatat”), bukan dianggap alpha — baris `ABSENT` hanya dibuat
  oleh HR (`POST /attendance/mark-absent`) atau oleh proses cuti/alpha terkait.
* Hari ini ditandai badge **Hari ini** dan barisnya disorot; bila belum
  check-in, statusnya “Belum presensi”.
* Durasi memakai `attendance.work_minutes` (menit kerja bersih setelah potong
  istirahat), ditampilkan dalam jam dengan satu desimal.
* Ringkasan di kepala kartu: jumlah hadir, terlambat, cuti, alpha, dan total jam
  kerja minggu tersebut; tautan **Rekap bulan →** membuka `/me/attendance`
  untuk rekap bulanan penuh.

### 3.5 Monitoring & rekap

```text
/attendance/live  → punch terbaru + posisi (HR)
/attendance/daily → tabel kehadiran per tanggal (hadir/telat/alpha/cuti)
/attendance/rekap → ringkasan bulanan per karyawan + export CSV
```

## 4. Status

| Field | Nilai | Arti |
|-------|-------|------|
| `status` | `PRESENT` | Hadir tepat waktu |
| | `LATE` | Hadir terlambat (ada `late_minutes` & denda) |
| | `ABSENT` | Tanpa presensi / ditandai alpha |
| | `LEAVE` | Cuti disetujui (dibuat sistem) |
| `check_in_status` | `ON_TIME` / `LATE` | Status punch masuk |
| Sumber (`source`) | `PWA` / `ANDROID` / `IOS` / `KIOSK` | Perangkat saat punch |
| | `SYSTEM` | Baris otomatis (cuti disetujui, penandaan alpha) |

Pengaturan kunci: `attendance_work_start`, `attendance_work_end`,
`attendance_late_tolerance`, `attendance_geofence_required`,
`attendance_selfie_required`, `attendance_wfh_bypass_geofence`,
`attendance_photo_max_width`, `attendance_photo_quality`,
serta denda: `attendance_late_deduction_type` + `attendance_late_tier_1_max`,
`attendance_late_tier_2_max`, `attendance_late_tier_3_max` dan nominal
`attendance_late_tier_1_amount` … `tier_4_amount` (default 25.000 / 50.000 /
75.000 / 125.000).

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /me/attendance` | `AttendanceController@mine` | Halaman presensi PWA |
| `GET /dashboard` | `DashboardController@index` | Dashboard karyawan — tabel presensi minggu berjalan (`DashboardModel::myAttendance`) |
| `POST /attendance/punch` | `AttendanceController@punch` | Check-in/out (web) |
| `GET /attendance/live` | `AttendanceController@live` | Monitoring live |
| `GET /attendance/daily` | `AttendanceController@daily` | Presensi harian |
| `GET /attendance/rekap` | `AttendanceController@rekap` | Rekap bulanan + CSV |
| `POST /attendance/mark-absent` | `AttendanceController@markAbsent` | Tandai alpha |
| `GET/POST /attendance/settings` | `AttendanceController@settings/saveSettings` | Pengaturan |

Berkas pendukung: `app/Models/AttendanceModel.php`, `app/Views/attendance/*`,
`app/Views/dashboard/employee.php` (tabel minggu berjalan),
tabel `attendance` (unique `employee_id + work_date`) dan `schedules`
(dipakai mengisi shift terjadwal pada hari yang belum ada presensi).

## 6. Integrasi & catatan

* Presensi menjadi dasar **payroll** (hari hadir, alpha, denda) dan **lembur**
  (validasi jam kerja).
* Presensi juga tersedia melalui aplikasi Android (`/api/v1/attendance/*`)
  dengan `Idempotency-Key` agar punch ganda tidak tercatat dua kali.
* Foto presensi disimpan pada folder `storage/uploads` dan ditampilkan di
  halaman monitoring.
