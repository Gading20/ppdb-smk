# Database Role Refactoring — Walkthrough

## Tujuan
Menggabungkan 4 tabel autentikasi terpisah (`admin`, `kepsek`, `wali_kelas`, `siswa`) menjadi satu tabel `users` terpusat dengan kolom `role`.

## Perubahan Database

### Tabel Baru
| Tabel | Deskripsi |
|---|---|
| `roles` | Master role: admin, siswa, kepsek, wali_kelas |
| `users` | Autentikasi terpusat. Menggantikan tabel `admin` dan `kepsek` |

### Tabel Lama (Dipertahankan)
| Tabel | Alasan |
|---|---|
| `siswa` | FK di `absensi`, `pelanggaran`, `konseling` |
| `wali_kelas` | Data kelas/jurusan; diperlukan oleh dashboard wali kelas |

Tabel `siswa` dan `wali_kelas` **ditambahkan kolom `user_id`** yang mereferensikan `users.id`.

## File PHP yang Diperbarui

| File | Perubahan |
|---|---|
| [admin/login.php](file:///c:/laragon/www/absensi_siswa/admin/login.php) | Query ke `users WHERE role='admin'` |
| [admin/logout.php](file:///c:/laragon/www/absensi_siswa/admin/logout.php) | Cleanup session |
| [admin/profil/index.php](file:///c:/laragon/www/absensi_siswa/admin/profil/index.php) | Semua query ke `users` |
| [admin/dashboard/index.php](file:///c:/laragon/www/absensi_siswa/admin/dashboard/index.php) | Activity log JOIN ke `users` |
| [admin/absensi/addk.php](file:///c:/laragon/www/absensi_siswa/admin/absensi/addk.php) | Admin name query ke `users` |
| [admin/absensi/detailp.php](file:///c:/laragon/www/absensi_siswa/admin/absensi/detailp.php) | JOIN `users` untuk nama pencatat |
| [admin/absensi/detailk.php](file:///c:/laragon/www/absensi_siswa/admin/absensi/detailk.php) | JOIN `users` untuk nama pencatat |
| [admin/absensi/editk.php](file:///c:/laragon/www/absensi_siswa/admin/absensi/editk.php) | JOIN `users` untuk nama pencatat |
| [kepsek/login.php](file:///c:/laragon/www/absensi_siswa/kepsek/login.php) | Query ke `users WHERE role='kepsek'` |
| [kepsek/logout.php](file:///c:/laragon/www/absensi_siswa/kepsek/logout.php) | Cleanup session |
| [wali_kelas/login.php](file:///c:/laragon/www/absensi_siswa/wali_kelas/login.php) | JOIN `users` + `wali_kelas` untuk data kelas |
| [wali_kelas/logout.php](file:///c:/laragon/www/absensi_siswa/wali_kelas/logout.php) | Cleanup semua session key |
| [siswa/login.php](file:///c:/laragon/www/absensi_siswa/siswa/login.php) | Tetap pakai tabel `siswa` (NIS-based) + update `users.last_login` |

## Hasil Verifikasi Login

| Role | Username | Password | Status |
|---|---|---|---|
| Admin | `admin` | `admin123` | ✅ Dashboard berhasil |
| Kepsek | `kepsek` | `password` | ✅ Dashboard berhasil |
| Wali Kelas | `Supri` | `password` | ✅ Dashboard berhasil |
| Siswa | `2024002` (NIS) | `siswa_2024002` | ✅ Dashboard berhasil |

## Kredensial Default
- **Admin**: `admin` / `admin123`
- **Kepsek**: `kepsek` / `password`
- **Wali Kelas**: `Supri`, `Gading`, `Emma`, `Indrawan` / `password`
- **Siswa**: NIS `2024002`–`2024026` / `siswa_<NIS>` atau sesuai data

![Login Admin Dashboard](file:///C:/Users/gadin/.gemini/antigravity/brain/8d54b9e9-4170-498a-92a8-ce31972375ac/final_admin_kepsek_test_1774621539165.webp)
