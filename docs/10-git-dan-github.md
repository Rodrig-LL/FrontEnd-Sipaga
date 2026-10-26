# Git dan GitHub

Remote: `origin` → `https://github.com/Rodrig-LL/FrontEnd-Sipaga.git` (publik, akun pribadi). Cabang utama: `main`.

## Sebelum commit dan push

1. Jalankan tes di **kedua** database ([09-pengujian.md](09-pengujian.md)): `php artisan test` (SQLite) dan sekali di MariaDB.
2. Pastikan tidak ada yang terlarang ikut ke git:
   ```bash
   git ls-files | grep -Ei "(^|/)\.env$|\.sqlite|\.venv|hasil\.json|\.zip$|personel\.db" || echo "(tidak ada)"
   ```
3. Commit **per bagian**, pesan berbahasa Indonesia yang menjelaskan *alasan* perubahan. Perubahan yang bisa dibatalkan terpisah sebaiknya di commit terpisah
   (contoh: rename Dashboard dan desain ulang `/masuk` dipisah).

## Yang tidak boleh ikut ke repo

`.env` (kata sandi database dan `APP_KEY`), data asli juru bayar (DPP, nominatif, pembukuan, `OTOMASI_GAJI_DPP.zip`, `personel.db`), `*.sqlite`,
`python/.venv`, `storage/` (berkas unggahan dan hasil proses), dan `public/build`. Repo ini **publik**: semua yang di-push terlihat siapa pun,
termasuk email penulis commit. `tests/fixtures/` ada di `.gitignore` (berkas uji tidak disimpan di repo).

## Push lewat HTTPS dengan token

GitHub tidak menerima kata sandi akun untuk push; pakai **Personal Access Token**.

1. GitHub → Settings → Developer settings → Personal access tokens → *Fine-grained tokens* → Generate new token.
   - *Resource owner*: akun pemilik repo. *Repository access*: **Only select repositories** → `FrontEnd-Sipaga`.
   - *Repository permissions* → **Contents: Read and write**. Masa berlaku pendek (mis. 7 hari).
2. Simpan kredensial di memori saja (tidak di disk) dan push **dari folder repo** (atau pakai `git -C`):
   ```bash
   git config --local credential.helper 'cache --timeout=3600'
   git -C /home/fadhlan/Documents/FrontEnd-Sipaga push origin main
   ```
   Saat diminta *Password*, tempel token (tidak ada tulisan yang muncul). Isian *Username* tidak menentukan akun; tokenlah yang menentukan.
3. **Hapus token** di GitHub setelah selesai. Jangan menaruhnya di URL remote (`https://USER:TOKEN@...`), di chat, atau di berkas.

## Galat yang sering muncul

| Pesan | Arti dan perbaikan |
|---|---|
| `fatal: not a git repository` | terminal sedang di luar folder repo; `cd` ke repo, atau pakai `git -C <folder-repo> ...` |
| `Permission to X denied to Y` (403) | token dikenali sebagai akun **Y** tetapi tidak berizin tulis ke repo X: cek *Repository access* dan *Contents: Read and write*; token klasik cukup dengan `public_repo` |
| `Authentication failed` | token salah, kedaluwarsa, atau sudah dihapus; buat yang baru |
| push ke `main` ditolak (protected branch) | pakai cabang lalu pull request |

## Alur cabang

- Pekerjaan besar: cabang (`backend-sipaga`) → pull request → merge ke `main`.
- Perubahan kecil: boleh langsung `main` (repo pribadi tanpa kolaborator). Bila ada orang lain, pakai cabang dan pull request.
- Menghapus cabang di GitHub setelah di-merge: `git push origin --delete <nama-cabang>`; lokal: `git branch -d <nama-cabang>`.
- Kebiasaan yang dipakai: `main` lokal diselaraskan dengan cabang kerja lewat fast-forward (`git merge --ff-only`); jangan `push --force` ke `main`.
