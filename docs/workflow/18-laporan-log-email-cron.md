# 18 — Laporan, Log Aktivitas, Email & Penjadwal (Cron)

> Empat perkakas operasional yang menyatukan data: laporan periodik siap ekspor,
> jejak audit, antrean email, dan pekerjaan terjadwal otomatis.

## 1. Fungsi

### Laporan (`/reports`)

* Empat jenis rekap per bulan: **Presensi**, **Lembur**, **Cuti**, **Payroll**.
* Filter unit, departemen, status, kata kunci, dan bulan.
* **Ekspor CSV** (`/reports/{type}/csv`) dan **PDF** (`/reports/{type}/pdf`).
* Halaman khusus rekap payroll (`/reports/payroll`).

### Log aktivitas (`/logs`)

* Jejak audit semua aksi penting: login, kata sandi, payroll, komponen gaji,
  pengaturan, data karyawan, presensi, lembur, cuti, jadwal, organisasi.
* Filter grup/pegawai/rentang tanggal/kata kunci, statistik, dan paginasi.
* **Ekspor CSV** dan **purge** berdasarkan retensi (`audit_retention_days`,
  bawaan 180 hari).

### Email (`/mail`)

* Antrean email transaksional (`email_queue`): status, template, penerima,
  percobaan kirim, dan pesan galat.
* Proses antrean manual (`/mail/process`), **coba ulang** yang gagal, batalkan
  yang masih `PENDING`, dan **prune** email lama.
* Uji koneksi SMTP dari **Pengaturan → Email** (`/settings/email/test`).

### Penjadwal (`/scheduler`)

* Daftar pekerjaan otomatis + interval, tombol **jalankan semua** / per job.
* Pengaturan aktif/nonaktif per job, rahasia endpoint cron, dan prune riwayat.
* Endpoint HTTP `GET /cron?token=…` untuk cron eksternal (tanpa login).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pemilik operasional | Semua laporan, log, antrean email, dan penjadwal |
| FIN | Pemakai laporan | Laporan presensi/lembur/cuti/payroll (tanpa log, email, penjadwal) |
| Karyawan | — | Tidak punya akses; data pribadinya lewat menu *Layanan Mandiri* |
| Cron eksternal | Pemanggil | `GET /cron` dengan token rahasia |

## 3. Workflow

### 3.1 Laporan

```text
[HR/FIN] /reports → pilih jenis + bulan + filter
        │
        ├─ tabel rekap di layar (total & rincian per karyawan)
        ├─ [CSV] /reports/{type}/csv → unduh untuk Excel
        └─ [PDF] /reports/{type}/pdf → cetak/arsip
```

### 3.2 Log aktivitas

```text
Aksi pengguna → Audit::log(group, entity, id, meta) → activity_logs
        │
[HR] /logs → filter grup/tanggal/pegawai → ekspor CSV
        │
        └─ /logs/purge → hapus log lebih tua dari audit_retention_days
```

### 3.3 Email

```text
Aplikasi memanggil Mailer::queue()/sendTemplate()
        ▼
   email_queue: PENDING ──(process / cron email_queue)──► SENT
        │                                    └─(gagal)──► FAILED ──[Coba ulang]──► PENDING
        └─[Batalkan]──► CANCELLED
        ▼
   [Prune] hapus SENT/CANCELLED lebih tua dari N hari
```

### 3.4 Penjadwal

```text
[HR] /scheduler → aktifkan job, atur interval, salin token/URL cron
        │
cron eksternal memanggil GET /cron?token=… (atau [Jalankan] di UI)
        ▼
 Scheduler menjalankan job yang sudah jatuh tempo → hasil + durasi dicatat
        ▼
 riwayat job tampil di /scheduler; prune untuk membersihkan riwayat lama
```

## 4. Status

| Objek | Status | Arti |
|-------|--------|------|
| Email | `PENDING` | Menunggu dikirim |
| | `SENT` | Terkirim |
| | `FAILED` | Gagal (dapat dicoba ulang) |
| | `CANCELLED` | Dibatalkan HR sebelum terkirim |
| Job | aktif / non-aktif | Diatur per job di `/scheduler` |
| Log | permanen s.d. retensi | Dihapus manual atau oleh retensi |

**Job bawaan**: `email_queue` (5 menit), `contract_expiry` (720 menit),
`document_expiry` (1440), `leave_expiry` (1440), `announcement_publish` (15).

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /reports`, `GET /reports/payroll` | `ReportController@index/payroll` | Rekap |
| `GET /reports/{type}/csv`, `GET /reports/{type}/pdf` | `ReportController@csv/pdf` | Ekspor |
| `GET /logs`, `GET /logs/export`, `POST /logs/purge` | `LogController@index/export/purge` | Audit |
| `GET /mail`, `GET /mail/{id}` | `MailController@index/show` | Antrean email |
| `POST /mail/process`, `POST /mail/prune` | `MailController@process/prune` | Kirim & bersihkan |
| `POST /mail/{id}/retry`, `POST /mail/{id}/cancel` | `MailController@retry/cancel` | Aksi per email |
| `POST /settings/email`, `POST /settings/email/test` | `SettingsController@saveEmail/testEmail` | Konfigurasi SMTP |
| `GET /scheduler`, `POST /scheduler/run-all`, `POST /scheduler/run/{job}` | `SchedulerController@index/runAll/run` | Penjadwal |
| `POST /scheduler/settings`, `POST /scheduler/secret`, `POST /scheduler/prune` | `SchedulerController@saveSettings/rotateSecret/prune` | Pengaturan job |
| `GET /cron` | `SchedulerController@http` | Endpoint cron bertoken |

Berkas pendukung: `app/Core/Mailer.php`, `app/Core/Scheduler.php`,
`app/Core/Audit.php`, `app/Models/ReportModel.php`, `app/Models/MailModel.php`,
`app/Models/SchedulerModel.php`.

## 6. Catatan

* Email hanya benar-benar terkirim bila `mail_enabled` aktif dan driver
  `smtp`/`mail`; driver `log` mencatat isi pesan tanpa mengirim.
* Endpoint `/cron` wajib memakai token yang dibuat di `/scheduler`: token belum
  dibuat → 403, token salah/kosong → 401, dan tidak ada job yang dijalankan.
* Purge log/email bersifat permanen — pastikan retensi sesuai kebijakan
  penyimpanan perusahaan.
* Semua aksi ekspor/purge tercatat di log audit.
