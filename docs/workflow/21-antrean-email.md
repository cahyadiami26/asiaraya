# 21 — Antrean Email (Email Queue)

> "Kotak keluar" HRIS: setiap email otomatis dicatat lebih dulu, dikirim
> segera bila memungkinkan, dicoba ulang bila gagal, dan dapat diaudit,
> dikirim ulang, atau dibatalkan oleh HR.

## 1. Fungsi & kegunaan

Email adalah kanal resmi HRIS untuk hal-hal yang tidak boleh terlewat:
pengingat kontrak berakhir, dokumen kedaluwarsa, sisa cuti, pengumuman
terjadwal, dan tautan reset kata sandi. Antrean email ada agar kanal itu
**tidak rapuh**.

| Masalah tanpa antrean | Yang diselesaikan antrean |
|-----------------------|---------------------------|
| SMTP mati/limit → email hilang tanpa jejak, tidak ada yang tahu | Email tersimpan `PENDING` + pesan error; dikirim ulang otomatis (3 percobaan, jeda 5/10/15 menit) atau manual |
| "Karyawan bilang tidak pernah dapat email pengingat kontrak" | Ada bukti baris: penerima, subjek, template, waktu kirim (`sent_at`), driver, jumlah percobaan |
| Email terlanjur dikirim ke orang yang sudah resign / salah alamat | Status `PENDING` dapat **dibatalkan** sebelum terkirim (audit `mail.cancel`) |
| HR harus menebak apakah isi email sudah benar | Pratinjau HTML asli + versi teks pada `/mail/{id}`, tanpa mengirim apa pun |
| Uji coba go-live berisiko mengirim ke karyawan sungguhan | Driver `log` mencatat seluruh isi ke `storage/logs/mail-outbox.log` tanpa mengirim |
| Permintaan web menggantung menunggu server SMTP | Antrean dapat diproses borongan oleh job `email_queue` (batch 1–200) |
| Riwayat email menumpuk selamanya | **Prune** email `SENT`/`CANCELLED` lebih tua dari N hari (7–730) |
| Pengingat yang sama terkirim berulang | Dedup bawaan: pengingat kontrak 60 hari, dokumen 45 hari, cuti 30 hari, pengumuman 30 hari |

Ringkasnya, antrean email memberi empat hal: **keandalan** (retry + status),
**akuntabilitas** (jejak audit per penerima), **kendali** (batal/kirim ulang/
bersihkan), dan **keamanan uji coba** (driver log).

## 2. Komponen & data

* **Tabel `email_queue`** — satu baris per email:
  `recipient_email`, `recipient_name`, `employee_id`, `template`, `subject`,
  `body_html`, `body_text`, `status`, `attempts`, `driver`, `error`,
  `next_attempt_at`, `sent_at`, `created_at`.
  Index: `(status, next_attempt_at)`, `employee_id`, `created_at`.
* **Driver** (`mail_driver`): `smtp` (socket langsung, SSL/STARTTLS, AUTH
  LOGIN/PLAIN), `mail` (fungsi `mail()` PHP/sendmail), `log` (mode uji, tidak
  mengirim).
* **Batas percobaan** `Mailer::MAX_ATTEMPTS = 3`; setelah itu status `FAILED`
  (masih bisa dikirim ulang manual).
* **Template** di `app/Views/mail/`: `layout` (kop/footer), `generic`,
  `contract_expiry`, `leave_expiry`, `announcement`, `password_reset`, `test`.
  Template tak dikenal otomatis jatuh ke `generic`.
* **Log berkas**: setiap upaya menulis satu baris ke
  `storage/logs/mail.log`; pada driver `log`, HTML lengkap disimpan di
  `storage/logs/mail-outbox.log`.

## 3. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Operator | Lihat antrean & detail, kirim ulang, batalkan, proses sekarang, prune, atur SMTP & kirim email uji |
| Sistem (aplikasi) | Penulis antrean | `Mailer::queue()/sendTemplate()/toEmployee()` dari reset sandi, cron, dan modul lain |
| FIN / Karyawan | Penerima | Tidak punya halaman antrean; hanya menerima email di kotak masuknya |
| Penjadwal (cron) | Pemroses | Job `email_queue` mengirim baris `PENDING` yang jatuh tempo |

## 4. Workflow

### 4.1 Siklus hidup satu email

