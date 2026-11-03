# 03 — Kepegawaian (Data Karyawan & Kontrak)

> Sumber data induk seluruh modul: identitas, penempatan, kontrak, akun login,
> dan status kepegawaian.

## 1. Fungsi

* CRUD karyawan: identitas, kontak, penempatan (unit/departemen/jabatan), gaji
  pokok, status kepegawaian, data bank/pajak/BPJS, atasan langsung, foto.
* **ID karyawan otomatis** mengikuti format organisasi (dapat diubah manual).
* Akun login: role (`HR`/`FIN`/`KARYAWAN`), username, kata sandi awal.
* **Kontrak**: tanggal mulai/berakhir, penanda kontrak akan berakhir (H-45),
  status otomatis `EXPIRED` saat kontrak lewat.
* **Resign**: menonaktifkan akun dan mencatat tanggal/alasan.
* Pencarian, filter unit/departemen/status, pagination, dan ekspor daftar.
* Menampilkan ringkasan presensi & slip terakhir pada halaman detail karyawan.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pemilik data | Tambah, ubah, nonaktifkan (resign), atur role & atasan langsung |
| FIN | Pengguna data | Melihat data lewat payroll/laporan (tidak membuka `/employees`) |
| Karyawan | Subjek data | Melihat & memperbarui profil sendiri di `/me/profile` |

## 3. Workflow

### 3.1 Menambah karyawan

```text
HR buka /employees/create
   │
   ├─ pilih business unit ──► ID karyawan otomatis menyesuaikan format
   │                          (badge + kolom ID ikut berubah)
   ├─ pilih departemen & jabatan ──► gaji pokok terisi otomatis
   ├─ isi identitas, email perusahaan, atasan langsung, role
   └─ simpan
        │
        ▼
  ID final diterbitkan dari counter (dijamin belum terpakai)
        │
        ├─ akun login dibuat (kata sandi awal: isian HR, atau `Password123!`)
        ├─ notifikasi “Selamat datang di HRIS” dikirim
        └─ audit log employee.create
```

1. HR membuka formulir; unit kerja sudah terpilih (unit pertama/default) dan
   sistem menampilkan preview ID sesuai format & unit.
2. Bila HR mengubah unit/departemen, ID diperbarui otomatis
   (`GET /employees/next-code` mengembalikan `{code, format}`).
3. **Mode otomatis** (bawaan): kolom ID hanya menampilkan preview; saat simpan
   sistem menerbitkan nomor final dari counter + lantai nomor terpakai, jadi
   dua formulir yang dibuka bersamaan tidak mungkin mendapat ID sama.
4. **Mode manual**: HR mengosongkan/mengubah ID → nilai itu dipakai apa adanya
   setelah dicek belum terpakai; bila sudah ada, penyimpanan ditolak.
5. Setelah tersimpan, karyawan dapat langsung login (atau lewat Google SSO).

### 3.2 Mengubah data & kontrak

1. HR membuka `/employees/{id}/edit`.
2. Perubahan unit/departemen/jabatan/gaji tercatat sebagai riwayat jabatan bila
   HR mencatatnya di modul **Riwayat Jabatan** (lihat berkas 16).
3. Kontrak berakhir: `refreshContractFlags()` menandai `EXPIRED` untuk kontrak
   yang sudah lewat; daftar “Kontrak & Status” menampilkan yang akan berakhir.

### 3.3 Resign / berhenti

```text
HR buka detail karyawan ──► tombol Nonaktifkan (resign)
        │
        ├─ isi tanggal resign + alasan
        ▼
flag = RESIGN, resign_date disimpan, sesi login dicabut pada login berikutnya
        │
        └─ audit log employee.resign
```

Untuk proses serah terima lengkap (aset, clearance, BAST), gunakan modul
**Offboarding** (berkas 15) — modul ini hanya menonaktifkan data kepegawaian.

## 4. Status & kondisi

| Field | Nilai | Arti |
|-------|-------|------|
| `flag` | `ACTIVE` | Karyawan aktif (default) |
| | `RESIGN` | Berhenti / dinonaktifkan HR |
| | `EXPIRED` | Kontrak berakhir otomatis |
| `role` | `HR`, `FIN`, `KARYAWAN` | Menentukan menu & hak akses |
| `employment_status` | `PKWTT`, `PKWTT (Probation)`, `PKWT`, `PKWT (Harian)`, `Magang`, `Outsourcing` | Jenis hubungan kerja |
| `contract_end` | tanggal | Kontrak berjangka wajib memiliki tanggal akhir |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /employees` | `EmployeeController@index` | Direktori + filter |
| `GET /employees/contracts` | `EmployeeController@index` (preset filter) | Kontrak akan berakhir |
| `GET /employees/create`, `POST /employees` | `EmployeeController@create/store` | Tambah karyawan |
| `GET /employees/next-code?unit=&department=` | `EmployeeController@nextCode` | Preview ID (JSON) |
| `GET /employees/{id}` | `EmployeeController@show` | Detail + ringkasan |
| `GET /employees/{id}/edit`, `POST/PUT /employees/{id}` | `EmployeeController@edit/update` | Ubah data |
| `POST /employees/{id}/resign` | `EmployeeController@resign` | Nonaktifkan |

Berkas pendukung: `app/Models/EmployeeModel.php`, `app/Core/EmployeeCode.php`,
`app/Views/employees/*`, tabel `employees`.

## 6. Integrasi & catatan

* Data karyawan dipakai semua modul: presensi (jadwal & geofence), payroll
  (gaji pokok, status pajak), cuti (saldo), kinerja, pelatihan, dokumen.
* Role karyawan menentukan menu; perubahan role langsung berlaku pada login
  berikutnya.
* Karyawan tidak dapat menonaktifkan akunnya sendiri (dicek di controller).
* ID karyawan unik di tingkat basis data (`uq_employee_code`); perubahan format
  hanya mengubah pola, bukan ID yang sudah terbit.
