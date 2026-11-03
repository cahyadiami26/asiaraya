# 10 — Klaim & Reimbursement

> Pengajuan penggantian biaya operasional karyawan, persetujuan berjenjang, dan
> pembayaran yang dapat menempel ke payroll.

## 1. Fungsi

* **Kategori klaim** dengan **plafon** (batas nominal per pengajuan/periode) dan
  status aktif.
* **Pengajuan klaim**: tanggal, kategori, nominal, keterangan, lampiran bukti.
* **Approval**: atasan langsung → HR/Finance; Finance juga menandai pembayaran.
* **Aksi massal** setujui/tolak untuk HR/Finance.
* **Integrasi payroll**: klaim `APPROVED` masuk periode payroll berikutnya
  (`SCHEDULED`) lalu `PAID` saat periode dibayar.
* Rekap, statistik, ekspor CSV, dan halaman **Klaim Saya**.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Karyawan | Pemohon | Ajukan klaim + bukti, lihat status, batalkan pengajuan yang belum diproses |
| Atasan langsung | Approver tahap 1 | Setujui/tolak klaim anggota tim |
| HR | Approver final & pengelola kategori | Setujui/tolak, kelola kategori & plafon, ekspor |
| FIN | Approver final & pembayar | Setujui/tolak, tandai dibayarkan, ekspor |

## 3. Workflow

```text
[Karyawan] /me/claims → kategori, tanggal, nominal, bukti
        │
        ▼
    PENDING ──(tolak atasan)──► REJECTED
        │
        └─(setujui atasan)──► MANAGER_APPROVED ──(tolak HR/FIN)──► REJECTED
                                    │
                                    └─(setujui HR/FIN)──► APPROVED
                                                            │
                              kalkulasi payroll berikutnya  ▼
                                                        SCHEDULED
                                                            │
                                            [FIN] periode dibayar
                                                            ▼
                                                          PAID
```

1. Validasi: kategori aktif, nominal > 0, tidak melebihi plafon, bukti wajib
   bila diminta.
2. Atasan memutus tahap 1; HR **atau** Finance dapat memutus tahap final
   (termasuk mengambil alih bila atasan belum memutus).
3. Klaim `APPROVED` otomatis ditautkan ke slip saat payroll periode dihitung.
4. Finance dapat langsung menandai klaim dibayar di luar payroll
   (`POST /claims/{id}/pay`) — status menjadi `PAID`.

### 3.1 Pengajuan oleh HR/Finance sendiri

Approver final klaim adalah **HR atau Finance**, dan keduanya tidak boleh
memutus klaim sendiri:

* Bila pemohon punya atasan langsung, klaim **selesai di atasan**: persetujuan
  atasan langsung menjadi keputusan **final** (`PENDING → APPROVED`) — klaim
  langsung siap dibayarkan tanpa menunggu HR/Finance lain.
* HR/Finance lain tidak boleh memutus klaim `PENDING` milik rekan HR/Finance
  sebelum atasan memutus (kotak masuk menyembunyikannya).
* Bila pemohon tidak punya atasan langsung **dan** tidak ada HR/Finance lain,
  klaim ditolak saat dibuat dengan pesan agar atasan langsung ditetapkan lebih
  dulu (mencegah klaim menggantung).
* Klaim lama yang masih `MANAGER_APPROVED` milik HR/Finance diselesaikan
  otomatis oleh migrasi skema 24.
* Kotak masuk HR/Finance tidak menampilkan klaim sendiri maupun klaim rekan
  HR/Finance yang masih menunggu atasan.

## 4. Status

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `PENDING` | Menunggu atasan langsung | Atasan |
| `MANAGER_APPROVED` | Menunggu HR/Finance | HR / FIN |
| `APPROVED` | Disetujui, menunggu payroll | HR (kalkulasi payroll) |
| `SCHEDULED` | Sudah masuk slip payroll | FIN (pembayaran periode) |
| `PAID` | Dibayarkan | — |
| `REJECTED` / `CANCELLED` | Ditolak / dibatalkan | — |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /claims` | `ExpenseController@index` | Antrean klaim (default status OPEN) |
| `GET /claims/categories`, `POST /claims/categories`; `POST/PUT /claims/categories/{id}`; `POST/DELETE /claims/categories/{id}/delete` | `ExpenseController@categories/storeCategory/updateCategory/destroyCategory` | Kategori & plafon (HR) |
| `GET /claims/{id}` | `ExpenseController@show` | Detail + bukti |
| `POST /claims/{id}/decide` | `ExpenseController@decide` | Setujui/tolak |
| `POST /claims/bulk-decide` | `ExpenseController@bulkDecide` | Aksi massal |
| `POST /claims/{id}/pay` | `ExpenseController@pay` | Tandai dibayar (FIN) |
| `GET /claims/export` | `ExpenseController@export` | Ekspor CSV |
| `GET /me/claims`, `POST /me/claims` | `ExpenseController@mine/store` | Klaim pribadi |
| `POST /me/claims/{id}/cancel` | `ExpenseController@cancel` | Batal oleh pemohon |

Berkas pendukung: `app/Models/ExpenseModel.php`, `app/Views/claims/*`, tabel
`expense_claims`, `expense_categories`.

## 6. Integrasi & catatan

* Plafon kategori dihitung dari total klaim berstatus `APPROVED`, `SCHEDULED`,
  dan `PAID`.
* Klaim yang sudah `SCHEDULED` tidak dapat diubah; batalkan lewat HR sebelum
  payroll dibayar.
* Aplikasi Android dapat mengajukan & memantau klaim (`/api/v1/claims`).
