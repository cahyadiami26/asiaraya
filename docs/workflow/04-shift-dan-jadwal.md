# 04 — Shift & Penjadwalan

> Master shift kerja, penugasan jadwal harian per unit/departemen/karyawan, dan
> pengajuan perubahan jadwal dengan approval dua tahap.

## 1. Fungsi

* **Master shift** — nama, kode, jam masuk/pulang, toleransi keterlambatan,
  jenis operasional (`NORMAL`, `SHIFT`, `WFH`, `FIELD`).
* **Penjadwalan** — matriks harian: pilih unit/departemen/karyawan lalu tugaskan
  shift pada rentang tanggal; jadwal menjadi dasar presensi (jam kerja &
  toleransi).
* **Pengajuan perubahan jadwal** oleh karyawan (tukar/geser shift) dengan
  approval atasan → HR.
* Karyawan melihat jadwal pribadi (`/me/schedule`) dan mengajukan perubahan.
* Sinkronisasi ke presensi: shift `WFH` dapat melewati geofence (bila diaktifkan).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pengelola | CRUD shift, penugasan massal, putuskan tahap 2 perubahan jadwal |
| Atasan langsung | Approver tahap 1 | Setujui/tolak perubahan jadwal anggota tim |
| Karyawan | Pengguna | Lihat jadwal sendiri, ajukan perubahan jadwal |

## 3. Workflow

### 3.1 Menyusun jadwal

```text
HR ──► Master shift (jam masuk/pulang, toleransi, jenis)
          │
          ▼
HR ──► /schedules: pilih unit/departemen/karyawan + rentang tanggal + shift
          │
          ▼
   jadwal tersimpan per karyawan per tanggal
          │
          ├─► Presensi memakai jam & toleransi dari jadwal
          └─► Karyawan melihat jadwal di /me/schedule
```

1. HR membuat shift bila belum ada.
2. HR memilih cakupan (unit, departemen, atau karyawan tertentu) dan rentang
   tanggal, lalu menugaskan shift.
3. Penugasan dapat dihapus per baris (`DELETE /schedules/{id}`).

### 3.2 Perubahan jadwal (approval dua tahap)

```text
[Karyawan] ajukan perubahan ──► PENDING
                                   │
                    ┌──────────────┴───────────────┐
                    ▼                              ▼
        [Atasan] setujui                  [Atasan] tolak
                    │                              │
                    ▼                              ▼
            MANAGER_APPROVED                  REJECTED
                    │
        ┌───────────┴────────────┐
        ▼                        ▼
[HR] setujui → APPROVED   [HR] tolak → REJECTED
        │
        ▼
jadwal karyawan diperbarui pada tanggal yang disetujui
```

Aturan tambahan:

* Satu karyawan hanya boleh punya **satu pengajuan aktif** (PENDING /
  MANAGER_APPROVED) pada satu waktu.
* Pemohon tidak dapat memutus pengajuannya sendiri.
* HR dapat memutus tahap 2 walaupun atasan belum memutus (override) — kecuali
  pengajuan milik rekan HR yang punya atasan langsung: pengajuan itu harus
  menunggu atasan lebih dahulu.
* Bila pemohon adalah satu-satunya HR, persetujuan atasan langsung menjadi
  keputusan **final** (lihat 3.3).

### 3.3 Pengajuan oleh HR sendiri

Tahap final perubahan jadwal hanya bisa diputus **HR**, dan HR tidak boleh
memutus pengajuannya sendiri. Karena itu:

```text
HR (satu-satunya) ajukan perubahan ──► PENDING
                                            │
                              [Atasan] setujui
                                            │
                                            ▼
                        APPROVED (final di tahap atasan)
                        manager_id terisi, hr_id kosong, jadwal diperbarui

HR (ada HR lain) ajukan perubahan ──► PENDING
                                            │
                              [Atasan] setujui
                                            ▼
                                    MANAGER_APPROVED
                                            │
                              [HR lain] setujui ──► APPROVED
```

* `PENDING` milik rekan HR **tidak muncul** sebagai tugas HR lain sampai atasan
  memutus (tombol keputusan juga disembunyikan).
* Bila pemohon belum punya atasan langsung dan tidak ada HR lain, pengajuan
  ditolak saat dibuat dengan pesan agar atasan langsung ditetapkan lebih dulu —
  supaya tidak ada pengajuan yang menggantung selamanya.
* Atasan yang berstatus nonaktif tidak dihitung sebagai approver.

## 4. Status

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `PENDING` | Menunggu atasan langsung | Atasan |
| `MANAGER_APPROVED` | Disetujui atasan, menunggu HR | HR |
| `APPROVED` | Final, jadwal diperbarui | — |
| `REJECTED` | Ditolak | — |
| `CANCELLED` | Dibatalkan karyawan | — |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /shifts`, `POST /shifts`; `POST/PUT /shifts/{id}`; `POST/DELETE /shifts/{id}/delete` | `ShiftController@index/store/update/destroy` | Master shift |
| `GET /schedules`, `POST /schedules/assign` | `ScheduleController@index/assign` | Matriks penjadwalan |
| `POST/DELETE /schedules/{id}/delete` | `ScheduleController@removeAssignment` | Hapus penugasan |
| `GET /schedules/requests` | `ScheduleController@requests` | Kotak perubahan jadwal (HR = semua, atasan = tim) |
| `GET /me/schedule`, `POST /me/schedule/request` | `ScheduleController@mine/storeRequest` | Jadwal & pengajuan pribadi |
| `POST /schedules/requests/{id}/decide` | `ApprovalController@decideShiftChange` | Keputusan approval |

Berkas pendukung: `app/Models/ScheduleModel.php` (shift & jadwal),
`app/Core/ApprovalEngine.php`, `app/Views/shifts/*`, `app/Views/schedules/*`.

## 6. Catatan

* Jadwal adalah **acuan presensi**; tanpa jadwal, sistem memakai jam kerja
  default dari pengaturan presensi.
* Perubahan jadwal yang disetujui hanya mengubah tanggal yang diajukan.
* Atasan yang membuka `/schedules/requests` hanya melihat anggota timnya.
