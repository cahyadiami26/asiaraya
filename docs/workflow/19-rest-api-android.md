# 19 — REST API v1 (Aplikasi Android)

> Antarmuka JSON stateless untuk aplikasi Android karyawan: autentikasi token,
> presensi, jadwal, lembur, cuti, slip gaji, klaim, bukti potong, pengumuman,
> notifikasi, dan kotak persetujuan atasan.

## 1. Fungsi

* **Autentikasi bearer token**: login, refresh, logout, sesi (`/api/v1/auth/*`).
* **Profil karyawan lengkap** (`/api/v1/me/profile`): identitas, masa kerja,
  kontrak, penempatan organisasi, atasan langsung, kontak, data pribadi,
  payroll/bank, saldo cuti, dan ringkasan dokumen.
* **Konfigurasi aplikasi**: nama perusahaan, jam kerja, kebijakan cuti/lembur,
  versi minimum aplikasi (`/api/v1/app/config`).
* **Presensi mobile**: presensi hari ini, punch (selfie + GPS), riwayat, ringkasan.
* **Jadwal & shift**: jadwal hari ini, rentang tanggal, daftar shift, pengajuan
  perubahan jadwal + pembatalan.
* **Lembur & cuti**: daftar, kebijakan/saldo, pengajuan, pembatalan, detail.
* **Payroll**: daftar slip, detail, unduh PDF (URL bertanda tangan).
* **Klaim**: kategori, daftar, pengajuan, detail, pembatalan.
* **Pajak**: daftar & detail bukti potong per tahun, unduh PDF.
* **Pengumuman & notifikasi**: daftar, detail, tandai dibaca (semua/satu).
* **Kotak persetujuan**: gabungan cuti/lembur/jadwal/klaim + ringkasan +
  keputusan (`/api/v1/approvals/{type}/{id}/decide`).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Karyawan | Pengguna utama aplikasi | Semua endpoint data pribadi; keputusan persetujuan hanya bila ia atasan langsung |
| Atasan langsung (HR/FIN/KARYAWAN) | Approver mobile | Kotak persetujuan berisi pengajuan timnya (bukan pengajuan sendiri) |
| HR | Pengelola | Tidak ada endpoint khusus HR; pengelolaan tetap di web |
| FIN | Approver/verifikator | Dapat memutuskan klaim tim yang menjadi tanggung jawabnya |

> API bersifat **employee-scoped**: setiap query selalu difilter `employee_id`
> milik token, kecuali kotak persetujuan yang difilter `direct_manager_id`.

## 3. Workflow

### 3.1 Login & sesi

```text
POST /api/v1/auth/login {identifier, password, device_name, platform, app_version}
        │  401 INVALID_CREDENTIALS bila salah (throttle per IP/identifier)
        ▼  200 {tokens:{access_token, refresh_token, expires_at, ...}, employee:{...}}
Aplikasi menyimpan token di EncryptedSharedPreferences
        │
        ├─ access token kedaluwarsa (8 jam) → POST /api/v1/auth/refresh {refresh_token}
        │      refresh berlaku 30 hari → token baru
        └─ POST /api/v1/auth/logout → token dicabut (revoked_at)
```

### 3.2 Presensi

```text
GET  /api/v1/attendance/today   → jadwal hari ini + status punch
POST /api/v1/attendance/punch   {type: IN|OUT, latitude, longitude, photo}
        │  validasi geofence (bila wajib), selfie (bila wajib), jam kerja
        ▼
   attendance tersimpan; respons memuat status PRESENT/LATE + jarak
GET  /api/v1/attendance/history?from=&to=  ·  /summary?month=
```

### 3.3 Pengajuan & persetujuan

```text
[Karyawan] POST /api/v1/leaves | /api/v1/overtimes | /api/v1/schedule-requests | /api/v1/claims
        ▼
   status PENDING → atasan menyetujui di aplikasi
        ▼
[Atasan] GET /api/v1/approvals?type=all → POST /api/v1/approvals/{type}/{id}/decide
        {decision: APPROVED|REJECTED, note}
        ▼
   efek sama dengan web (ApprovalEngine): cuti memotong saldo saat HR
   menyetujui; lembur menghitung kompensasi; klaim lanjut ke FIN
```

