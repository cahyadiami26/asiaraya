# 02 — Organisasi & Format ID Karyawan

> Struktur perusahaan (unit bisnis, departemen, jabatan), profil perusahaan, dan
> aturan penomoran ID karyawan otomatis.

## 1. Fungsi

* **Business unit** — kode singkat (mis. `JKT`, `SBY`), nama, radius geofence,
  status aktif. Dasar dari hampir semua filter data (presensi, payroll, jadwal).
* **Departemen** — berada di bawah satu unit bisnis, dipakai untuk penyaringan
  karyawan, target pengumuman, dan penjadwalan.
* **Jabatan (posisi)** — memiliki level dan gaji pokok default; formulir
  karyawan mengisi gaji otomatis dari jabatan yang dipilih.
* **Profil perusahaan** — nama, alamat, telepon, email, logo, dan data
  penandatangan laporan/slip (dipakai PDF).
* **Format ID karyawan** — pola penomoran otomatis dengan token
  `{COMPANY} {BU} {DEPT} {YEAR} {YY} {MONTH} {SEQ}`; cakupan nomor urut per
  unit (`BU`) atau global; contoh preview per unit.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pemilik data organisasi | Tambah/ubah/hapus unit, departemen, jabatan; atur format ID; ubah profil perusahaan |
| FIN | Pengguna data | Hanya membaca (melalui laporan/payroll); tidak punya menu organisasi |
| Karyawan | Pengguna data | Melihat unit/departemen/jabatan pada profil dan jadwalnya |

## 3. Workflow

### 3.1 Menyiapkan organisasi (awal implementasi)

```text
Profil perusahaan ──► Business unit ──► Departemen ──► Jabatan (+ gaji pokok)
        │                  │                │               │
        └── logo & ttd     └── radius GPS   └── target      └── auto-isi gaji
                                                                 di form karyawan
```

1. HR melengkapi **Pengaturan Perusahaan** (`/company`).
2. HR membuat unit bisnis beserta radius geofence lokasi kerja.
3. HR menambahkan departemen pada tiap unit dan jabatan beserta gaji pokoknya.
4. HR mengatur **format ID** di `/organisation?tab=id-format`.

### 3.2 Mengubah format ID

```text
HR buka Format ID ──► pilih token + cakupan (BU / GLOBAL) ──► simpan
                                                     │
                                                     ▼
                              counter nomor urut di-reset (id_sequences dihapus)
                                                     │
                                                     ▼
             ID berikutnya = max(nomor terpakai di data karyawan, counter) + 1
```

1. HR memilih pola, mis. `ARG-{BU}-{SEQ:4}` atau
   `{COMPANY}/{YY}{MONTH}/{BU}-{DEPT}-{SEQ:3}`.
2. Validasi memastikan format memuat `{SEQ}`, hanya karakter/token yang
   diizinkan, dan maksimal 60 karakter.
3. Saat disimpan, counter untuk cakupan tersebut dikosongkan; penerbitan
   berikutnya tetap **monoton** karena nomor terbesar yang sudah dipakai pada
   data karyawan dijadikan lantai.
4. Preview pada halaman organisasi dan formulir tambah karyawan memakai fungsi
   yang sama (`EmployeeCode::preview`), jadi contoh = ID yang akan terbit.

### 3.3 Perubahan struktur organisasi

* Penghapusan **ditolak sistem** bila masih ada relasi: unit yang masih punya
  departemen/karyawan, atau departemen yang masih punya jabatan/karyawan.
  Pesan error menyebutkan jumlah relasi yang menghalangi.
* Bila data historis (presensi, payroll) sudah ada, gunakan **penonaktifan**
  (`is_active = 0`) agar laporan lama tetap utuh.
* Menghapus unit juga membersihkan counter ID khusus unit tersebut
  (`id_sequences` dengan `scope_key = employee:BU:<id>`).

## 4. Status & aturan

| Objek | Status | Keterangan |
|-------|--------|------------|
| Business unit | `is_active` 1/0 | Unit nonaktif tidak muncul di pilihan baru |
| Departemen / jabatan | `is_active` 1/0 | Sama seperti unit |
| Format ID | — | Berlaku global; cakupan nomor `BU` atau `GLOBAL` |
| Counter (`id_sequences`) | per `scope_key` | Unik per cakupan; nomor tidak didaur ulang |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /organisation` | `OrgController@index` | Unit, departemen, jabatan, format ID |
| `POST /organisation/units` (+`PUT/DELETE`) | `OrgController@storeBusinessUnit/...` | CRUD unit |
| `POST /organisation/departments` (+`PUT/DELETE`) | `OrgController@storeDepartment/...` | CRUD departemen |
| `POST /organisation/positions` (+`PUT/DELETE`) | `OrgController@storePosition/...` | CRUD jabatan |
| `POST /organisation/id-format` | `OrgController@saveIdFormat` | Simpan format ID |
| `GET/POST /company` | `OrgController@company/saveCompany` | Profil perusahaan |

Berkas pendukung: `app/Models/OrgModel.php`, `app/Core/EmployeeCode.php`,
`app/Views/org/index.php`, `database/schema.sql` (tabel `business_units`,
`departments`, `positions`, `id_sequences`).

## 6. Catatan

* Perubahan kode unit **tidak** mengubah ID karyawan yang sudah terbit.
* Format ID dipakai bersama oleh formulir web dan konversi pelamar di rekrutmen,
  serta REST API.
* Radius geofence di unit menjadi nilai bawaan; dapat ditimpa per karyawan.
