# 15 — Offboarding & Register Aset (BAST)

> Proses keluar karyawan: pengunduran diri/PHK, persetujuan berjenjang, checklist
> serah terima, pengembalian aset, BAST PDF, exit interview, dan penonaktifan.

## 1. Fungsi

* **Pengajuan offboarding**: oleh karyawan (pengunduran diri) atau oleh HR
  (PHK, akhir kontrak, pensiun, lainnya) beserta tanggal kerja terakhir & alasan.
* **Approval dua tahap**: atasan langsung → HR.
* **Checklist serah terima (clearance)** dengan kategori & pemilik item:
  `HANDOVER` (atasan), `ASSET`, `DOCUMENT`, `ACCESS` (IT), `FINANCE`, `OTHER`.
* **Register aset** yang dipegang karyawan (laptop, ID card, akses, dsb.) dengan
  status pengembalian per item.
* **BAST PDF** bertanda tangan (berita acara serah terima aset).
* **Exit interview** + catatan penyelesaian akhir.
* **Penyelesaian** menandai karyawan `RESIGN` dan menonaktifkan akun.
* **Blokir penutupan** bila masih ada aset berstatus belum kembali.
* Ekspor CSV dan riwayat offboarding.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Karyawan | Pemohon | Ajukan pengunduran diri, batalkan, isi exit interview, lihat status |
| Atasan langsung | Approver tahap 1 + pemilik item handover | Setujui/tolak, selesaikan item `MANAGER` |
| HR | Pengelola & approver final | Buat pengajuan (PHK/dsb.), setujui, checklist, aset, BAST, selesaikan |
| FIN | Pemilik item keuangan | Menyelesaikan item kategori `FINANCE` (kasbon, klaim, pinjaman) |
| IT | Pemilik item akses (peran operasional) | Menyelesaikan item `ACCESS`/aset perangkat |

> Catatan: `IT` bukan role sistem; item dengan `owner_role = IT` diselesaikan
> oleh HR kecuali organisasi menunjuk pengguna tertentu.

## 3. Workflow

```text
[Karyawan] /me/offboarding ──► SUBMITTED ──(tolak atasan)──► REJECTED
        atau                            │
[HR] /offboarding (PHK, dsb.)           └─(setujui atasan)──► MANAGER_APPROVED
                                                                   │
                                                       (tolak HR) ──┤
                                                                   ▼
                                                        (setujui HR) IN_PROGRESS
                                                                   │
                                    checklist clearance + pengembalian aset
                                                                   │
                                                semua aset kembali? ──tidak──► tetap IN_PROGRESS
                                                                   │ ya
                                                                   ▼
                                                              COMPLETED
                                                                   │
                                                     karyawan flag = RESIGN
```

1. **Pengajuan**: karyawan mengisi tanggal terakhir & alasan; HR juga dapat
   membuat pengajuan untuk PHK/akhir kontrak.
2. **Approval**: atasan menyetujui (`SUBMITTED → MANAGER_APPROVED`), lalu HR
   menyetujui (`→ IN_PROGRESS`). Saat HR menyetujui, checklist default dibuat.
3. **Serah terima**: HR/atasan/FIN menandai item `DONE` atau `WAIVED`; aset
   diubah statusnya (`RETURNED`, `WAIVED`, `LOST`, `NOT_HELD`).
4. **BAST**: HR menerbitkan berita acara (`POST /offboarding/{id}/bast`) — PDF
   dapat diunduh karyawan.
5. **Exit interview**: HR mengisi hasil wawancara.
6. **Selesai**: HR menekan “Selesaikan”. Sistem menolak bila masih ada aset
   `PENDING`; bila lolos, karyawan ditandai `RESIGN` dan tidak dapat login lagi.

### 3.1 Pembatalan

* Karyawan dapat menarik pengajuan sebelum diproses (`withdrawMine` → `WITHDRAWN`).
* HR dapat membatalkan (`cancel` → `CANCELLED`).

## 4. Status

**Pengajuan**

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `DRAFT` | Draf (belum diajukan) | Pemohon |
| `SUBMITTED` | Menunggu atasan | Atasan |
| `MANAGER_APPROVED` | Menunggu HR | HR |
| `IN_PROGRESS` | Serah terima berjalan | HR/atasan/FIN |
| `COMPLETED` | Selesai, karyawan nonaktif | — |
| `REJECTED` / `WITHDRAWN` / `CANCELLED` | Ditolak / ditarik / dibatalkan | — |

**Checklist** `PENDING → DONE | WAIVED` · **Aset** `PENDING → RETURNED | WAIVED |
NOT_HELD | LOST`

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /offboarding` | `OffboardingController@index` | Daftar pengajuan (HR) |
| `POST /offboarding` | `OffboardingController@store` | Buat pengajuan (HR) |
| `GET /offboarding/{id}` | `OffboardingController@show` | Detail + checklist + aset |
| `POST /offboarding/{id}/manager-approve` | `OffboardingController@approveManager` | Approve atasan |
| `POST /offboarding/{id}/approve` | `OffboardingController@approveHr` | Approve HR |
| `POST /offboarding/{id}/assets`, `POST /offboarding/{id}/assets/{assetId}` | `OffboardingController@addAsset/updateAsset` | Register & status aset |
| `POST /offboarding/{id}/bast`, `GET /offboarding/{id}/bast` | `OffboardingController@issueBast/bastPdf` | BAST |
| `POST /offboarding/{id}/clearance`, `POST /offboarding/{id}/clearance/{itemId}` | `OffboardingController@addClearance/updateClearance` | Checklist |
| `POST /offboarding/{id}/interview` | `OffboardingController@interview` | Exit interview |
| `POST /offboarding/{id}/complete` | `OffboardingController@complete` | Selesaikan |
| `GET/POST /me/offboarding`, `POST /me/offboarding/{id}/withdraw`, `POST /me/offboarding/{id}/feedback` | `OffboardingController@mine/submitMine/...` | Pengajuan karyawan |

Berkas pendukung: `app/Models/OffboardingModel.php`,
`app/Views/offboarding/*`, tabel `offboarding_requests`, `offboarding_clearance`,
`offboarding_assets`, `offboarding_bast`.

## 6. Integrasi & catatan

* Penyelesaian offboarding menonaktifkan akun lewat `flag = RESIGN`.
* Item `FINANCE` (kasbon/klaim) harus selesai sebelum penutupan agar tidak ada
  piutang tertinggal.
* Modul **Kepegawaian** tetap punya tombol *Resign* cepat untuk kasus
  administratif tanpa proses serah terima.
