# 08 — Komponen Gaji

> Master tunjangan/potongan yang membentuk take-home pay, penugasannya per
> karyawan, dan simulator perhitungan gaji (PPh 21 & BPJS).

## 1. Fungsi

* **Master komponen gaji**: kode, nama, tipe (`BENEFIT` tunjangan /
  `DEDUCTION` potongan), cara hitung (`FIXED`, `PERCENT`, `PRORATE`), dasar
  persentase (`BASIC` gaji pokok / `GROSS` bruto), nominal, serta penanda
  **Kena PPh** dan **Dasar BPJS**.
* **Penugasan (assignment)** komponen ke karyawan dengan nominal override,
  tanggal efektif, dan tanggal berakhir.
* **Terapkan default** — komponen bertanda `apply_default` otomatis diberikan
  ke karyawan yang belum memilikinya.
* **Simulator take-home** per karyawan: bruto, potongan BPJS (Kes/JHT/JP),
  PPh 21 (TER / FLAT / PROGRESSIVE), dan sisa bersih.
* **Pengaturan payroll**: tanggal cut-off, tanggal bayar, metode PPh, tarif
  BPJS & batas upah, penanda komponen terkunci (`BASIC`, `BPJS_*_EE`, `PPH21`).

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pengelola | CRUD komponen, penugasan, apply default, simulator, pengaturan payroll/BPJS |
| FIN | Pengguna data | Melihat komponen & hasil perhitungan pada payroll/laporan |
| Karyawan | Penerima | Melihat rincian komponen pada slip gaji (tanpa mengubah) |

## 3. Workflow

### 3.1 Menyiapkan komponen

```text
HR ──► /components → Tambah komponen
        │   kode, nama, tipe (tunjangan/potongan)
        │   cara hitung: FIXED | PERCENT (BASIC/GROSS) | PRORATE
        │   penanda: Kena PPh? Dasar BPJS? Terapkan otomatis?
        ▼
   salary_components (aktif/nonaktif, terkunci untuk kode inti)
```

### 3.2 Menugaskan ke karyawan

```text
HR ──► pilih komponen + karyawan + nominal (override) + periode berlaku
        │
        ├─ penugasan tunggal          (POST /components/assign)
        ├─ terapkan default massal    (POST /components/apply-defaults)
        └─ ubah/hapus penugasan       (PUT/DELETE /components/assignments/{id})
              ▼
        employee_components (is_active, effective_date, end_date)
```

1. Penugasan hanya dihitung bila `is_active = 1` dan tanggal efektif dalam
   periode payroll.
2. Nominal override mengalahkan nominal master.
3. Komponen `PRORATE` dihitung berdasarkan hari kerja hadir pada periode.

### 3.3 Simulasi take-home

```text
gaji pokok + tunjangan (komponen BENEFIT)
        − potongan (komponen DEDUCTION)
        − BPJS karyawan (Kes 1%, JHT 2%, JP 1% — dengan batas upah)
        − PPh 21 (TER / FLAT / PROGRESSIVE)
        = take-home pay
```

Simulator memakai fungsi yang sama dengan payroll (`SalaryModel::takeHome()`),
sehingga angka simulasi = angka slip bila data tidak berubah.

## 4. Status & aturan

| Objek | Nilai | Arti |
|-------|-------|------|
| `salary_components.type` | `BENEFIT` / `DEDUCTION` | Menambah / mengurangi bruto |
| `calc_type` | `FIXED`, `PERCENT`, `PRORATE` | Cara hitung |
| `percent_base` | `BASIC`, `GROSS` | Dasar persentase |
| `apply_default` | 1 / 0 | Otomatis diberikan ke karyawan baru |
| `taxable` / `bpjs_base` | 1 / 0 | Masuk perhitungan PPh / dasar BPJS |
| Komponen terkunci | `BASIC`, `BPJS_KES_EE`, `BPJS_JHT_EE`, `BPJS_JP_EE`, `PPH21` | Tidak boleh dihapus |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /components` | `ComponentController@index` | Daftar komponen + statistik |
| `POST /components`; `POST/PUT /components/{id}`; `POST/DELETE /components/{id}/delete` | `ComponentController@store/update/destroy` | CRUD komponen |
| `POST /components/assign` | `ComponentController@assign` | Penugasan |
| `POST /components/apply-defaults` | `ComponentController@applyDefaults` | Terapkan default |
| `GET /components/simulate/{id}` | `ComponentController@simulate` | Simulator take-home |
| `POST /settings/payroll` | `SettingsController@savePayroll` | Pengaturan payroll & BPJS |

Berkas pendukung: `app/Models/SalaryModel.php`, `app/Views/components/index.php`,
tabel `salary_components` dan `employee_components`.

## 6. Catatan

* Pengaturan payroll juga dapat diubah dari **Pengaturan → Payroll**
  (`/settings?tab=payroll`).
* Metode PPh: `TER` (tarif efektif bulanan, default), `FLAT` (persen tetap),
  `PROGRESSIVE` (proyeksi setahun memakai lapisan UU HPP).
* Perubahan komponen tidak mengubah slip yang sudah `PAID` (data tersimpan pada
  `payroll_items`).
