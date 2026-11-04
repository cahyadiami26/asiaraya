# Dokumentasi Modul & Workflow — HRIS PT Asia Raya Group

Folder ini menjelaskan **fungsi setiap modul/fitur**, **alur kerja (workflow)**
yang dijalankan, dan **role pengguna yang berperan** di dalamnya.

Dokumen ditulis mengikuti implementasi di kode (route, controller, model, dan
tabel status), sehingga bisa dipakai sebagai acuan operasional, bahan training
HR/Finance, maupun dasar pengujian (UAT).

## Daftar modul

| No | Modul / Fitur | Berkas | Role utama |
|----|---------------|--------|------------|
| 01 | Autentikasi & akun | [01-autentikasi-dan-akun.md](01-autentikasi-dan-akun.md) | Semua |
| 02 | Organisasi & format ID karyawan | [02-organisasi-dan-format-id.md](02-organisasi-dan-format-id.md) | HR |
| 03 | Kepegawaian (data karyawan, kontrak) | [03-kepegawaian.md](03-kepegawaian.md) | HR |
| 04 | Shift & penjadwalan | [04-shift-dan-jadwal.md](04-shift-dan-jadwal.md) | HR, Karyawan, atasan |
| 05 | Presensi (GPS + selfie) | [05-presensi.md](05-presensi.md) | Karyawan, HR |
| 06 | Lembur (overtime) | [06-lembur.md](06-lembur.md) | Karyawan, atasan, HR |
| 07 | Cuti & saldo cuti | [07-cuti.md](07-cuti.md) | Karyawan, atasan, HR |
| 08 | Komponen gaji | [08-komponen-gaji.md](08-komponen-gaji.md) | HR |
| 09 | Payroll & slip gaji | [09-payroll.md](09-payroll.md) | HR, FIN, Karyawan |
| 10 | Klaim & reimbursement | [10-klaim-reimbursement.md](10-klaim-reimbursement.md) | Karyawan, atasan, HR/FIN |
| 11 | Pengumuman & notifikasi | [11-pengumuman-dan-notifikasi.md](11-pengumuman-dan-notifikasi.md) | HR, semua |
| 12 | Penilaian kinerja | [12-penilaian-kinerja.md](12-penilaian-kinerja.md) | Karyawan, atasan, HR |
| 13 | Pelatihan & sertifikasi | [13-pelatihan-dan-sertifikasi.md](13-pelatihan-dan-sertifikasi.md) | HR, Karyawan |
| 14 | Rekrutmen & onboarding | [14-rekrutmen-dan-onboarding.md](14-rekrutmen-dan-onboarding.md) | HR |
| 15 | Offboarding & aset/BAST | [15-offboarding-dan-aset.md](15-offboarding-dan-aset.md) | Karyawan, atasan, HR, FIN |
| 16 | Dokumen & riwayat jabatan | [16-dokumen-dan-riwayat-jabatan.md](16-dokumen-dan-riwayat-jabatan.md) | HR, Karyawan |
| 17 | Pajak PPh 21 & BPJS | [17-pajak-dan-bpjs.md](17-pajak-dan-bpjs.md) | HR, FIN |
| 18 | Laporan & log aktivitas | [18-laporan-log-email-cron.md](18-laporan-log-email-cron.md) | HR, FIN |
| 19 | REST API `/api/v1` (Android) | [19-rest-api-android.md](19-rest-api-android.md) | Semua (token) |
| 20 | Menu navigasi & RBAC | [20-menu-navigasi-dan-rbac.md](20-menu-navigasi-dan-rbac.md) | HR |
| 21 | Antrean email (email queue) | [21-antrean-email.md](21-antrean-email.md) | HR |
| 22 | Penjadwal & cron | [22-penjadwal-dan-cron.md](22-penjadwal-dan-cron.md) | HR |

## Role pengguna

Sistem hanya menyimpan **tiga role** pada kolom `employees.role`. Peran seperti
“atasan langsung”, “hiring manager”, atau “pemilik item serah terima” bersifat
**relasional** (ditentukan oleh data, bukan role baru).

| Role | Kode | Cakupan data | Contoh pengguna |
|------|------|--------------|-----------------|
| HR | `HR` | Seluruh perusahaan: kepegawaian, jadwal, presensi, cuti, lembur, payroll (proses), kinerja, pelatihan, rekrutmen, offboarding, dokumen, pengaturan | `hr@asiaraya.co.id` |
| Finance | `FIN` | Payroll (persetujuan & pembayaran), klaim (persetujuan & pembayaran), pajak/BPJS, laporan; ditambah layanan mandiri karyawan | `finance@asiaraya.co.id` |
| Karyawan | `KARYAWAN` | Layanan mandiri (profil, presensi, jadwal, lembur, cuti, klaim, slip gaji, dokumen, bukti potong) + persetujuan tim bila menjadi atasan langsung | `hendra@asiaraya.co.id` |

**Peran relasional:**

| Istilah | Definisi | Dipakai pada |
|---------|----------|--------------|
| Atasan langsung | `employees.direct_manager_id` menunjuk ke karyawan tersebut | Approval tahap 1 cuti/lembur/perubahan jadwal/klaim/offboarding/penilaian kinerja |
| Bawahan | Karyawan aktif dengan `direct_manager_id` = id pengguna | Menu “Persetujuan Tim”, badge antrean tim |
| Hiring manager | `job_vacancies.hiring_manager_id` | Notifikasi rekrutmen |
| Pemilik item clearance | `offboarding_clearance.owner_role` (`HR`, `MANAGER`, `IT`, `FINANCE`) | Serah terima offboarding |
| Penerima target pengumuman | Unit / departemen / jabatan / role / karyawan tertentu | Pengumuman |

