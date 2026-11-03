# 01 — Autentikasi & Akun

> Login aman (kata sandi atau Google), sesi terbatas, pemulihan kata sandi
> mandiri, dan pengelolaan profil pribadi.

## 1. Fungsi

* Login dengan **NIK (kode karyawan), email, atau username + kata sandi** (`POST /login`).
* **Google Sign-In** (`/login/google`) — akun Google dihubungkan ke kolom
  `employees.email`, sehingga hanya email karyawan terdaftar yang bisa masuk.
* Sesi aman: batas umur sesi **8 jam**, idle **2 jam**, opsi *ingat perangkat*
  **30 hari**, dan proteksi brute-force (**5** percobaan gagal → jeda **15 menit**
  per akun/IP).
* **Lupa kata sandi** — tautan sekali pakai dikirim lewat email
  (`/forgot-password`, `/reset-password`).
* Ganti kata sandi dari profil dengan verifikasi kata sandi lama.
* Profil pribadi: foto, kontak, alamat, data rekening/BPJS/NPWP
  (`/me/profile`).
* Logout (`POST /logout`) menghapus sesi aktif.

## 2. Role yang berperan

| Role | Peran dalam modul | Hak |
|------|-------------------|-----|
| Semua (belum login) | Pengguna aplikasi | Login, Google SSO, lupa kata sandi |
| Semua (sudah login) | Pemilik akun | Ubah profil & kata sandi sendiri, logout |

## 3. Workflow

### 3.1 Login kata sandi

```text
Buka /login ──► isi identifier + password ──► validasi
     │ invalid (3x+)                       │ valid
     ▼                                     ▼
pesan error / jeda brute-force        sesi dibuat (role dibaca dari employees.role)
                                           │
                                           ▼
                        redirect /dashboard (HR/Finance/Karyawan)
```

1. Pengguna membuka `/login` dan mengisi identifier (NIK, email, atau username).
2. Sistem memverifikasi kata sandi (`password_hash`) dan status karyawan
   (`flag = ACTIVE`); akun resign/expired tidak dapat login.
3. Bila “Ingat perangkat” dicentang, cookie pengingat 30 hari disimpan.
4. Setelah login, pengguna diarahkan ke dashboard sesuai role.

### 3.2 Login Google

1. Pengguna menekan **Masuk dengan Google** → redirect ke Google OAuth.
2. Callback mengambil email Google, lalu mencari karyawan aktif dengan email
   yang sama (`employees.email`).
3. Bila cocok dan `google_id` belum ada, `google_id` diikatkan; bila email tidak
   terdaftar, login ditolak dengan pesan agar menghubungi HR.

### 3.3 Lupa kata sandi

```text
/forgot-password ──► email terdaftar? ──► buat token sekali pakai (kedaluwarsa)
        │ tidak                                │
        ▼                                      ▼
pesan netral (tanpa bocor data)         email berisi tautan /reset-password?token=…
                                               │
                                               ▼
                                   set kata sandi baru ──► token dihapus
```

### 3.4 Ganti kata sandi & profil

1. Pengguna membuka `/me/profile`.
2. Perubahan profil disimpan ke data karyawan; unggah foto dibatasi ukuran dan
   diperkecil otomatis.
3. Ganti kata sandi wajib menyertakan kata sandi lama dan minimal 8 karakter.

## 4. Status & kondisi

| Kondisi | Arti |
|---------|------|
| `flag = ACTIVE` | Akun boleh login |
| `flag = RESIGN` / `EXPIRED` | Akun tidak boleh login (pesan generik) |
| Sesi kedaluwarsa | Idle > 2 jam atau total > 8 jam → diarahkan ke `/login` |
| Percobaan gagal berulang | Diblokir sementara (proteksi brute-force) |

## 5. Rute & berkas kode

| Rute | Controller@method | Keterangan |
|------|-------------------|------------|
| `GET/POST /login` | `AuthController@showLogin/login` | Login kata sandi |
| `GET /login/google`, `/login/google/callback` | `AuthController@googleRedirect/googleCallback` | Google SSO |
| `GET/POST /forgot-password` | `AuthController@showForgot/sendReset` | Permintaan reset |
| `GET/POST /reset-password` | `AuthController@showReset/resetPassword` | Set sandi baru |
| `POST /logout` | `AuthController@logout` | Akhiri sesi |
| `GET/POST /me/profile` | `DashboardController@profile/updateProfile` | Profil pribadi |

Berkas pendukung: `app/Core/Auth.php`, `app/Core/GoogleAuth.php`,
`app/Models/EmployeeModel.php`, `app/Views/auth/*`.

## 6. Catatan

* Email reset kata sandi dikirim lewat **antrean email** (status & riwayat di
  `/mail`); bila SMTP belum aktif, tautan tetap dicatat di
  `storage/logs/password-reset.log`. Lihat [21-antrean-email.md](21-antrean-email.md).
* Google SSO memakai email sebagai kunci — HR **wajib** memakai email perusahaan
  yang benar saat membuat karyawan.
* Semua percobaan login (berhasil/gagal) dan reset kata sandi tercatat di log
  aktivitas HR.
* Aplikasi Android memakai jalur token terpisah (`/api/v1/auth/*`), lihat
  [19-rest-api-android.md](19-rest-api-android.md).
