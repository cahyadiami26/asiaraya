# 18 — Laporan & Log Aktivitas

> Dua perkakas yang mengubah data operasional menjadi informasi: laporan
> periodik siap ekspor dan jejak audit seluruh aksi pengguna.

> Nama berkas dipertahankan agar tautan lama tetap berlaku.

> Antrean email dibahas terpisah di
> [21-antrean-email.md](21-antrean-email.md); penjadwal otomatis (cron) di
> [22-penjadwal-dan-cron.md](22-penjadwal-dan-cron.md).

## 1. Fungsi

### Laporan (`/reports`)

* Empat jenis rekap per bulan: **Presensi**, **Lembur**, **Cuti**, **Payroll**.
* Filter unit, departemen, status, kata kunci, dan bulan.
* **Ekspor CSV** (`/reports/{type}/csv`) dan **PDF** (`/reports/{type}/pdf`).
* Halaman khusus rekap payroll (`/reports/payroll`).

### Log aktivitas (`/logs`)

* Jejak audit semua aksi penting: login, kata sandi, payroll, komponen gaji,
  pengaturan, data karyawan, presensi, lembur, cuti, jadwal, organisasi.
* Filter grup/pegawai/rentang tanggal/kata kunci, statistik, dan paginasi
  30 baris.
* **Ekspor CSV** dan **purge** berdasarkan retensi (`audit_retention_days`,
  bawaan 180 hari).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pemilik operasional | Semua jenis laporan + seluruh log aktivitas, ekspor, dan purge |
| FIN | Pemakai laporan | Laporan presensi/lembur/cuti/payroll (tanpa log) |
| Karyawan | — | Tidak punya akses; data pribadinya lewat menu *Layanan Mandiri* |

## 3. Workflow

### 3.1 Laporan

```text
[HR/FIN] /reports → pilih jenis + bulan + filter
        │
        ├─ tabel rekap di layar (total & rincian per karyawan)
        ├─ [CSV] /reports/{type}/csv → unduh untuk Excel
        └─ [PDF] /reports/{type}/pdf → cetak/arsip
```

1. Pilih tab jenis rekap (default **Presensi**) — daftar jenis diambil dari
   `ReportModel::TYPES`.
2. Atur bulan (format `YYYY-MM`) dan filter unit/departemen; filter yang tidak
   relevan dengan jenis laporan diabaikan.
3. Ekspor CSV/PDF memakai filter yang sedang aktif, jadi hasil unduhan sama
   dengan yang tampil di layar.

### 3.2 Log aktivitas

```text
Aksi pengguna → Audit::log(group, entity, id, meta) → activity_logs
        │
[HR] /logs → filter grup/tanggal/pegawai → ekspor CSV
        │
        └─ /logs/purge → hapus log lebih tua dari audit_retention_days
```

1. Setiap aksi penting menulis satu baris `activity_logs` (aktor, aksi, entitas,
   metadata).
2. Filter **grup** memakai daftar `ReportModel::logGroups()` (login, kata sandi,
   sesi, payroll, komponen, pengaturan, karyawan, presensi, lembur, cuti,
   jadwal, organisasi); nilai grup yang tidak dikenal diabaikan.
3. Purge bersifat permanen dan tercatat sebagai aksi `log.purge`.

## 4. Status

Modul ini tidak punya status domain — hanya data rekap dan baris log. Yang
berlaku:

| Objek | Aturan |
|-------|--------|
| Laporan | Dihitung ulang setiap kali dibuka (tidak disimpan) |
| Log | Permanen sampai dihapus manual / melewati retensi |
| Retensi | `audit_retention_days` (bawaan 180 hari), ditampilkan di halaman log |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /reports`, `GET /reports/payroll` | `ReportController@index/payroll` | Rekap |
| `GET /reports/{type}/csv`, `GET /reports/{type}/pdf` | `ReportController@csv/pdf` | Ekspor |
| `GET /logs`, `GET /logs/export`, `POST /logs/purge` | `LogController@index/export/purge` | Audit |

Berkas pendukung: `app/Core/Audit.php`, `app/Models/ReportModel.php`,
`app/Views/reports/*`, `app/Views/logs/*`, tabel `activity_logs`.

## 6. Catatan

* Rekap payroll **mengecualikan periode `DRAFT`** — periode yang sudah
  dikalkulasi (`CALCULATED`) tetap muncul, sehingga angka bisa berubah sampai
  periode final; gunakan status periode sebagai penanda versi data.
* Purge log bersifat permanen — pastikan retensi sesuai kebijakan penyimpanan
  perusahaan.
* Semua aksi ekspor/purge tercatat di log audit.
* Email otomatis (pengingat kontrak/cuti/dokumen, pengumuman, reset sandi)
  tidak dikirim langsung: lihat [21-antrean-email.md](21-antrean-email.md).