## Pola approval umum

Sebagian besar modul memakai **approval dua tahap** yang dijalankan oleh mesin
yang sama (`App\Core\ApprovalEngine`) untuk cuti, lembur, dan perubahan jadwal:

```text
[Karyawan] ajukan ──► PENDING ──► [Atasan langsung] setujui ──► MANAGER_APPROVED
                          │                                   │
                          │ tolak                             │ [HR] setujui
                          ▼                                   ▼
                      REJECTED ◄──── tolak ────────────── APPROVED (final)
```

Aturan yang berlaku di semua modul approval:

1. Pemohon **tidak boleh** memutus pengajuannya sendiri (dicek di engine).
2. Tahap 1 hanya untuk atasan langsung dan hanya saat status `PENDING`.
3. Tahap 2 hanya untuk HR dan hanya saat status `PENDING`/`MANAGER_APPROVED`
   (HR boleh mengambil alih bila atasan belum memutus) — **kecuali** pengajuan
   milik rekan HR yang punya atasan: pengajuan itu harus menunggu atasan lebih
   dahulu, HR lain tidak boleh melompati tahap 1.
4. Penolakan pada tahap mana pun mengakhiri pengajuan (`REJECTED`) dan
   mengirim notifikasi ke pemohon.
5. Setiap keputusan dicatat pada `activity_logs` dan memicu notifikasi in-app.

Modul dengan alur sedikit berbeda dijelaskan pada berkasnya masing-masing:
klaim (atasan → HR/Finance → pembayaran), payroll (HR → Finance → PAID),
rekrutmen (pipeline pelamar), offboarding (atasan → HR → serah terima).

### Bila yang mengajukan adalah HR sendiri

HR adalah approver final, sehingga tanpa aturan khusus pengajuan HR akan
menunggu persetujuan dirinya sendiri. Aturan yang dipakai:

**Aturan utama: pengajuan milik HR selesai di atasan langsung, tidak menunggu
HR.** Sebab tahap final hanya boleh diputus pemegang peran HR, sedangkan pemohon
tidak boleh menyetujui pengajuannya sendiri — berapa pun jumlah akun HR yang
ada, tahap itu tidak akan pernah bisa diputus orang yang tepat.

| Kondisi | Perilaku |
|---------|----------|
| HR punya atasan langsung (jumlah HR lain tidak berpengaruh) | Persetujuan atasan langsung = **final** (`APPROVED`); `hr_id` dibiarkan kosong, audit mencatat `final_by: MANAGER`, saldo/shift/kompensasi langsung dihitung |
| HR belum punya atasan langsung, ada HR lain aktif | Tahap atasan dilewati; HR lain memutus dari `PENDING` |
| HR belum punya atasan langsung **dan** tidak ada HR lain | Pengajuan ditolak saat dibuat dengan pesan agar atasan langsung ditetapkan lebih dulu |
| Atasan langsung nonaktif atau menunjuk diri sendiri | Dianggap tidak ada atasan (aturan di atas berlaku) |
| Sudah telanjur `MANAGER_APPROVED` sebelum aturan ini berlaku | Diselesaikan otomatis oleh migrasi skema 24 (`ApprovalEngine::finalizeLegacy()`): status menjadi `APPROVED` beserta efek sampingnya |

Prinsip yang sama dipakai klaim dengan approver final **HR atau Finance**
(pengajuan milik HR/Finance berhenti di atasan), dan offboarding (persetujuan
atasan langsung langsung membuka `IN_PROGRESS` bila pemohon HR). Rincian per modul ada di
[04](04-shift-dan-jadwal.md), [06](06-lembur.md), [07](07-cuti.md),
[10](10-klaim-reimbursement.md), dan [15](15-offboarding-dan-aset.md).

Kotak masuk persetujuan (`/approvals`, `/leave`, `/overtime`,
`/schedules/requests`, `/claims`) tidak menampilkan pengajuan sendiri, dan
tombol keputusan hanya muncul bila pengguna benar-benar berhak memutus tahap
saat ini.

## Konvensi status

| Istilah | Arti |
|---------|------|
| `PENDING` / `SUBMITTED` | Menunggu keputusan atasan langsung |
| `MANAGER_APPROVED` | Disetujui atasan, menunggu HR (atau HR/Finance untuk klaim) |
| `APPROVED` | Final, siap dipakai modul lain (presensi, payroll) |
| `REJECTED` | Ditolak, tidak dapat diproses lagi |
| `CANCELLED` / `WITHDRAWN` | Dibatalkan pemohon sebelum/sesudah diproses |
| `SCHEDULED` | Klaim sudah disetujui dan akan masuk payroll |
| `PAID` | Sudah dibayarkan (gaji/klaim) |

## Cara memakai dokumen ini

* **Operasional** — ikuti bagian *Workflow* pada modul terkait; setiap langkah
  menyebut role pelakunya.
* **Pengujian/UAT** — gunakan tabel *Status* sebagai daftar kondisi yang harus
  dicoba, termasuk jalur penolakan dan pembatalan.
* **Pengembangan** — bagian *Rute & berkas kode* memetakan halaman ke controller
  dan model; perubahan status wajib disinkronkan dengan dokumen ini.

> Dokumen ini melengkapi `readme.md` (ringkasan batch & catatan rilis) dan
> `docs/openapi.yaml` (kontrak REST API untuk aplikasi Android).
