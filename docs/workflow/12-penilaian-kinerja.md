# 12 — Penilaian Kinerja

> Siklus penilaian kinerja: periode, indikator berbobot, self assessment,
> penilaian atasan, kalibrasi HR, dan konfirmasi hasil.

## 1. Fungsi

* **Periode penilaian** (bulanan/triwulan/semester/tahunan/probation) dengan
  tanggal mulai–selesai dan status siklus.
* **Indikator penilaian** berkategori (kompetensi, hasil kerja, perilaku)
  dengan **bobot** yang totalnya 100%.
* **Self assessment** karyawan terhadap indikator.
* **Penilaian atasan** untuk anggota tim.
* **Kalibrasi HR** — penyesuaian nilai akhir, lalu status `FINAL`.
* **Rating A–E** dan rekap per departemen/unit.
* Karyawan mengonfirmasi hasil akhir (`acknowledge`) dan melihat riwayat.
* Ekspor CSV/PDF.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| HR | Pengelola siklus | Buat periode, buka/tutup, kelola indikator, kalibrasi, ekspor |
| Atasan langsung | Penilai | Menilai bawahan pada tahap penilaian atasan |
| Karyawan | Dinilai | Isi self assessment, konfirmasi hasil, lihat riwayat |

## 3. Workflow

```text
[HR] Buat periode (DRAFT) ──► indikator + bobot (total 100%)
        │
        ▼  buka periode
      SELF  ── karyawan mengisi self assessment ──► review PENDING → SELF_DONE
        │
        ▼  HR menutup tahap self (otomatis saat semua selesai / manual)
     MANAGER ── atasan menilai bawahan ──────────► SELF_DONE → MANAGER_DONE
        │
        ▼
   CALIBRATION ── HR menyesuaikan & menetapkan nilai final ──► FINAL
        │
        ▼
   CLOSED ── hasil tampil di /me/performance, karyawan acknowledge
```

1. HR membuat periode; indikator aktif dengan bobot dipakai sebagai kuesioner.
2. Pada status `SELF`, karyawan mengisi nilai diri sendiri.
3. Pada status `MANAGER`, atasan menilai bawahannya (skor + catatan).
4. Pada status `CALIBRATION`, HR dapat menyesuaikan skor/nilai akhir; status
   review menjadi `FINAL` dan rating A–E dihitung.
5. Setelah periode `CLOSED`, hasil tidak dapat diubah; karyawan mengonfirmasi.

## 4. Status

**Periode**

| Status | Arti |
|--------|------|
| `DRAFT` | Periode disiapkan, belum dibuka |
| `SELF` | Tahap self assessment |
| `MANAGER` | Tahap penilaian atasan |
| `CALIBRATION` | Tahap kalibrasi HR |
| `CLOSED` | Selesai, hasil final |

**Review karyawan**

| Status | Arti | Aktor berikutnya |
|--------|------|------------------|
| `PENDING` | Menunggu self assessment | Karyawan |
| `SELF_DONE` | Menunggu penilaian atasan | Atasan |
| `MANAGER_DONE` | Menunggu kalibrasi HR | HR |
| `FINAL` | Final, menunggu konfirmasi karyawan | Karyawan |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET /performance` | `PerformanceController@index` | Konsol HR |
| `GET/POST /performance/periods` | `PerformanceController@periods/storePeriod` | Periode |
| `POST /performance/periods/{id}/open`, `POST /performance/periods/{id}/calibration`, `POST /performance/periods/{id}/close` | `PerformanceController@openPeriod/calibration/closePeriod` | Tahap siklus |
| `GET/POST /performance/indicators` (+`PUT/DELETE`) | `PerformanceController@indicators/storeIndicator/...` | Indikator & bobot |
| `GET /performance/team`, `GET /performance/team/{id}` | `PerformanceController@team/assess` | Penilaian atasan |
| `GET /me/performance`, `POST /me/performance/{id}` | `PerformanceController@mine/submitSelf` | Self assessment |
| `POST /performance/{id}/calibrate` | `PerformanceController@calibrate` | Kalibrasi HR |
| `POST /performance/{id}/acknowledge` | `PerformanceController@acknowledge` | Konfirmasi hasil |
| `GET /performance/export` | `PerformanceController@export` | Ekspor |

Berkas pendukung: `app/Models/PerformanceModel.php`, `app/Views/performance/*`,
tabel `review_periods`, `review_indicators`, `performance_reviews`,
`performance_scores`.

## 6. Catatan

* Bobot indikator harus berjumlah 100% agar nilai akhir valid.
* Badge *Penilaian Tim* muncul untuk atasan yang masih memiliki bawahan belum
  dinilai (`SELF_DONE`).
* Hasil final dipakai pada laporan HR dan dapat dicetak.