Aturan tahap pada endpoint persetujuan:

* `GET /api/v1/approvals` hanya berisi pengajuan yang **benar-benar** bisa
  diputus pengguna: tanpa pengajuan sendiri, dan untuk HR tanpa pengajuan rekan
  HR yang masih menunggu atasan.
* `POST .../decide` menolak (`422 DECISION_REJECTED`) bila pengguna memutus
  pengajuannya sendiri atau memutus tahap yang belum berhak.
* Respons `decide` menyertakan `stage` (`MANAGER`/`HR`/`FINAL`) dan `final`
  (`true` bila status sudah final). Pengajuan milik HR (atau HR/Finance untuk
  klaim) **selalu final di tahap atasan**, sehingga `decide` mengembalikan
  `{stage: "MANAGER", status: "APPROVED", final: true}`.
* Pengajuan yang tidak akan punya approver (tanpa atasan langsung dan tanpa
  approver final lain) ditolak saat dibuat (`422 APPROVER_MISSING`).

### 3.4 Unduhan PDF

```text
GET /api/v1/payslips/{id}/pdf  (header Authorization)
        │  Android DownloadManager tidak bisa kirim header →
        ▼
   respons menyertakan URL bertanda tangan (expires + signature, 5 menit)
   yang bisa diunduh tanpa header
```

### 3.5 Profil karyawan

```text
GET /api/v1/me/profile?year=2026   (header Authorization)
        ▼
{ profile, employment, organization, manager, contact, personal,
  payroll, leave{summary,balances}, documents, permissions }
```

Dipakai layar **Profil & Pengaturan** aplikasi Android. Berbeda dari
`GET /api/v1/auth/me` yang hanya berisi sesi + penghitung beranda, endpoint ini
mengembalikan **data kepegawaian lengkap milik pemilik token**:

| Blok | Isi |
|------|-----|
| `profile` | NIK, nama, nama panggilan, foto, peran + label, status (`ACTIVE`) |
| `employment` | status kepegawaian (PKWT/PKWTT), tanggal bergabung, `tenure` (tahun/bulan/hari + label), kontrak (`start`, `end`, `remaining_days`, `expiring_soon` ≤ 60 hari), tanggal resign |
| `organization` | unit bisnis, departemen, jabatan (id, nama, kode) |
| `manager` | atasan langsung: id, nama, email, jabatan, departemen, status aktif (null bila belum ditetapkan) |
| `contact` | email perusahaan, username, telepon, alamat, `google_linked` |
| `personal` | tanggal lahir, umur, jenis kelamin, status pernikahan, agama |
| `payroll` | gaji pokok (+label rupiah), status pajak, NPWP, BPJS Kesehatan & Ketenagakerjaan, bank (nama, pemilik, nomor + versi tersamar) |
| `leave` | ringkasan cuti tahun berjalan (`total`, `approved_days`, `open`, `remaining`) + saldo per jenis |
| `documents` | jumlah dokumen: `total`, `verified`, `unverified`, `rejected`, `expiring_soon` (tanpa URL berkas) |
| `permissions` | peran, `can_approve`, `is_manager`, `editable_fields` (`phone`, `address`), `email_change_requires_hr` |

* `?year=` (2000–2100, default tahun berjalan) mengatur tahun saldo cuti;
  nilainya juga dikembalikan di `meta.year`.
* Endpoint **employee-scoped**: tidak ada parameter id karyawan, sehingga data
  rekan kerja tidak bisa dibaca. Tanpa token → `401 UNAUTHENTICATED`.
* Email perusahaan tidak dapat diubah dari aplikasi (harus HR); nomor HP dan
  alamat dapat diubah lewat web `/me/profile` — API saat ini hanya membaca.

## 4. Status & konvensi respons

