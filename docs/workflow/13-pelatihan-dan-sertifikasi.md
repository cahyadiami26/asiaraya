# 13 — Pelatihan & Sertifikasi

> Katalog program pelatihan, sesi dengan kuota & pendaftaran, kehadiran, nilai,
> dan sertifikat PDF.

## 1. Fungsi

* **Katalog program** — kategori, durasi (jam), biaya, status aktif.
* **Sesi pelatihan** — jadwal, lokasi/online, kuota, biaya, status.
* **Pendaftaran daring** karyawan (kuota & batas waktu pendaftaran) dan
  **nominasi HR**.
* **Persetujuan peserta** — semua pendaftaran (mandiri maupun nominasi HR)
  masuk sebagai `NOMINATED` dan disetujui HR menjadi `APPROVED`.
* **Presensi peserta** (hadir/tidak hadir), **nilai**, dan kelulusan.
* **Sertifikat PDF** per peserta + riwayat pelatihan karyawan.
* Ekspor CSV sesi/peserta dan rekap biaya pelatihan.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pengelola | CRUD program & sesi, nominasi peserta, presensi, nilai, sertifikat, ekspor |
| Karyawan | Peserta | Lihat katalog, daftar/batalkan pendaftaran, unduh sertifikat |
| Atasan langsung | Pendukung | Melihat pelatihan anggota tim (melalui riwayat) |

## 3. Workflow

```text
[HR] Katalog program ──► Sesi pelatihan (DRAFT → OPEN)
        │                         │
        │                         ├─ karyawan mendaftar (kuota)
        │                         └─ HR menominasikan peserta
        ▼
   peserta NOMINATED ──(HR/kebijakan)──► APPROVED (terdaftar)
        │
        ▼  sesi dimulai (ONGOING)
   presensi peserta: ATTENDED / ABSENT
        │
        ▼  sesi selesai (COMPLETED)
   nilai + sertifikat PDF ──► riwayat pelatihan karyawan
```

1. HR membuat program lalu sesi; sesi dibuka (`OPEN`) agar dapat didaftari.
2. Karyawan mendaftar dari `/me/trainings` — status sesi `OPEN`, batas
   pendaftaran belum lewat, kuota masih tersedia, dan belum terdaftar.
3. Pendaftaran tersimpan sebagai `NOMINATED`; HR menyetujui (satuan atau
   **setujui semua**) menjadi `APPROVED`, atau menolak/menghapus peserta.
4. Saat sesi berlangsung, HR mengisi presensi; setelah selesai, HR mengisi nilai
   dan menerbitkan sertifikat.
5. Sesi dapat dibatalkan (`CANCELLED`); peserta otomatis dibatalkan.

## 4. Status

**Sesi**

| Status | Arti |
|--------|------|
| `DRAFT` | Belum dipublikasikan |
| `OPEN` | Pendaftaran dibuka |
| `ONGOING` | Sedang berlangsung |
| `COMPLETED` | Selesai (nilai & sertifikat) |
| `CANCELLED` | Dibatalkan |

**Peserta**

| Status | Arti |
|--------|------|
| `NOMINATED` | Menunggu persetujuan (nominasi HR) |
| `APPROVED` | Terdaftar |
| `ATTENDED` | Hadir |
| `ABSENT` | Tidak hadir |
| `CANCELLED` | Dibatalkan |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /trainings` | `TrainingController@index` | Daftar sesi (HR) |
| `GET/POST /trainings/catalog` (+`PUT/DELETE`) | `TrainingController@catalog/storeProgram/...` | Katalog program |
| `POST /trainings` | `TrainingController@storeSession` | Buat sesi |
| `POST /trainings/{id}/open`, `POST /trainings/{id}/start`, `POST /trainings/{id}/complete`, `POST /trainings/{id}/cancel` | `TrainingController@openSession/startSession/completeSession/cancelSession` | Status sesi |
| `POST /trainings/{id}/participants` | `TrainingController@storeParticipants` | Tambah peserta/nominasi |
| `POST /trainings/{id}/participants/{pid}` | `TrainingController@updateParticipantStatus` | Approve/kehadiran |
| `POST /trainings/{id}/results` | `TrainingController@storeResult` | Nilai peserta |
| `GET /trainings/{id}/export` | `TrainingController@export` | Ekspor peserta |
| `GET /me/trainings`, `POST /me/trainings/{id}/enroll`, `POST /me/trainings/{id}/cancel` | `TrainingController@mine/enroll/cancelEnrollment` | Pendaftaran karyawan |

Berkas pendukung: `app/Models/TrainingModel.php`, `app/Views/trainings/*`,
tabel `trainings`, `training_sessions`, `training_participants`.

## 6. Catatan

* Kuota sesi dihitung dari peserta aktif (`NOMINATED`, `APPROVED`, `ATTENDED`,
  `ABSENT`).
* Sertifikat memakai kop perusahaan dan dapat diverifikasi dari nomor sertifikat.
* Biaya pelatihan masuk rekap HR (bukan payroll).
