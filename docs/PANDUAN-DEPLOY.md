# Panduan: Transfer Repo ke Organization + Auto‑Deploy ke Server (GitHub Actions)

Dokumen ini adalah **walkthrough yang bisa dipakai ulang** untuk menyiapkan pipeline seperti di repo ini pada repo lain:

1. **Memindahkan repo** dari akun pribadi ke **organization** (opsional: mengganti nama).
2. **Auto‑deploy ke server** setiap `push` ke `main` lewat **GitHub Actions → SSH**.

Alur yang dihasilkan:

```
push ke main ─▶ [ Job "verify" ]  build bersih di runner (gerbang)
                     │  gagal ─▶ STOP (server tak disentuh)
                     ▼  hijau
               [ Job "deploy" ] ─ SSH ─▶ server: git pull + build + cache + restart
```

> **Ganti placeholder** ini sesuai repo/server Anda:
>
> | Placeholder | Contoh di repo ini | Arti |
> |---|---|---|
> | `<ORG>` | `Kisantra` | nama organization GitHub |
> | `<REPO>` | `project-management` | nama repo (boleh baru setelah rename) |
> | `<SERVER_HOST>` | `dev.kisantra.com` | host/IP server |
> | `<SSH_USER>` | `root` / `kisantra-dev` | user SSH deploy di server |
> | `<APP_DIR>` | `/home/kisantra-dev/htdocs/coal-management` | folder aplikasi di server |
> | `<SSH_PORT>` | `22` | port SSH |
> | `<BRANCH>` | `main` | branch produksi |

---

## Prasyarat

- Anda **admin** repo asal (untuk transfer) dan **owner/admin** organization tujuan.
- Server sudah bisa diakses via **SSH** dan menjalankan aplikasi (untuk stack ini: **PHP + Composer + Node/npm + Git**).
- Repo sudah **ter‑clone di server** di `<APP_DIR>` dan `.env` produksi sudah terisi (jangan pernah commit `.env`).
- Aplikasi sudah pernah berjalan di server (deploy pertama = memutakhirkan, bukan setup dari nol).

---

## A. Transfer repo pribadi → organization

1. Buka repo di GitHub → **Settings** → scroll ke **Danger Zone** → **Transfer ownership**.
2. Ketik nama repo untuk konfirmasi, lalu masukkan owner baru = **`<ORG>`**. Transfer.
3. *(Opsional)* Ganti nama repo: **Settings → General → Repository name** → `<REPO>` → **Rename**.
4. **Beri hak akses.** Di repo org → **Settings → Collaborators and teams** → pastikan Anda/tim punya peran minimal **Write** (untuk push).

**Yang perlu diketahui:**

- GitHub otomatis membuat **redirect** dari URL lama ke baru, jadi remote lama *sementara* masih jalan — tapi tetap **perbarui remote** (lihat di bawah).
- Perbarui remote di **mesin lokal**:
  ```bash
  git remote set-url origin git@github.com:<ORG>/<REPO>.git      # SSH
  # atau
  git remote set-url origin https://github.com/<ORG>/<REPO>.git  # HTTPS
  git remote -v
  ```
- Perbarui juga remote **di server** (SSH ke server, lalu jalankan hal yang sama di `<APP_DIR>`).

---

## B. Siapkan kunci SSH untuk deploy

GitHub Actions login ke server **tanpa interaksi**, jadi buat pasangan kunci **tanpa passphrase** khusus deploy.

