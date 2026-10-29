<?php
/**
 * Konfigurasi tampilan menu samping (sidebar).
 *
 * Dipakai oleh `App\Core\Nav` untuk menyembunyikan item/grup menu tanpa
 * mengubah kode. Isi berkas ini adalah **nilai bawaan**; HR juga dapat
 * mengubahnya dari aplikasi lewat **Pengaturan → Menu Navigasi**
 * (`/settings?tab=menu`). Kedua sumber digabung: item disembunyikan bila
 * terdaftar di sini **atau** di pengaturan aplikasi.
 *
 * Penting: menyembunyikan menu hanya mengubah navigasi. Hak akses data tetap
 * dikendalikan RBAC (role HR / FIN / KARYAWAN) dan pemeriksaan di controller,
 * sehingga URL yang disembunyikan tetap tidak bisa dibuka oleh peran yang
 * tidak berhak — tetapi peran yang berhak masih bisa membukanya lewat URL.
 *
 * Kunci item = nama route (mis. `payroll.index`). Beberapa item memakai kunci
 * khusus karena berbagi route yang sama untuk peran berbeda:
 *   - `leave.manage`    → "Manajemen Cuti" (HR)
 *   - `leave.approvals` → "Persetujuan Cuti Tim" (Karyawan)
 *
 * Kunci grup: main, org, hr, ops, payroll, tax, recruit, performance,
 * offboarding, comms, portal, system.
 *
 * Item pada `locked` (ditambah Dashboard dan Pengaturan HR) tidak akan pernah
 * disembunyikan agar HR tidak kehilangan jalan kembali ke halaman pengaturan.
 */
return [
    /* Contoh: sembunyikan seluruh grup rekrutmen & menu pelatihan.
     * 'hidden_groups' => ['recruit'],
     * 'hidden'        => ['performance.indicators', 'trainings.catalog'],
     */
    'hidden'        => [],
    'hidden_groups' => [],

    // Kunci yang selalu tampil (tidak dapat dimatikan dari UI maupun di sini).
    'locked'        => ['dashboard', 'settings.index'],
];
