# 14 — Rekrutmen & Onboarding (ATS)

> Lowongan multi-unit, pipeline pelamar, wawancara, penawaran, dan konversi
> pelamar diterima menjadi karyawan.

## 1. Fungsi

* **Lowongan (job vacancy)**: kode, judul, unit, departemen, posisi, kuota,
  jenis kerja (onsite/hybrid/remote), jenis kontrak, hiring manager, tanggal
  buka–tutup.
* **Pipeline pelamar**: `APPLIED → SCREENING → INTERVIEW → OFFER → HIRED`
  (atau `REJECTED` / `WITHDRAWN`).
* **Data pelamar**: kontak, CV, sumber, catatan, rating, jadwal & pewawancara.
* **Konversi otomatis** pelamar `OFFER` menjadi karyawan: ID otomatis, akun
  login, penempatan, kontrak, notifikasi selamat datang.
* Papan pipeline per lowongan, daftar pelamar, ekspor CSV, statistik.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pemilik proses | CRUD lowongan, kelola pelamar, wawancara, putuskan, konversi menjadi karyawan |
| Hiring manager | Pengguna data | Menerima notifikasi pelamar baru/wawancara; akses baca terbatas |
| Karyawan/FIN | — | Tidak memiliki akses modul rekrutmen |

## 3. Workflow

```text
[HR] Buat lowongan ──► DRAFT ──► OPEN (tampil sebagai lowongan aktif)
                                     │
                                     ├─ pelamar masuk (APPLIED)
                                     ▼
                            SCREENING ──► INTERVIEW ──► OFFER
                                 │            │            │
                                 └─ tolak ────┴─ tolak ────┴─► REJECTED
                                              │
                                              └─ pelamar mundur ──► WITHDRAWN
                                                           │
                                        [HR] Hire (konversi)▼
                                                       HIRED
                                                           │
                     ┌─────────────────────────────────────┘
                     ▼
     karyawan baru: ID otomatis sesuai format, akun login,
     riwayat jabatan awal, notifikasi “Selamat datang”
```

1. HR membuat lowongan; status `OPEN` menandakan lowongan aktif, `ON_HOLD`
   menahan proses, `CLOSED` menutup lowongan.
2. Pelamar dipindahkan antar-tahap (`POST /recruitment/candidates/{id}/stage`);
   setiap perpindahan tercatat dengan waktu.
3. Wawancara: jadwal, pewawancara, rating, dan catatan disimpan pada pelamar.
4. Pelamar di tahap `OFFER` dapat dikonversi menjadi karyawan
   (`POST /recruitment/candidates/{id}/hire`).
5. Konversi memakai `EmployeeCode::nextUnique()` sehingga ID selalu sesuai
   format organisasi dan tidak bentrok.

## 4. Status

**Lowongan**

| Status | Arti |
|--------|------|
| `DRAFT` | Disiapkan, belum dibuka |
| `OPEN` | Dibuka, menerima pelamar |
| `ON_HOLD` | Ditahan sementara |
| `CLOSED` | Ditutup |

**Pelamar**

| Status | Arti |
|--------|------|
| `APPLIED` | Pelamar baru |
| `SCREENING` | Seleksi berkas |
| `INTERVIEW` | Wawancara |
| `OFFER` | Penawaran |
| `HIRED` | Diterima (sudah dikonversi menjadi karyawan) |
| `REJECTED` / `WITHDRAWN` | Ditolak / mengundurkan diri |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /recruitment` | `RecruitmentController@index` | Daftar lowongan |
| `POST /recruitment` | `RecruitmentController@storeJob` | Buat lowongan |
| `POST/PUT /recruitment/jobs/{id}`; `POST/DELETE /recruitment/jobs/{id}/delete` | `RecruitmentController@updateJob/destroyJob` | Ubah/hapus |
| `POST /recruitment/jobs/{id}/open`, `POST /recruitment/jobs/{id}/hold`, `POST /recruitment/jobs/{id}/close` | `RecruitmentController@openJob/holdJob/closeJob` | Status lowongan |
| `GET /recruitment/candidates` | `RecruitmentController@candidates` | Daftar pelamar |
| `GET /recruitment/candidates/{id}` | `RecruitmentController@candidate` | Detail pelamar |
| `POST /recruitment/candidates/{id}/stage` | `RecruitmentController@moveStage` | Pindah tahap |
| `POST /recruitment/candidates/{id}/hire` | `RecruitmentController@hire` | Konversi menjadi karyawan |
| `GET /recruitment/export` | `RecruitmentController@export` | Ekspor CSV |

Berkas pendukung: `app/Models/RecruitmentModel.php`,
`app/Views/recruitment/*`, tabel `job_vacancies`, `candidates`,
`candidate_stages`.

## 6. Catatan

* Lowongan otomatis berstatus `CLOSED` saat HR menutupnya setelah kuota
  terpenuhi (kuota & jumlah diterima ditampilkan pada papan pipeline).
* Email pelamar menjadi kunci saat konversi; email yang sudah dipakai karyawan
  lain akan ditolak sehingga HR memperbarui email pelamar terlebih dahulu.
* Notifikasi rekrutmen ke hiring manager non-HR masih menautkan halaman khusus
  HR (temuan terbuka pada rilis 2.17.3).