| Hal | Aturan |
|-----|--------|
| Sukses | `{"success": true, "data": ..., "meta": {...}}` |
| Gagal | `{"success": false, "message": "...", "errors": {...}, "meta": {...}}` |
| Kode | 200 sukses, 201 dibuat, 401 sesi, 403 hak, 404 tidak ada, 422 validasi, 429 throttle, 500 server |
| Header | `X-Request-Id` untuk korelasi log; `Cache-Control: no-store` |
| Paginasi | `?page=&per_page=` (maks 100) + `meta.pagination.page/per_page/total/total_pages/has_more` |
| Tanggal | ISO-8601 (`2025-11-03`, `07:30` untuk jam) |
| Status domain | Sama dengan web (`PENDING`, `MANAGER_APPROVED`, `APPROVED`, …) |

## 5. Rute & berkas kode

| Grup | Rute | Controller |
|------|------|-----------|
| Auth | `POST /api/v1/auth/login`, `/api/v1/auth/refresh`, `/api/v1/auth/logout`; `GET /api/v1/auth/me`, `GET /api/v1/app/config` | `AuthApiController` |
| Profil | `GET /api/v1/me/profile` | `ProfileApiController` |
| Presensi | `GET /api/v1/attendance/today`; `POST /api/v1/attendance/punch`; `GET /api/v1/attendance/history`, `GET /api/v1/attendance/summary` | `AttendanceApiController` |
| Jadwal | `GET /api/v1/schedule/today`, `GET /api/v1/schedule`, `GET /api/v1/shifts`; `GET /api/v1/schedule-requests`; `POST /api/v1/schedule-requests`; `POST /api/v1/schedule-requests/{id}/cancel` | `ScheduleApiController` |
| Lembur | `GET /api/v1/overtimes`, `GET /api/v1/overtimes/policy`; `POST /api/v1/overtimes`; `POST /api/v1/overtimes/{id}/cancel` | `OvertimeApiController` |
| Cuti | `GET /api/v1/leave/types`, `GET /api/v1/leave/balances`, `GET /api/v1/leaves`; `GET /api/v1/leaves/{id}`; `POST /api/v1/leaves`; `POST /api/v1/leaves/{id}/cancel` | `LeaveApiController` |
| Payroll | `GET /api/v1/payslips`, `GET /api/v1/payslips/{id}`, `GET /api/v1/payslips/{id}/pdf` | `PayrollApiController` |
| Klaim | `GET /api/v1/claim-categories`, `GET /api/v1/claims`; `POST /api/v1/claims`; `GET /api/v1/claims/{id}`; `POST /api/v1/claims/{id}/cancel` | `ClaimApiController` |
| Pajak | `GET /api/v1/tax-slips`, `GET /api/v1/tax-slips/{year}`, `GET /api/v1/tax-slips/{year}/pdf` | `TaxApiController` |
| Pengumuman | `GET /api/v1/announcements`, `GET /api/v1/announcements/{id}`, `POST /api/v1/announcements/{id}/read` | `AnnouncementApiController` |
| Notifikasi | `GET /api/v1/notifications`; `POST /api/v1/notifications/read-all`, `POST /api/v1/notifications/{id}/read` | `NotificationApiController` |
| Persetujuan | `GET /api/v1/approvals`, `GET /api/v1/approvals/summary`, `POST /api/v1/approvals/{type}/{id}/decide` | `ApprovalApiController` |

Berkas pendukung: `app/Core/ApiController.php`, `app/Core/ApiAuth.php`,
`app/Core/ApiRequest.php`, `app/Controllers/Api/V1/*`, `docs/openapi.yaml`,
`docs/android-app-prompt.md`.

## 6. Catatan

* Semua route API memakai `auth => false` di router; autentikasi dilakukan
  `ApiAuth` di dalam controller, sehingga tidak memakai sesi/cookie dan CSRF
  dimatikan.
* Endpoint unduhan menerima **bearer token** atau **URL bertanda tangan**;
  tanda tangan HMAC berumur 5 menit dan terikat `resource + id + employee`.
* Perubahan status tetap melalui `ApprovalEngine`, jadi aturan (tidak boleh
  menyetujui pengajuan sendiri, tahapan atasan → HR) identik dengan web.
* Kontrak lengkap tersedia di `docs/openapi.yaml`; prompt pembuatan aplikasi
  Kotlin ada di `docs/android-app-prompt.md`.
