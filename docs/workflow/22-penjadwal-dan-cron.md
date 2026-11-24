# 22 — Penjadwal & Cron

> Mesin pekerjaan terjadwal: mengirim antrean email, mengingatkan kontrak
> berakhir, dokumen kedaluwarsa, sisa cuti, dan menerbitkan pengumuman
> terjadwal — tanpa harus ada orang yang menekan tombol.

## 1. Fungsi & kegunaan

Banyak pekerjaan HR bersifat **rutin dan berbasis tanggal**, bukan berdasarkan
permintaan pengguna. Tanpa penjadwal, semuanya bergantung pada ingatan staf:

| Pekerjaan | Bila tidak dijadwalkan | Dengan penjadwal |
|-----------|------------------------|------------------|
| Kontrak PKWT berakhir | Diketahui setelah lewat; perpanjangan terlambat, status `EXPIRED` | Pengingat H-30 (dapat diatur H-1…H-180) ke karyawan + notifikasi lonceng |
| Dokumen legal (KTP, paspor, SIM, sertifikat) | Kedaluwarsa tanpa ada yang tahu | Pengingat H-60; dokumen rahasia tidak dikirim lewat email |
| Sisa cuti akhir tahun | Hangus tanpa pemberitahuan | Pengingat menjelang 31 Desember (bawaan H-70) berisi rincian jatah/carry/terpakai |
| Pengumuman terjadwal | Harus diumumkan manual pada jam tayang | Notifikasi lonceng (opsional email) tepat saat `publish_at` tiba |
| Antrean email menumpuk | Email gagal tidak pernah dicoba lagi | Batch 25 email tiap 5 menit sampai habis |

Manfaat utamanya:

1. **Proaktif, bukan reaktif** — karyawan & HR diberi tahu sebelum tenggat,
   bukan sesudah.
2. **Tiga cara menjalankan** — CLI crontab, HTTP bertoken, atau sapuan otomatis
   saat halaman dibuka; tetap berjalan di shared hosting tanpa akses cron.
3. **Dapat diaudit** — setiap eksekusi tercatat di `cron_runs` (job, sumber,
   jumlah data, durasi, pesan) dan di log aktivitas (`cron.run`).
4. **Aman diulang** — dedup per penerima/subjek, lock agar tidak dobel,
   hanya karyawan `ACTIVE`, dan dokumen rahasia tidak dikirim via email.
5. **Dapat dikendalikan** — aktif/nonaktif per job, jendela pengingat, ukuran
   batch, retensi riwayat, dan rotasi token HTTP.

## 2. Komponen & data

* **Definisi job** — `Scheduler::JOBS` (kunci, label, deskripsi, `interval`
  dalam menit, kunci toggle, ikon).
* **Tabel `cron_runs`** — `job`, `status`, `source`, `processed`, `message`,
  `duration_ms`, `started_at`, `finished_at`; index `(job, started_at)`.
* **Kunci pengaturan** — `cron_job_email_queue`, `cron_job_contract`,
  `cron_job_document`, `cron_job_leave`, `cron_job_announcement`, `cron_auto`,
  `cron_contract_lead_days`, `cron_leave_lead_days`, `cron_document_lead_days`,
  `cron_email_batch`, `cron_announcement_email`, `cron_prune_days`,
  `cron_secret`.
* **Entry point** — kelas `App\Core\Scheduler`, skrip `public/cron.php`, route
  `GET /cron`, dan `Scheduler::autoRun()` yang dipanggil bootstrap.
* **Zona waktu** — mengikuti `config('timezone')`, bawaan **Asia/Jakarta**.

## 3. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Operator | Lihat status & riwayat, jalankan per job/semua, ubah pengaturan, rotasi token, prune riwayat |
| Sistem (bootstrap) | Pelaksana otomatis | Sapuan `AUTO` pada request GET bila `cron_auto` aktif |
| Cron server / layanan eksternal | Pemanggil | `php public/cron.php [job]` atau `GET /cron?token=…` |
| FIN / Karyawan | Penerima manfaat | Menerima email & notifikasi; tidak punya akses halaman |

## 4. Workflow

### 4.1 Tiga jalur eksekusi

