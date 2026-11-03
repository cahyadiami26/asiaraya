# 07 — Cuti & Saldo Cuti

> Pengelolaan jenis cuti, jatah & saldo tahunan (carry over), pengajuan cuti
> karyawan, approval dua tahap, dan integrasi ke presensi.

## 1. Fungsi

* **Jenis cuti** — nama, kode, jatah default, berbayar/tidak, boleh carry over,
  memotong gaji/tidak, wajib lampiran, batas maksimal per pengajuan, dan minimal
  pemberitahuan (hari).
* **Saldo tahunan** per karyawan per jenis cuti: `quota + carry - used`.
* **Generate saldo massal** untuk tahun tertentu per unit (jatah diambil dari
  jenis cuti); baris yang sudah ada dilewati atau di-reset jatahnya.
* **Carry over** diisi per karyawan pada kolom “Sisa tahun lalu” (tidak dihitung
  otomatis) agar HR tetap memegang kendali kebijakan.
* **Pengajuan cuti** dengan rentang tanggal, alasan, dan lampiran.
* Validasi saldo (boleh negatif bila diizinkan), batas rentang hari, dan
  tumpang tindih dengan pengajuan lain.
* **Approval dua tahap**: atasan langsung → HR.
* **Pembatalan** oleh pemohon (sebelum diproses) dan oleh HR.
* Integrasi presensi: cuti `APPROVED` membuat baris presensi `LEAVE`.
* Rekap, statistik saldo (rendah/negatif), dan ekspor CSV.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Karyawan | Pemohon | Ajukan cuti, lihat saldo & status, batalkan pengajuan sendiri |
| Atasan langsung | Approver tahap 1 | Setujui/tolak cuti anggota tim |
| HR | Pengelola & approver final | Kelola jenis cuti, saldo & generate, putuskan tahap 2, batalkan, ekspor |
| FIN | Pengguna data | Melihat rekap cuti pada laporan |

## 3. Workflow

### 3.1 Menyiapkan jenis cuti & saldo

```text
HR ──► Jenis & Jatah Cuti (kode, jatah, berbayar, wajib lampiran)
          │
          ▼
HR ──► Saldo Cuti → Generate tahun berjalan (per unit / semua)
          │            └─ quota = jatah jenis cuti (default 12 hari)
          ▼
   leave_balances (employee, jenis, tahun, quota, carry, used)
          │
          └─ HR dapat mengubah quota & mengisi carry (sisa tahun lalu) per baris
```

### 3.2 Pengajuan & approval

```text
[Karyawan] /me/leave → pilih jenis, tanggal, alasan, lampiran
        │
        ├─ validasi saldo & bentrok tanggal
        ▼
    PENDING ──(tolak atasan)──► REJECTED
        │
        └─(setujui atasan)──► MANAGER_APPROVED ──(tolak HR)──► REJECTED
                                    │
                                    └─(setujui HR)──► APPROVED
                                                          │
                              ├─ saldo.used bertambah
                              └─ presensi LEAVE dibuat per tanggal
```

1. Sistem memeriksa saldo tersedia (bila tidak mengizinkan negatif) dan
   maksimal rentang hari pengajuan.
2. Atasan memutus tahap 1; HR memutus tahap 2 (dapat mengambil alih).
3. Saat final disetujui, `used` ditambahkan dan baris presensi `LEAVE` dibuat.
4. Bila pengajuan yang sudah `APPROVED` dibatalkan HR, presensi `LEAVE` dan
   saldo dikembalikan (revert).

### 3.3 Pembatalan

* Karyawan dapat membatalkan pengajuan yang belum diproses (`/me/leave/{id}/cancel`).
* HR dapat membatalkan pengajuan kapan pun (`POST /leave/{id}/cancel`) dengan
  pengembalian saldo & presensi.

## 4. Status

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `PENDING` | Menunggu atasan langsung | Atasan |
| `MANAGER_APPROVED` | Disetujui atasan, menunggu HR | HR |
| `APPROVED` | Final, saldo & presensi diperbarui | — |
| `REJECTED` | Ditolak | — |
| `CANCELLED` | Dibatalkan | — |

Pengaturan: `leave_default_quota` (12), `leave_allow_negative` (tidak),
`leave_max_range_days` (90). Setiap jenis cuti menetapkan
`quota_days`, `max_per_request`, `min_notice_days`, `requires_attachment`,
`carry_over`, dan `deduct_salary`.

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /leave` | `LeaveController@index` | HR: manajemen; atasan: persetujuan tim |
| `GET /leave/types`, `POST/PUT/DELETE` | `LeaveController@types/storeType/...` | Jenis cuti |
| `GET /leave/balances`, `POST /leave/balances[/generate]` | `LeaveController@balances/saveBalance/generateBalances` | Saldo cuti |
| `GET /me/leave`, `POST /me/leave` | `LeaveController@mine/request` | Pengajuan pribadi |
| `POST /me/leave/{id}/cancel` | `LeaveController@myCancel` | Batal oleh pemohon |
| `POST /leave/{id}/cancel` | `LeaveController@cancel` | Batal oleh HR |
| `GET /leave/export` | `LeaveController@export` | Ekspor CSV |
| `POST /leave/{id}/decide` | `ApprovalController@decideLeave` | Approval dua tahap |

Berkas pendukung: `app/Models/LeaveModel.php`, `app/Core/ApprovalEngine.php`,
`app/Views/leave/*`.

## 6. Integrasi & catatan

* Cuti disetujui memengaruhi **presensi** (`LEAVE`) dan **payroll** (potongan
  atau gaji prorata bila jenis cuti tidak berbayar).
* Halaman `/leave` dual-mode seperti lembur; atasan hanya melihat timnya.
* Cuti dapat diajukan lewat aplikasi Android (`/api/v1/leaves`).
* Pengingat sisa cuti menjelang akhir tahun dikirim otomatis oleh job
  `leave_expiry` (bawaan H-70) — lihat [22-penjadwal-dan-cron.md](22-penjadwal-dan-cron.md).
