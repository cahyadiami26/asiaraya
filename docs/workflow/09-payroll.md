# 09 — Payroll & Slip Gaji

> Perhitungan gaji per unit per bulan, alur persetujuan HR → Finance, pembayaran,
> dan slip gaji PDF ber-QR dengan verifikasi publik.

## 1. Fungsi

* **Periode payroll** per **business unit + bulan + tahun** (unik per kombinasi).
* **Kalkulasi otomatis** dari: presensi (hari hadir, alpha, denda), lembur
  disetujui, komponen gaji, cuti tidak berbayar, dan klaim yang sudah disetujui.
* **Alur status**: `DRAFT → CALCULATED → SENT_TO_FINANCE → FINANCE_APPROVED → PAID`.
* **Slip gaji PDF** ber-QR per karyawan + **verifikasi publik** tanpa login.
* Rekap per departemen, tren 6 bulan, ekspor CSV, cetak massal.
* Perbaikan periode: `reopen` (HR), hapus periode (HR, hanya sebelum dikirim).
* Karyawan melihat slip sendiri di `/me/payslip`.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pembuat & pengirim | Buat periode, kalkulasi, kirim ke Finance, reopen, hapus, ekspor |
| FIN | Approver & pembayar | Setujui periode, tandai lunas (PAID), cetak & ekspor |
| Karyawan | Penerima | Lihat & unduh slip sendiri, verifikasi QR |

## 3. Workflow

```text
[HR] Buat periode (unit + bulan)
        │  status DRAFT
        ▼
[HR] Hitung (calculate)
        │  presensi + lembur + komponen + klaim APPROVED
        │  → payroll_items per karyawan
        │  status CALCULATED
        ▼
[HR] Kirim ke Finance (submit)
        │  status SENT_TO_FINANCE  ── muncul di menu “Persetujuan Payroll” (FIN)
        ▼
[FIN] Setujui (approve)
        │  status FINANCE_APPROVED
        ▼
[FIN] Bayar (pay)
        │  status PAID  + payment_status slip = PAID
        ▼
   slip PDF ber-QR tersedia; klaim terkait ditandai PAID
```

1. **Periode baru** hanya boleh satu per unit per bulan (unique index).
2. **Kalkulasi** mengambil data pada rentang tanggal cut-off (default tanggal
   25) dan menghitung PPh/BPJS memakai pengaturan pada modul 08.
3. **Submit** mengunci periode dari perubahan; `reopen` oleh HR mengembalikan ke
   `CALCULATED` (selama belum `PAID`).
4. **Approve** oleh Finance menandai persetujuan beserta nama & waktu.
5. **Pay** menandai seluruh slip lunas dan klaim yang menempel menjadi `PAID`.
6. Setiap perubahan status dicatat di audit log dan memicu notifikasi.

### 3.1 Klaim & lembur dalam payroll

* Lembur `APPROVED` dihitung sebagai komponen pendapatan.
* Klaim `APPROVED` pada rentang periode diikutkan, lalu ditautkan ke slip
  (`expense_claims.payroll_item_id`) dan berstatus `SCHEDULED`; saat periode
  `PAID`, klaim berubah `PAID`.

### 3.2 Slip & verifikasi

* Slip memuat rincian pendapatan/potongan, tanda tangan HR & Finance, dan QR.
* QR menuju `/verify/slip/{id}/{token}` (publik, tanpa login) untuk memastikan
  keaslian slip.

## 4. Status

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `DRAFT` | Periode dibuat, belum dihitung | HR |
| `CALCULATED` | Sudah dihitung, masih bisa diubah | HR |
| `SENT_TO_FINANCE` | Menunggu persetujuan Finance | FIN |
| `FINANCE_APPROVED` | Disetujui Finance, menunggu pembayaran | FIN |
| `PAID` | Lunas | — |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /payroll` | `PayrollController@index` | Daftar periode + KPI |
| `POST /payroll` | `PayrollController@store` | Buat periode (HR) |
| `GET /payroll/{id}` | `PayrollController@show` | Detail periode & slip |
| `POST /payroll/{id}/calculate` | `PayrollController@calculate` | Kalkulasi (HR) |
| `POST /payroll/{id}/submit` | `PayrollController@submit` | Kirim ke Finance (HR) |
| `POST /payroll/{id}/approve` | `PayrollController@approve` | Setujui (FIN) |
| `POST /payroll/{id}/pay` | `PayrollController@pay` | Bayar / lunas (FIN) |
| `POST /payroll/{id}/reopen` | `PayrollController@reopen` | Buka kembali (HR) |
| `POST /payroll/{id}/delete` | `PayrollController@destroy` | Hapus periode (HR) |
| `GET /payroll/{id}/export` | `PayrollController@export` | Ekspor CSV |
| `GET /payroll/items/{id}/pdf` | `PayrollController@slipPdf` | Slip PDF |
| `GET /verify/slip/{id}/{token}` | `PayrollController@verify` | Verifikasi publik |
| `GET /me/payslip` | `PayrollController@mine` | Slip karyawan |

Berkas pendukung: `app/Models/PayrollModel.php`, `app/Models/SalaryModel.php`,
`app/Views/payroll/*`, tabel `payroll_periods`, `payroll_items`.

## 6. Integrasi & catatan

* Menu **Persetujuan Payroll** (FIN) menampilkan filter
  `status=SENT_TO_FINANCE` dengan badge jumlah antrean.
* Slip dan bukti potong pajak memakai kop & penandatangan dari profil perusahaan.
* Data slip yang sudah `PAID` bersifat final; koreksi dilakukan lewat periode baru.
