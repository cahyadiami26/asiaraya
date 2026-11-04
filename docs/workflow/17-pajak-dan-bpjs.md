# 17 — Pajak PPh 21 & BPJS

> Rekonsiliasi PPh 21 tahunan, penerbitan bukti potong 1721-A1 (PDF + QR
> verifikasi), ekspor e-SPT, serta rekapitulasi iuran BPJS Kesehatan & BPJS
> Ketenagakerjaan per periode.

## 1. Fungsi

### Pajak (PPh 21)

* **Rekap tahunan** per karyawan: bruto, biaya jabatan, iuran pensiun, netto,
  PTKP, PKP, PPh terutang, PPh dipotong, dan selisih (kurang/lebih bayar).
* **Sumber data**: komponen payroll bertanda *Kena PPh* dari periode payroll
  berstatus final (`FINANCE_APPROVED`/`PAID`) pada tahun pajak terkait.
* **Bukti potong 1721-A1**: nomor urut `1.1-<tahun>-NNNN`, PDF resmi, dan **QR
  verifikasi publik** (`/tax/verify/{id}/{year}`).
* **Terbitkan satuan / massal** (`issue-all`) untuk semua karyawan yang lolos
  filter; karyawan yang belum punya payroll final dilewati.
* **Cabut bukti potong** bila ada koreksi (menghapus slip, nomor dipakai ulang).
* **Ekspor e-SPT** (CSV) dan ringkasan per PTKP/unit/bulan.
* Karyawan melihat & mengunduh bukti potongnya sendiri di `/me/tax`.

### BPJS

* **Rekap iuran** per program: KES, JHT, JP, JKK, JKM — porsi perusahaan (ER)
  dan karyawan (EE) dipisah.
* Filter tahun/bulan/unit/karyawan; ringkasan bulanan & tahunan, cakupan peserta.
* **Ekspor CSV/PDF** untuk pelaporan ke BPJS.
* Batas upah diterapkan: KES Rp 12 jt, JP Rp 10 jt; JKK/JKM hanya porsi
  perusahaan.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pengelola data & penerbit | Lihat rekap, terbitkan/cabut bukti potong, ekspor e-SPT, lihat & ekspor BPJS |
| FIN | Verifikator & pelapor | Sama dengan HR (modul pajak & BPJS terbuka untuk HR dan FIN) |
| Karyawan | Penerima bukti potong | `/me/tax`, unduh PDF bukti potong sendiri, akses via API `tax-slips` |
| Publik | Verifikator dokumen | Buka tautan QR `/tax/verify/{id}/{year}` tanpa login |

## 3. Workflow

### 3.1 Penerbitan bukti potong

```text
[Payroll] periode FINANCE_APPROVED / PAID
        │  (komponen bertanda Kena PPh)
        ▼
[HR/FIN] /tax?year=YYYY → rekap per karyawan (bruto → PKP → PPh)
        │
        ├─[Terbitkan] satu karyawan → tax_slips (nomor 1.1-YYYY-NNNN)
        ├─[Terbitkan semua] → semua yang lolos filter (yang belum final dilewati)
        └─[Cetak 1721-A1] → PDF + QR; bila belum ada slip, diterbitkan otomatis
        ▼
[Karyawan] /me/tax → unduh PDF / buka QR verifikasi
```

1. Pilih **tahun pajak** (dan filter unit/kata kunci bila perlu).
2. Periksa kolom bruto, PTKP (`TK/0`, `K/1`, …), PKP, PPh terutang vs dipotong.
3. Terbitkan bukti potong; nomor urut dihitung dari jumlah slip tahun tersebut.
4. Unduh PDF — dokumen memuat QR berisi URL verifikasi publik.
5. Bila ada koreksi payroll: **cabut** slip, perbaiki payroll, terbitkan ulang.

### 3.2 Rekap BPJS

```text
[HR/FIN] /bpjs?year=YYYY&month=MM → rekap per program
        │  (dari komponen BPJS_* payroll final)
        ├─ ringkasan ER vs EE per program
        ├─ rincian per karyawan (upah dasar, batas upah)
        └─ ekspor CSV / PDF untuk pelaporan
```

## 4. Status

| Objek | Status | Arti |
|-------|--------|------|
| Slip pajak | *belum terbit* | Belum ada baris `tax_slips` |
| | `ISSUED` (tersimpan) | Bukti potong sudah bernomor & dapat diunduh |
| Sumber payroll | `FINANCE_APPROVED`, `PAID` | Hanya status ini yang dihitung (`FINAL_STATUSES`) |

> Tidak ada alur persetujuan pada modul ini; ia adalah hilir dari payroll.
> Pembatalan = cabut slip (hapus baris), bukan status.

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /tax` | `TaxController@index` | Rekap PPh 21 per tahun |
| `GET /tax/{id}` | `TaxController@show` | Detail per karyawan |
| `POST /tax/{id}/issue`, `POST /tax/issue-all` | `TaxController@issue/issueAll` | Terbitkan bukti potong |
| `POST /tax/{id}/revoke` | `TaxController@revoke` | Cabut bukti potong |
| `GET /tax/{id}/pdf`, `GET /tax/export` | `TaxController@pdf/export` | PDF 1721-A1 & CSV e-SPT |
| `GET /tax/verify/{id}/{year}` | `TaxController@verify` | Halaman verifikasi QR (publik) |
| `GET /bpjs`, `GET /bpjs/csv`, `GET /bpjs/pdf` | `BpjsController@index/csv/pdf` | Rekap & ekspor BPJS |
| `GET /me/tax` | `TaxController@mine` | Bukti potong karyawan |
| `GET /api/v1/tax-slips[...]` | `TaxApiController@index/show/pdf` | Versi Android |

Berkas pendukung: `app/Models/TaxModel.php`, `app/Models/BpjsModel.php`,
`app/Views/tax/*`, `app/Views/bpjs/*`, tabel `tax_slips` + `payroll_items`.

## 6. Catatan

* Perhitungan PPh 21 memakai metode yang dipilih di **Pengaturan → Payroll**
  (`TER` bawaan, `FLAT`, atau `PROGRESSIVE`) dan pembulatan `pph_round`.
* Slip pajak menyimpan *snapshot* detail payroll, sehingga perubahan data
  karyawan tidak mengubah dokumen yang sudah terbit.
* QR memakai layanan pembuat QR daring (`Qr::png`) dengan URL yang dapat
  diverifikasi tanpa login.
* BPJS hanya membaca komponen kode `BPJS_KES_EE/ER`, `BPJS_JHT_EE/ER`,
  `BPJS_JP_EE/ER`, `BPJS_JKK`, `BPJS_JKM` — komponen dengan kode lain diabaikan.