```text
                  ┌──────────────────────── Scheduler::run($job, $source) ───────────────────────┐
                  │                                                                              │
1) CRON SERVER    │  php public/cron.php [job]        source = CRON                              │
2) HTTP TOKEN     │  GET /cron?token=…&job=…          source = CRON                              │
                  │  (atau public/cron.php?token=…)                                              │
3) UI MANUAL      │  POST /scheduler/run/{job} · /scheduler/run-all    source = MANUAL           │
4) AUTO SWEEP     │  bootstrap → autoRun() pada GET, maks. 1×/menit    source = AUTO            │
                  └──────────────────────────────────┬───────────────────────────────────────────┘
                                                     ▼
                    cron_runs: RUNNING → OK / ERROR (processed, message, duration_ms)
                                                     ▼
                    Audit::log('cron.run', …) + tampil di riwayat /scheduler
```

**Perbedaan penting antar jalur**

| Jalur | Kapan berjalan | Job mana yang dijalankan |
|-------|----------------|--------------------------|
| CLI `public/cron.php` | Sesuai jadwal crontab server | Semua job **aktif**, atau satu job bila argumen diberikan |
| HTTP `/cron` | Dipanggil layanan cron eksternal | Sama seperti CLI; `&format=text` untuk respons teks |
| Manual di UI | HR menekan tombol | Satu job (walau toggle-nya mati) atau semua job aktif |
| Auto sweep | Request GET biasa, bila job sudah lewat interval | Hanya job aktif yang sudah jatuh tempo (`due()`) |

> `interval` pada `Scheduler::JOBS` hanya dipakai mode **AUTO**. Pada mode cron
> server, jadwal ditentukan crontab; pada mode manual, tombol yang menentukan.

### 4.2 Job bawaan

| Job | Yang dikerjakan | Interval AUTO | Pengaturan |
|-----|-----------------|---------------|-----------|
| `email_queue` | Kirim email `PENDING` yang jatuh tempo (maks. `cron_email_batch`) | 5 menit | `cron_email_batch` 1–200 (bawaan 25) |
| `contract_expiry` | Email + notifikasi lonceng untuk kontrak berakhir dalam H-N (hanya `flag = ACTIVE`) | 720 menit | `cron_contract_lead_days` 1–180 (bawaan 30), dedup 60 hari |
| `document_expiry` | Email + lonceng untuk dokumen kedaluwarsa H-N; melewati dokumen `REJECTED` & rahasia | 1440 menit | `cron_document_lead_days` 7–180 (bawaan 60), dedup 45 hari |
| `leave_expiry` | Ringkasan sisa cuti per karyawan menjelang 31 Des, hanya bila masih ada sisa | 1440 menit | `cron_leave_lead_days` 1–180 (bawaan 70), dedup 30 hari |
| `announcement_publish` | Notifikasi lonceng (opsional email) untuk pengumuman `PUBLISHED` yang `publish_at`-nya tiba | 15 menit | `cron_announcement_email` (bawaan mati), dedup 30 hari |

Catatan perilaku:

* `leave_expiry` langsung selesai tanpa mengirim apa pun bila belum masuk
  jendela H-N (pesan: "Belum masuk jendela pengingat: … hari menuju 31 Des").
* `announcement_publish` hanya memproses pengumuman dengan `notify = 1` dalam
  rentang `publish_at` tiga hari terakhir dan belum kedaluwarsa; idempoten
  karena keberadaan baris `notifications` dengan tautan pengumuman dipakai
  sebagai penanda "sudah dikirim".
* Semua job mengembalikan ringkasan teks (jumlah diproses, dikirim, dilewati)
  yang ditampilkan sebagai flash di UI dan tersimpan di `cron_runs.message`.

### 4.3 Mengatur penjadwal (HR)

```text
[HR] /scheduler
   ├─ kartu statistik: total eksekusi · hari ini · gagal · terakhir
   ├─ per job: status aktif, interval, eksekusi terakhir, [Jalankan]
   ├─ [Jalankan Semua Job Aktif]
   ├─ Pengaturan: toggle per job · cron_auto · jendela H-N · batch email ·
   │              email pengumuman · retensi riwayat  → POST /scheduler/settings
   ├─ Token cron: [Buat/Rotasi Token] → POST /scheduler/secret
   ├─ Contoh crontab siap salin (path aplikasi + alternatif curl)
   ├─ [Bersihkan Riwayat] → POST /scheduler/prune (7–730 hari)
   └─ Riwayat 15 eksekusi terakhir (job, sumber, status, data, durasi, pesan)
```

1. Aktifkan hanya job yang relevan; job non-aktif dilewati oleh mode AUTO dan
   "Jalankan Semua", tetapi tetap bisa diuji lewat tombol per job.