```text
Modul memanggil Mailer::sendTemplate()/toEmployee()
        │  validasi alamat → baris email_queue (status PENDING, attempts 0)
        ▼
   sendNow = true (bawaan)? ──ya──► deliver() langsung (request menunggu SMTP)
        │ tidak                              │
        │                                    ├─ berhasil ─► SENT (driver, sent_at)
        │                                    └─ gagal ────► attempts+1
        │                                                     │
        │                            attempts < 3 ───────────┤
        │                            → PENDING + next_attempt_at (5 × attempts menit)
        │                            attempts = 3 ───────────┤
        │                            → FAILED (error disimpan)
        ▼
   job email_queue / tombol "Kirim Antrean Sekarang"
        │  ambil PENDING yang next_attempt_at ≤ sekarang, urut id, maksimal batch
        ▼
   proses ulang → SENT / PENDING / FAILED
```

1. **Masuk antrean** — penerima divalidasi (`filter_var`); alamat tidak valid
   atau tabel belum ada → tidak ada baris dibuat (fungsi mengembalikan `0`).
2. **Kirim segera** — bawaan `sendNow = true`, jadi sebagian besar email
   dikirim saat itu juga; bila server SMTP lambat, permintaan web menunggu
   sampai `mail_timeout` (5–120 detik, bawaan 15).
3. **Gagal → dijadwalkan ulang** — percobaan ke-1 mundur 5 menit, ke-2 10
   menit; percobaan ke-3 yang gagal menandai `FAILED` permanen.
4. **Email dinonaktifkan** (`mail_enabled` mati) — pengiriman mengembalikan
   `SKIPPED`; baris **tetap `PENDING`** (tidak dianggap terkirim) sehingga bisa
   dilanjutkan setelah email diaktifkan.
5. **Pemrosesan borongan** — job/cron mengambil maksimal `cron_email_batch`
   (1–200, bawaan 25) email per eksekusi.

### 4.2 Menyiapkan pengiriman (HR)

```text
[HR] /settings?tab=email
        │
        ├─ pilih driver (SMTP / mail() / log) + aktifkan "Kirim email otomatis"
        ├─ isi host, port, enkripsi (TLS 587 / SSL 465 / none), username, password
        ├─ atur From (email & nama), Reply-To, timeout
        ├─ [Simpan] → validasi → Settings::many(..., 'email') + audit settings.email
        └─ [Kirim email uji] → POST /settings/email/test → hasil nyata ditampilkan
```

1. Password SMTP **tidak pernah ditampilkan ulang**; hanya ditimpa bila field
   diisi, dan ada opsi menghapus password tersimpan.
2. Driver `smtp` mewajibkan host & port valid; enkripsi harus `tls`, `ssl`,
   atau `none`.
3. **Selalu** kirim email uji sebelum go-live; pesan galat server ditampilkan
   apa adanya agar mudah didiagnosis.
4. Selama uji coba gunakan driver `log`: alur, template, dan tautan tetap bisa
   diperiksa tanpa mengirim ke karyawan.

### 4.3 Operasi harian di `/mail` (HR)

```text
[HR] /mail
   ├─ kartu ringkasan: menunggu / terkirim / gagal / hari ini / driver aktif
   ├─ filter status + template + pencarian (penerima, subjek) + pagination 20
   ├─ [Kirim Antrean Sekarang] → POST /mail/process (job email_queue, sumber MANUAL)
   ├─ per baris: [Lihat] /mail/{id} · [Kirim ulang] · [Batalkan]
   └─ [Bersihkan Riwayat] → POST /mail/prune (SENT/CANCELLED > N hari)
```

* **Kirim ulang** — hanya untuk baris yang belum `SENT`; sistem mereset
  `attempts = 0`, menghapus error, lalu mengirim langsung. Hasilnya
  dilaporkan: terkirim, tetap menunggu (email dimatikan), atau gagal.
* **Batalkan** — hanya baris `PENDING`; status menjadi `CANCELLED` dengan
  alasan "Dibatalkan manual oleh HR" dan tidak akan pernah dikirim.
* **Bersihkan riwayat** — hanya `SENT`/`CANCELLED`; `PENDING` dan `FAILED`
  tidak ikut terhapus agar tidak ada email yang hilang tanpa jejak.
* **Detail `/mail/{id}`** — pratinjau HTML dalam iframe ber-sandbox, versi teks
  fallback, jumlah percobaan, driver, dan pesan error terakhir.

### 4.4 Email yang dikirim sistem

