# 16 — Dokumen Karyawan & Riwayat Jabatan

> Arsip digital dokumen kepegawaian dengan verifikasi HR, serta linimasa
> perjalanan karier karyawan.

## 1. Fungsi

### Dokumen

* **Jenis dokumen**: KTP, NPWP, KK, ijazah, sertifikat, kontrak, SK, BPJS, SIM,
  paspor, dan lainnya; masing-masing dengan nomor, masa berlaku, dan penanda
  **rahasia**.
* **Unggah oleh HR** (semua karyawan) dan **unggah mandiri** oleh karyawan.
* **Verifikasi HR**: `UNVERIFIED → VERIFIED` atau `REJECTED` (dengan alasan).
* **Pengingat kedaluwarsa** (H-60) via cron + badge menu.
* Pencarian/filter, unduh dokumen (rahasia hanya HR), dan audit akses.

### Riwayat jabatan

* **Pencatatan perubahan**: pengangkatan awal, promosi, mutasi, demosi,
  perpanjangan kontrak, penyesuaian gaji, perubahan status.
* **Tanggal efektif** + snapshot organisasi (unit, departemen, jabatan, gaji).
* **Penyelarasan otomatis** data karyawan saat riwayat disimpan.
* Linimasa karier untuk HR dan karyawan (`/me/career`).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pengelola | Unggah/ubah dokumen semua karyawan, verifikasi/tolak, catat riwayat jabatan, unduh dokumen rahasia |
| FIN | — | Tidak punya akses modul dokumen |
| Karyawan | Pemilik dokumen | Unggah dokumen sendiri, unduh dokumen non-rahasia, lihat riwayat jabatan sendiri |

## 3. Workflow

### 3.1 Dokumen

```text
[HR] /documents → pilih karyawan, jenis, nomor, masa berlaku, unggah berkas
        │                                        (atau [Karyawan] /me/documents)
        ▼
   UNVERIFIED ──(HR verifikasi)──► VERIFIED
        │
        └─(HR tolak + alasan)──► REJECTED ──► karyawan unggah ulang
```

1. Berkas disimpan di `storage/uploads/documents` dengan nama acak; tipe & ukuran
   divalidasi.
2. HR memeriksa keaslian lalu memverifikasi atau menolak.
3. Dokumen dengan `expires_at` dalam 60 hari tampil sebagai “akan kedaluwarsa”
   dan memicu pengingat cron `document_expiry`.
4. Dokumen bertanda rahasia hanya dapat diunduh HR; karyawan melihat metadata.

### 3.2 Riwayat jabatan

```text
[HR] /careers/create → pilih karyawan + jenis perubahan + tanggal efektif
        │  isi unit/departemen/jabatan/gaji baru (opsional)
        ▼
   employee_job_history tersimpan (snapshot lama & baru)
        │
        ├─ data karyawan diperbarui otomatis (posisi, unit, gaji, status)
        └─ linimasa tampil di /careers (HR) dan /me/career (karyawan)
```

## 4. Status

| Objek | Status | Arti |
|-------|--------|------|
| Dokumen | `UNVERIFIED` | Menunggu verifikasi HR |
| | `VERIFIED` | Sah, dapat dipakai referensi |
| | `REJECTED` | Ditolak (alasan wajib), perlu unggah ulang |
| Riwayat jabatan | — | Setiap baris permanen; koreksi = catatan baru |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /documents` | `DocumentController@index` | Arsip dokumen (HR) |
| `GET /documents/create`, `POST /documents` | `DocumentController@create/store` | Unggah (HR) |
| `POST/PUT /documents/{id}`, `POST /documents/{id}/verify` | `DocumentController@update/verify` | Ubah & verifikasi |
| `GET /documents/{id}/download` | `DocumentController@download` | Unduh (cek hak akses) |
| `GET /me/documents`, `POST /me/documents` | `DocumentController@mine/storeMine` | Dokumen pribadi |
| `GET /careers`, `GET /careers/create`, `POST /careers` | `CareerController@index/create/store` | Riwayat jabatan (HR) |
| `POST/DELETE /careers/{id}/delete` | `CareerController@destroy` | Hapus catatan (koreksi) |
| `GET /me/career` | `CareerController@mine` | Linimasa karyawan |

Berkas pendukung: `app/Models/DocumentModel.php`, `app/Models/CareerModel.php`,
`app/Views/documents/*`, `app/Views/careers/*`, tabel `employee_documents`,
`employee_job_history`.

## 6. Catatan

* Cron `document_expiry` mengirim pengingat dokumen yang akan kedaluwarsa.
* Perubahan jabatan/gaji wajib dicatat di riwayat agar audit trail lengkap;
  payroll memakai nilai terbaru pada data karyawan.
* Dokumen kontrak dan SK dapat dipakai sebagai lampiran offboarding.