1. Buat kunci (di mesin mana pun):
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/deploy_<REPO> -N ""
   ```
   Menghasilkan: `deploy_<REPO>` (privat) dan `deploy_<REPO>.pub` (publik).

2. Pasang kunci **publik** di server (pada user `<SSH_USER>`):
   ```bash
   ssh-copy-id -i ~/.ssh/deploy_<REPO>.pub -p <SSH_PORT> <SSH_USER>@<SERVER_HOST>
   # atau manual: tempel isi .pub ke ~/.ssh/authorized_keys milik <SSH_USER> di server
   ```

3. Uji koneksi:
   ```bash
   ssh -i ~/.ssh/deploy_<REPO> -p <SSH_PORT> <SSH_USER>@<SERVER_HOST> "whoami && pwd"
   ```

4. Isi kunci **privat** (`cat ~/.ssh/deploy_<REPO>`) akan dipakai sebagai secret `DEPLOY_SSH_KEY` di Bagian F.

---

## C. Siapkan server

- **Tooling** tersedia untuk user `<SSH_USER>`: `git`, `php` (versi sesuai app), `composer`, `node` + `npm`. Cek: `php -v && node -v && composer --version`.
- **Kepemilikan folder**: `<SSH_USER>` bisa menulis di `<APP_DIR>` (kalau tidak, deploy gagal saat menulis vendor/aset).
- **Remote git di server** mengarah ke repo org (Bagian A).
- **Akses server → GitHub untuk `git pull`** (penting):
  - Repo **publik** → pull anonim cukup, tanpa kredensial.
  - Repo **privat** → server harus bisa autentikasi ke GitHub: pasang **Deploy Key** GitHub (read‑only) di server, **atau** remote HTTPS dengan Personal Access Token. Tanpa ini, `git fetch` di server akan gagal.

---

## D. Tambahkan `deploy.sh` ke repo

Skrip ini **berjalan DI SERVER** (dipanggil Actions via SSH, dan bisa dijalankan manual). Sudah generic lewat env `APP_DIR`/`BRANCH`.

```bash
#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-<APP_DIR>}"
BRANCH="${BRANCH:-<BRANCH>}"
log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

cd "$APP_DIR"

log "Menarik perubahan dari origin/$BRANCH"
git fetch origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"     # ff-only: berhenti jelas jika ada perubahan lokal