| Pemicu | Template | Penerima | Sifat |
|--------|----------|----------|-------|
| Lupa kata sandi (`/forgot-password`) | `password_reset` | Pemohon | Sekali pakai, tautan berlaku 60 menit; tautan juga dicatat di `storage/logs/password-reset.log` |
| Job `contract_expiry` | `contract_expiry` | Karyawan aktif | Pengingat kontrak H-N (bawaan 30 hari), dedup 60 hari |
| Job `document_expiry` | `generic` | Karyawan aktif | Dokumen kedaluwarsa H-N (bawaan 60 hari), dedup 45 hari, dokumen rahasia dilewati |
| Job `leave_expiry` | `leave_expiry` | Karyawan aktif | Sisa cuti menjelang 31 Desember (bawaan H-70), dedup 30 hari |
| Job `announcement_publish` | `announcement` | Audiens pengumuman | Opsional (`cron_announcement_email`), dedup 30 hari |

Semua tautan aksi dibuat **absolut** memakai `app_url` (atau host request),
dan setiap email menyertakan versi teks sederhana sebagai fallback klien.

## 5. Status & aturan

| Status | Arti | Tindakan berikutnya |
|--------|------|---------------------|
| `PENDING` | Menunggu dikirim / menunggu jadwal percobaan ulang | Cron `email_queue` atau tombol proses |
| `SENT` | Terkirim oleh driver aktif (`sent_at` terisi) | Hanya bisa dilihat; prune setelah retensi |
| `FAILED` | Gagal setelah 3 percobaan | Kirim ulang manual setelah masalah SMTP diperbaiki |
| `CANCELLED` | Dibatalkan HR / penerima tidak aktif | Prune setelah retensi |

**Jadwal percobaan ulang** (`next_attempt_at` = waktu gagal + 5 menit ×
percobaan): percobaan 1 → +5 menit, percobaan 2 → +10 menit, percobaan 3 →
`FAILED`.

**Kunci pengaturan**: `mail_enabled`, `mail_driver`, `mail_host`, `mail_port`,
`mail_encryption`, `mail_username`, `mail_password`, `mail_from_email`,
`mail_from_name`, `mail_reply_to`, `mail_timeout`, serta `cron_email_batch`
(jumlah per pemrosesan).

## 6. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /mail` | `MailController@index` | Antrean + statistik + filter |
| `GET /mail/{id}` | `MailController@show` | Detail, pratinjau HTML & teks |
| `POST /mail/process` | `MailController@process` | Kirim antrean sekarang (job `email_queue`) |
| `POST /mail/{id}/retry` | `MailController@retry` | Kirim ulang satu email |
| `POST /mail/{id}/cancel` | `MailController@cancel` | Batalkan email `PENDING` |
| `POST /mail/prune` | `MailController@prune` | Hapus riwayat `SENT`/`CANCELLED` |
| `GET /settings?tab=email` | `SettingsController@index` | Tab konfigurasi email |
| `POST /settings/email` | `SettingsController@saveEmail` | Simpan konfigurasi SMTP |
| `POST /settings/email/test` | `SettingsController@testEmail` | Kirim email uji (tidak lewat antrean) |

Berkas pendukung: `app/Core/Mailer.php` (antrean, driver SMTP/mail/log,
template, statistik), `app/Models/MailModel.php` (query & aksi antrean),
`app/Views/mail/*` (antrean, detail, template), tabel `email_queue`.

## 7. Catatan & penanganan masalah

* **Pesan galat yang sering muncul** (tersimpan di kolom `error`):
  * "Host SMTP belum diisi pada Pengaturan → Email." → lengkapi konfigurasi.
  * "Server menolak STARTTLS…" → ganti enkripsi ke `ssl` (port 465) atau
    `none` bila server internal.
  * "Password SMTP ditolak." / "Autentikasi SMTP gagal." → periksa
    username/app password; Gmail & Outlook 365 memerlukan app password.
  * "Penerima … ditolak server." → alamat tidak valid di sisi penyedia.
* **Email uji tidak masuk antrean** — `sendTest()` memakai `sendRaw()` langsung
  sehingga hasilnya segera terlihat; kegagalan uji tidak mengotori riwayat.
* **Jangan matikan `mail_enabled` lama-lama** bila memakai driver `log`:
  baris `PENDING` akan menumpuk, tetapi tidak ada yang hilang.
* `Mailer::cancelPendingForEmployee()` tersedia untuk membatalkan seluruh email
  tertunda milik satu karyawan (dipakai saat penerima dinonaktifkan); saat ini
  belum dipanggil otomatis oleh modul lain, jadi pembatalan dilakukan manual
  dari `/mail`.
* Antrean berisi isi email (HTML) — prune berkala penting agar tabel tidak
  membengkak; retensi default 90 hari.
