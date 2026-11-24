# 11 — Pengumuman & Notifikasi

> Papan pengumuman bertarget, penjadwalan terbit, pelacakan dibaca, dan pusat
> notifikasi in-app (lonceng + halaman notifikasi).

## 1. Fungsi

* **Pengumuman** dengan judul, isi, lampiran, masa tayang, dan **pin**.
* **Target audiens berlapis**: semua karyawan, unit bisnis, departemen, jabatan,
  role, atau karyawan tertentu (dapat dikombinasikan).
* **Penjadwalan terbit** (`publish_at`) dan pengarsipan otomatis/manual.
* **Pelacakan dibaca** per karyawan (read receipts).
* **Notifikasi in-app**: dikirim otomatis oleh modul lain (approval, payroll,
  kontrak, dokumen, rekrutmen, offboarding) dengan tautan langsung.
* **Pusat notifikasi**: halaman `/notifications`, lonceng topbar (polling
  `/notifications/latest`), tandai dibaca (satuan/semua).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Penulis & pengelola | Buat, ubah, terbitkan, arsipkan, pin, lihat statistik dibaca |
| FIN | Penerima | Baca pengumuman sesuai audiens, kelola notifikasi sendiri |
| Karyawan | Penerima | Baca pengumuman sesuai audiens, kelola notifikasi sendiri |
| Sistem (cron) | Penerbit terjadwal | Menerbitkan pengumuman saat `publish_at` tiba |

## 3. Workflow

### 3.1 Menulis & menerbitkan pengumuman

```text
[HR] /announcements/create
   │  judul, isi, lampiran, pin
   │  audiens: semua | unit | departemen | jabatan | role | karyawan
   │  jadwal terbit (opsional)
   ▼
DRAFT ──(terbitkan)──► PUBLISHED ──(arsipkan)──► ARCHIVED
   │                       │
   │                       ├─ notifikasi in-app ke penerima
   │                       └─ tampil di /announcements (sesuai audiens)
   └─ jadwal terbit → cron announcement_publish menerbitkan otomatis
```

1. HR menyusun pengumuman; audiens dapat dipilih lebih dari satu jenis.
2. Bila `publish_at` diisi, status tetap `DRAFT` sampai cron/jadwal menerbitkan.
3. Saat `PUBLISHED`, sistem membuat notifikasi untuk setiap penerima yang
   relevan dan menandai pengumuman sebagai baru di lonceng.

### 3.2 Membaca

* Karyawan membuka `/announcements` → daftar sesuai audiens; membuka detail
  menandai `announcement_reads`.
* Tombol **Tandai semua dibaca** tersedia di halaman pengumuman.

### 3.3 Notifikasi

```text
Modul lain (approval, payroll, kontrak, dsb.)
        │  membuat baris notifications (employee_id, title, message, link, type)
        ▼
Lonceng topbar (polling /notifications/latest)
        │
        ├─ klik → tautan tujuan + tandai dibaca
        └─ /notifications → daftar lengkap, tandai semua dibaca
```

## 4. Status

| Objek | Status | Arti |
|-------|--------|------|
| Pengumuman | `DRAFT` | Belum terbit (bisa terjadwal) |
| | `PUBLISHED` | Terbit & tampil sesuai audiens |
| | `ARCHIVED` | Diarsipkan, tidak tampil di daftar aktif |
| Notifikasi | `read_at = NULL` | Belum dibaca |
| | `read_at` terisi | Sudah dibaca |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /announcements` | `AnnouncementController@index` | Daftar pengumuman (sesuai audiens) |
| `GET /announcements/manage` | `AnnouncementController@manage` | Konsol HR |
| `GET /announcements/create`, `POST /announcements` | `AnnouncementController@create/store` | Tulis pengumuman |
| `POST/PUT /announcements/{id}`; `POST/DELETE /announcements/{id}/delete` | `AnnouncementController@update/destroy` | Ubah/hapus |
| `POST /announcements/{id}/publish`, `POST /announcements/{id}/archive` | `AnnouncementController@publish/archive` | Terbitkan / arsipkan |
| `POST /announcements/{id}/read`, `/announcements/read-all` | `AnnouncementController@markRead/markAllRead` | Tandai dibaca |
| `GET /notifications` | `NotificationController@index` | Pusat notifikasi |
| `GET /notifications/latest` | `NotificationController@latest` | Polling lonceng (JSON) |
| `POST /notifications/read`, `POST /notifications/{id}/read` | `NotificationController@readAll/read` | Tandai dibaca |

Berkas pendukung: `app/Models/AnnouncementModel.php`,
`app/Models/NotificationModel.php`, `app/Views/announcements/*`,
`app/Views/notifications/index.php`, `public/assets/js/app.js` (lonceng).

## 6. Integrasi & catatan

* Notifikasi adalah kanal utama pemberitahuan approval; setiap tautan harus
  menuju halaman yang boleh dibuka penerimanya (sudah diaudit pada rilis 2.17.3).
* Email pengumuman bersifat **opsional**: aktifkan `cron_announcement_email`
  di halaman **Penjadwal & Cron** ([22-penjadwal-dan-cron.md](22-penjadwal-dan-cron.md)),
  lalu job `announcement_publish` mengirim template `announcement` ke audiens
  (maksimal sekali per karyawan per 30 hari untuk judul yang sama).
* Aplikasi Android membaca pengumuman & notifikasi lewat `/api/v1/announcements`
  dan endpoint notifikasi pada API.