log "Membersihkan cache lama (tanpa mem-boot Laravel)"
rm -f bootstrap/cache/*.php               # rm, BUKAN artisan (lihat Troubleshooting #3)

log "Memasang dependensi PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

log "Membangun aset frontend"
npm ci
npm run build                             # public/build di .gitignore -> WAJIB di-build di server

log "Menyiapkan cache produksi"
php artisan optimize                      # config + route saja (view:cache dihindari, Troubleshooting #4)
php artisan filament:optimize

log "Merestart worker antrean"
php artisan queue:restart

log "Selesai: $(git rev-parse --short HEAD) $(git log -1 --format=%s)"
```

> **Untuk stack lain** (bukan Laravel/Filament): ganti bagian `composer`/`artisan` dengan perintah build/migrate framework Anda. Pola intinya tetap: **pull → clear cache tanpa boot → install deps → build aset → cache/optimize → restart worker.**
>
> **Migrasi DB sengaja TIDAK di sini** — dijalankan manual agar perubahan skema selalu ditinjau (lihat Bagian H).

Set eksekutabel: `chmod +x deploy.sh`.

---

## E. Tambahkan workflow GitHub Actions

Simpan sebagai `.github/workflows/deploy.yml`. Dua job: **verify** (gerbang build bersih) lalu **deploy** (`needs: verify`).

```yaml
name: Deploy ke produksi
on:
  push:
    branches: [<BRANCH>]
  workflow_dispatch:                 # tombol "Run workflow" untuk deploy ulang tanpa commit

concurrency:
  group: deploy-produksi
  cancel-in-progress: false          # jangan batalkan deploy di tengah jalan

jobs:
  # GERBANG: reproduksi build produksi di runner. Kalau gagal, "deploy" tak jalan.
  verify:
    name: Verifikasi build bersih
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Siapkan PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'         # samakan dengan server
          extensions: mbstring, zip, gd, intl, bcmath, exif, pcntl, sodium, curl, dom, xml
          coverage: none
      - name: Validasi composer sinkron
        run: composer validate --no-check-publish
      - name: Siapkan .env minimal        # agar package:discover bisa boot (repo tanpa .env.example)
        run: |
          cat > .env <<'EOF'
          APP_NAME=CI
          APP_ENV=testing
          APP_DEBUG=true
          APP_URL=http://localhost
          LOG_CHANNEL=stderr
          DB_CONNECTION=sqlite
          DB_DATABASE=:memory:
          CACHE_DRIVER=array
          SESSION_DRIVER=array
          QUEUE_CONNECTION=sync
          BROADCAST_DRIVER=null
          FILESYSTEM_DISK=local
          MAIL_MAILER=array
          EOF
          echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)" >> .env
      - name: Install PHP (bersih, dari lock, --no-dev)
        run: composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
      - name: Pastikan service provider termuat
        run: php artisan package:discover --ansi
      - name: Siapkan Node
        uses: actions/setup-node@v4
        with: { node-version: '22', cache: npm }   # samakan dengan server
      - name: Build aset
        run: |
          npm ci
          npm run build

  # DEPLOY: hanya jalan bila verify hijau.
  deploy:
    name: Tarik & bangun di server
    needs: verify
    runs-on: ubuntu-latest
    steps:
      - name: Siapkan kunci SSH
        env:
          SSH_KEY: ${{ secrets.DEPLOY_SSH_KEY }}
          HOST: ${{ secrets.DEPLOY_HOST }}
          PORT: ${{ secrets.DEPLOY_PORT }}
        run: |
          set -euo pipefail
          mkdir -p ~/.ssh
          printf '%s\n' "$SSH_KEY" > ~/.ssh/deploy_key   # via env agar tak bocor ke log
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -p "${PORT:-22}" -H "$HOST" >> ~/.ssh/known_hosts 2>/dev/null
      - name: Jalankan deploy
        env:
          HOST: ${{ secrets.DEPLOY_HOST }}
          USER_NAME: ${{ secrets.DEPLOY_USER }}
          PORT: ${{ secrets.DEPLOY_PORT }}
          APP_DIR: ${{ secrets.DEPLOY_PATH }}
        run: |
          set -euo pipefail
          ssh -i ~/.ssh/deploy_key -p "${PORT:-22}" -o BatchMode=yes -o ServerAliveInterval=30 \
              "$USER_NAME@$HOST" \
              "set -euo pipefail
               cd '$APP_DIR'
               git fetch origin <BRANCH>
               git merge --ff-only origin/<BRANCH>
               APP_DIR='$APP_DIR' bash deploy.sh"
      - name: Bersihkan kunci
        if: always()
        run: rm -f ~/.ssh/deploy_key
```

> **Kenapa ada gerbang `verify`?** Ia menjalankan `composer install` + `package:discover` + `npm run build` **bersih di runner**. Kalau `composer.lock` rusak (mis. paket ter‑pin ke versi tak kompatibel), kegagalan terjadi **di CI**, bukan di server hidup — mencegah 500 produksi. Lihat Troubleshooting #5.

---

## F. Daftarkan GitHub Secrets

Repo → **Settings → Secrets and variables → Actions → New repository secret**. Butuh **5** secret:

| Secret | Isi | Contoh |
|---|---|---|
| `DEPLOY_SSH_KEY` | isi kunci **privat** `deploy_<REPO>` (seluruhnya, termasuk baris `BEGIN/END`) | — |
| `DEPLOY_HOST` | host/IP server | `dev.kisantra.com` |
| `DEPLOY_USER` | user SSH deploy | `root` |
| `DEPLOY_PATH` | folder app di server (`APP_DIR`) | `/home/kisantra-dev/htdocs/coal-management` |
| `DEPLOY_PORT` | port SSH | `22` |

> **Tips reuse antar‑repo:** kalau banyak repo dideploy ke **server yang sama**, buat **Organization secrets** (Settings organization → Secrets) untuk `DEPLOY_SSH_KEY`/`DEPLOY_HOST`/`DEPLOY_PORT`/`DEPLOY_USER` sekali saja, dan cukup set `DEPLOY_PATH` (yang berbeda per app) di tiap repo.

---

## G. Deploy pertama (uji)

1. Commit dua file baru:
   ```bash
   git add deploy.sh .github/workflows/deploy.yml
   git commit -m "ci: auto-deploy ke produksi saat push ke <BRANCH>"
   ```
2. **Push** — push inilah yang **memicu deploy pertama** sekaligus ujiannya:
   ```bash
   git push origin <BRANCH>
   ```
3. Buka tab **Actions** di GitHub. Pantau: `verify` → hijau → `deploy` → SSH ke server → selesai.
4. Kalau merah, buka job yang gagal untuk pesannya (server tak akan tersentuh bila `verify` gagal).

---

## H. Operasional harian

- **Deploy ulang tanpa commit baru:** tab **Actions → Deploy ke produksi → Run workflow** (memakai `workflow_dispatch`).
- **Migrasi database (manual, disengaja):** setiap kali ada migrasi baru, setelah deploy hijau:
  ```bash
  ssh -i ~/.ssh/deploy_<REPO> -p <SSH_PORT> <SSH_USER>@<SERVER_HOST> \
    "cd <APP_DIR> && php artisan migrate --force"
  ```
- **Deploy manual dari server** (mis. saat debug): `cd <APP_DIR> && APP_DIR=<APP_DIR> bash deploy.sh`.
- **Rollback:** `git revert <commit>` lalu push (memicu deploy ulang ke keadaan sebelumnya), atau checkout commit lama di server lalu jalankan `deploy.sh`.

---

## Troubleshooting (dari pengalaman nyata repo ini)

1. **`git push` → 403 setelah transfer.** Penyebab umum: (a) peran Anda di repo org belum **Write**; (b) kalau org pakai **SAML SSO**, Personal Access Token harus di‑*authorize* untuk org itu; (c) remote lokal masih menunjuk repo lama. Perbaiki peran/SSO/`remote set-url`. Catatan: repo **publik** bisa dibaca anonim, jadi "bisa clone tapi tak bisa push" ≠ SSO beres — itu justru gejala hak **write** yang kurang.

2. **Server masih menarik dari URL lama.** Berkat redirect GitHub, `git pull` di server *sementara* tetap jalan. Tetap perbarui: `git remote set-url origin <URL baru>`.

3. **Deploy nyangkut/deadlock saat "clear cache".** Perintah `php artisan optimize:clear` (atau artisan apa pun) **mem-boot** aplikasi lebih dulu. Kalau `vendor` sedang setengah jalan (paket lama tak kompatibel belum digusur composer), boot itu sendiri gagal dan `set -e` menjatuhkan deploy **sebelum** composer sempat memperbaikinya — deploy gagal di setiap run. Solusi (sudah di `deploy.sh`): bersihkan cache **tanpa boot** → `rm -f bootstrap/cache/*.php`, dan taruh `composer install` **sebelum** perintah artisan apa pun.

4. **`php artisan view:cache` menggagalkan deploy.** Ada paket yang tak menyertakan direktori `resources/views` (di repo ini: `moataz-01/filament-notification-sound`), sehingga `view:cache` selalu error. Jangan pakai. `php artisan optimize` di Laravel 10 hanya meng‑cache **config + route** (bukan view), jadi aman.

5. **`composer install` gagal di `package:discover`.** Biasanya lock memuat paket yang class‑nya tak bisa dimuat (mis. paket ter‑pin ke versi framework lain). Inilah alasan **gerbang `verify`** ada — ia menangkap ini **di CI**, bukan di server. Perbaiki `composer.json`/`composer.lock`, dorong ulang.

6. **Host key / `known_hosts`.** Workflow memakai `ssh-keyscan` yang mempercayai host pada koneksi pertama. Untuk lebih aman, simpan sidik jari host sebagai secret `DEPLOY_KNOWN_HOSTS` dan tulis langsung ke `~/.ssh/known_hosts`.

7. **Repo privat + server tak bisa `git fetch`.** Server butuh autentikasi ke GitHub. Pasang **Deploy Key** GitHub (read‑only) di server atau pakai remote HTTPS + token (lihat Bagian C).

---

## Checklist ringkas (untuk repo baru)

- [ ] Transfer repo ke `<ORG>` (+ rename bila perlu), perbarui remote lokal & server.
- [ ] Peran Write/Maintain untuk yang perlu push; PAT di‑authorize SSO bila ada.
- [ ] Buat kunci `deploy_<REPO>` (tanpa passphrase); pasang `.pub` di `authorized_keys` server; uji SSH.
- [ ] Server siap: git/php/composer/node, izin tulis `<APP_DIR>`, `.env` terisi, akses server→GitHub untuk pull.
- [ ] Tambah `deploy.sh` (+`chmod +x`) dan `.github/workflows/deploy.yml`.
- [ ] Daftarkan 5 secret: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_PORT`.
- [ ] Commit + push ke `<BRANCH>` → pantau Actions (verify → deploy).
- [ ] Jalankan migrasi manual bila ada: `php artisan migrate --force`.