2. Sesuaikan jendela pengingat: terlalu pendek → karyawan telat bersiap;
   terlalu panjang → pengingat datang terlalu dini.
3. Bila **cron server sudah dipasang**, matikan `cron_auto` agar tidak ada
   sapuan ganda (dedup tetap mencegah email dobel, tetapi lebih hemat).
4. Setelah mengganti token, perbarui URL di crontab/layanan cron eksternal.

### 4.4 Keamanan endpoint HTTP

```text
GET /cron?token=…                 (atau header X-Cron-Token)
   │ cron_secret kosong?         → 403 "Token cron belum dibuat…"
   │ token kosong / tidak cocok? → 401 "Token cron tidak valid."
   │ job tidak dikenal?          → 404
   ▼ token cocok (hash_equals)
   jalankan job / semua job aktif → JSON {ok, time, results}
```

* Token dibuat acak 160-bit (`bin2hex(random_bytes(20))`) dan dibandingkan
  dengan `hash_equals` (anti timing attack).
* `public/cron.php?token=…` memakai aturan sama; tanpa token yang cocok → 403.
* Token dapat dirotasi kapan saja; rotasi tercatat sebagai `cron.secret`.
* Endpoint ini **tanpa login** — jangan pernah membagikan URL bertoken.

### 4.5 Pembersihan riwayat

`POST /scheduler/prune` menghapus `cron_runs` lebih tua dari N hari
(7–730, bawaan 90). Email lama dibersihkan terpisah di `/mail/prune`.

## 5. Status

| Objek | Status | Arti |
|-------|--------|------|
| Eksekusi (`cron_runs.status`) | `RUNNING` | Sedang berjalan |
| | `OK` | Selesai (mungkin 0 data) |
| | `ERROR` | Gagal — pesan galat disimpan (maks. 480 karakter) |
| Sumber (`cron_runs.source`) | `MANUAL` / `CRON` / `AUTO` | Dari UI / cron server-HTTP / sapuan otomatis |
| Job | aktif / non-aktif | Toggle per job di `/scheduler` |
| Token | ada / belum dibuat | `cron_secret`; kosong berarti endpoint HTTP menolak semua panggilan |

## 6. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /scheduler` | `SchedulerController@index` | Status job, riwayat, pengaturan, contoh crontab |
| `POST /scheduler/run-all` | `SchedulerController@runAll` | Jalankan semua job aktif |
| `POST /scheduler/run/{job}` | `SchedulerController@run` | Jalankan satu job |
| `POST /scheduler/settings` | `SchedulerController@saveSettings` | Simpan toggle, jendela H-N, batch, retensi |
| `POST /scheduler/secret` | `SchedulerController@rotateSecret` | Buat/rotasi token cron |
| `POST /scheduler/prune` | `SchedulerController@prune` | Hapus riwayat eksekusi lama |
| `GET /cron` | `SchedulerController@http` | Endpoint cron bertoken (tanpa login) |
| — (CLI/HTTP) | `public/cron.php` | `php public/cron.php [job]`; dukung `?token=` |

Berkas pendukung: `app/Core/Scheduler.php` (definisi job, eksekusi, riwayat,
lock, dedup), `app/Models/SchedulerModel.php` (data halaman & pengaturan),
`app/Controllers/SchedulerController.php`, `app/Views/scheduler/*`,
`public/cron.php`, tabel `cron_runs`.

## 7. Catatan

* **Idempoten**: pengingat kontrak (dedup 60 hari), dokumen (45), cuti (30),
  dan pengumuman (30) tidak dikirim dua kali untuk penerima & subjek yang sama.
* **Lock**: `storage/cache/cron.lock` mencegah dua request menjalankan sapuan
  bersamaan; sapuan AUTO juga dibatasi sekali per menit.
* **Aman untuk halaman**: `autoRun()` hanya pada request GET, dibungkus
  `try/catch` di bootstrap, dan tidak berjalan bila tabel `cron_runs` belum ada
  atau belum ada karyawan (masa instalasi).
* Pengingat hanya untuk karyawan `flag = ACTIVE`; dokumen bertanda rahasia dan
  dokumen `REJECTED` dilewati pada job `document_expiry`.
* Bila SMTP belum dikonfigurasi (driver `log`), job tetap berjalan dan email
  tetap tercatat di antrean — lihat [21-antrean-email.md](21-antrean-email.md).
* Mematikan semua job tidak menghentikan aplikasi; hanya membuat pekerjaan
  otomatis berhenti sampai diaktifkan kembali.
