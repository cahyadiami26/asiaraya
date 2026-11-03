# 06 — Lembur (Overtime)

> Pengajuan lembur mandiri maupun penugasan lembur terjadwal, dengan approval
> dua tahap dan perhitungan kompensasi otomatis.

## 1. Fungsi

* **Pengajuan lembur** oleh karyawan (tanggal, jam rencana, alasan).
* **Lembur terjadwal** oleh HR (massal untuk unit/departemen/karyawan).
* Validasi kebijakan: status aktif lembur, minimal menit, maksimal jam/hari,
  dan batas nominal kompensasi.
* **Perhitungan kompensasi**: gaji pokok ÷ pembagi (default 173) × pengali
  (jam pertama, jam berikutnya, hari libur).
* **Approval dua tahap**: atasan langsung → HR.
* **Input jam aktual** oleh HR sebelum/setelah persetujuan final.
* Pembatalan oleh HR atau pemohon (sebelum diproses).
* Rekap, statistik, ekspor CSV, dan halaman **Persetujuan Lembur Tim** untuk
  atasan.
* Kompensasi lembur yang disetujui masuk ke payroll.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Karyawan | Pemohon | Ajukan lembur, batalkan pengajuan sendiri, lihat status |
| Atasan langsung | Approver tahap 1 | Setujui/tolak lembur anggota tim |
| HR | Pengelola & approver final | Buat lembur terjadwal, input jam aktual, putuskan tahap 2, atur kebijakan, ekspor |
| FIN | Pengguna data | Melihat kompensasi pada payroll & laporan |

## 3. Workflow

### 3.1 Pengajuan mandiri

```text
[Karyawan] /me/overtime → isi tanggal, jam mulai/selesai, alasan
        │
        ▼
    PENDING  ──(tolak atasan)──► REJECTED
        │
        └─(setujui atasan)──► MANAGER_APPROVED ──(tolak HR)──► REJECTED
                                    │
                                    └─(setujui HR)──► APPROVED
                                                          │
                                          hitung kompensasi (jam × tarif)
                                                          │
                                                          ▼
                                                  masuk payroll periode
```

1. Validasi kebijakan dijalankan saat pengajuan (jam kerja, bentrok jadwal,
   batas maksimal).
2. Atasan memutus tahap 1; HR memutus tahap 2.
3. Saat final `APPROVED`, sistem menghitung `compensation_amount` memakai jam
   aktual bila sudah diisi, jika tidak memakai jam rencana.
4. Notifikasi dikirim ke pemohon setiap perubahan status.

### 3.2 Pengajuan oleh HR sendiri

Tahap final lembur hanya bisa diputus **HR**, sedangkan HR tidak boleh
memutus pengajuannya sendiri. Aturannya:

```text
[Pemohon HR] ajukan ──► PENDING
                          │
                [Atasan langsung] setujui
                          │
                          ▼
                  APPROVED (final)
         manager_id terisi · hr_id kosong
```

* **Selalu selesai di atasan langsung** — berapa pun jumlah akun HR, pengajuan
  milik HR tidak lagi menunggu tahap HR karena tahap itu hanya bisa diputus
  dirinya sendiri. Notifikasi & audit mencatat `final_by: MANAGER`.
* Persetujuan atasan **wajib** dilalui; HR lain tidak boleh memutus `PENDING`
  milik rekan HR sebelum atasan memutus.
* Bila pemohon belum punya atasan langsung (atau atasannya nonaktif / menunjuk
  dirinya sendiri) dan tidak ada HR lain, pengajuan **ditolak saat dibuat**
  dengan pesan agar atasan langsung ditetapkan lebih dulu.
* Pengajuan lama yang masih `MANAGER_APPROVED` diselesaikan otomatis oleh
  migrasi skema 24 (`ApprovalEngine::finalizeLegacy()`).
* Kompensasi tetap dihitung saat status berubah menjadi `APPROVED` (termasuk pada jalur final-di-atasan).

### 3.3 Lembur terjadwal (HR)

1. HR membuka **Manajemen Lembur Terjadwal** (`/overtime/schedules`).
2. Pilih karyawan/unit, tanggal, jam, lalu simpan sebagai lembur yang sudah
   disetujui (tetap tercatat beserta pembuatnya).
3. HR dapat memperbarui jam aktual (`POST /overtime/{id}/actual`).

### 3.4 Kebijakan lembur

| Pengaturan | Default | Arti |
|------------|---------|------|
| `overtime_enabled` | aktif | Tombol pengajuan tersedia |
| `overtime_divisor` | 173 | Pembagi gaji pokok per jam |
| `overtime_first_hour_multiplier` | 1.5 | Pengali jam pertama |
| `overtime_next_hour_multiplier` | 2 | Pengali jam berikutnya |
| `overtime_holiday_multiplier` | 2 | Pengali hari libur |
| `overtime_max_hours` | 4 | Maksimal jam per pengajuan |
| `overtime_min_minutes` | 30 | Minimal durasi lembur |
| `overtime_max_compensation` | 0 (tanpa batas) | Plafon nominal |

## 4. Status

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `PENDING` | Menunggu atasan langsung | Atasan |
| `MANAGER_APPROVED` | Disetujui atasan, menunggu HR | HR |
| `APPROVED` | Final, kompensasi dihitung & masuk payroll | — |
| `REJECTED` | Ditolak | — |
| `CANCELLED` | Dibatalkan pemohon/HR | — |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /overtime` | `OvertimeController@index` | HR: konsol; atasan: persetujuan tim |
| `GET /me/overtime`, `POST /me/overtime` | `OvertimeController@mine/request` | Pengajuan pribadi |
| `POST /me/overtime/{id}/cancel` | `OvertimeController@myCancel` | Batal oleh pemohon |
| `GET/POST /overtime/schedules` | `OvertimeController@schedules/storeScheduled` | Lembur terjadwal |
| `POST /overtime/{id}/actual` | `OvertimeController@actual` | Jam aktual (HR) |
| `POST /overtime/bulk-decide` | `OvertimeController@bulkDecide` | Keputusan massal (HR) |
| `POST /overtime/policy` | `OvertimeController@savePolicy` | Kebijakan |
| `GET /overtime/export` | `OvertimeController@export` | Ekspor CSV |
| `POST /overtime/{id}/decide` | `ApprovalController@decideOvertime` | Approval dua tahap |

Berkas pendukung: `app/Models/OvertimeModel.php`, `app/Core/ApprovalEngine.php`,
`app/Views/overtime/*`.

## 6. Integrasi & catatan

* Halaman `/overtime` bersifat **dual-mode**: HR melihat konsol penuh, atasan
  melihat antrean timnya (KPI & daftar dibatasi `direct_manager_id`).
* Karyawan tanpa bawahan yang membuka `/overtime` dialihkan ke `/me/overtime`.
* Kompensasi lembur diambil payroll pada saat kalkulasi periode.
* Lembur dapat diajukan lewat aplikasi Android (`/api/v1/overtimes`).
