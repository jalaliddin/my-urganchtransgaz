# VPSga joylashtirish bo'yicha yo'riqnoma

Bu hujjat `my.urtg.uz` (Urganchtransgaz Korporativ Portali) loyihasini haqiqiy VPS serverga Docker orqali joylashtirish bosqichlarini tavsiflaydi. Loyihaning root katalogidagi `docker-compose.yml`, `.env.docker.example` va `docker/nginx/default.conf` fayllariga asoslangan — bu yerda faqat qo'shimcha, amaliy qadamlar keltirilgan (README.md dagi "Docker deployment" bo'limi ham shu narsani qisqaroq tasvirlaydi).

---

## 0. Talablar

- **Server:** Ubuntu 22.04/24.04 LTS (tavsiya), kamida 2 vCPU / 4 GB RAM / 40 GB disk.
- **Domen:** `my.urtg.uz` A-yozuvi VPS ning IP manziliga yo'naltirilgan bo'lishi kerak (DNS provayderingizda sozlang).
- **SSH orqali root yoki sudo huquqiga ega foydalanuvchi** kirish.
- Loyiha kodi Git repository sifatida mavjud (GitHub/GitLab yoki boshqa joy).

Ikkita stsenariy bor, ular quyida alohida ko'rsatilgan:

- **A stsenariy** — VPS da faqat shu loyiha ishlaydi (alohida server).
- **B stsenariy** — VPS da boshqa loyihalar ham bor va ulardan oldindan umumiy nginx/reverse-proxy `:80`/`:443` portlarini egallab turibdi.

---

## 1. Serverni tayyorlash

SSH orqali serverga kiring va paketlarni yangilang:

```bash
sudo apt update && sudo apt upgrade -y
```

### Docker va Docker Compose plugin o'rnatish

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker   # joriy sessiyada guruh o'zgarishini darhol qo'llash
docker --version
docker compose version
```

### Firewall (ufw)

Faqat SSH, HTTP va HTTPS portlarini oching:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

> MySQL porti (3306) hech qachon tashqariga ochilmasligi kerak — `docker-compose.yml` da `db` xizmati uchun `ports:` yo'q, ya'ni u faqat konteynerlar ichki tarmog'ida ko'rinadi. Shunday qoldiring.

---

## 2. Loyihani serverga olib kelish

```bash
sudo mkdir -p /opt/my-urtg
sudo chown $USER:$USER /opt/my-urtg
cd /opt/my-urtg

git clone <repository-url> .
```

(Agar repo private bo'lsa — deploy key yoki shaxsiy access token orqali clone qiling.)

---

## 3. `.env` faylini sozlash

```bash
cp .env.docker.example .env
nano .env
```

Quyidagilarni albatta o'zgartiring:

| O'zgaruvchi | Nima qilish kerak |
| --- | --- |
| `DB_PASSWORD` | Kuchli, tasodifiy parol qo'ying |
| `MYSQL_ROOT_PASSWORD` | `DB_PASSWORD`dan boshqa, kuchli parol qo'ying |
| `APP_KEY` | Bo'sh qoldiring — 4-bosqichda generatsiya qilamiz |
| `APP_URL`, `FRONTEND_URL`, `VITE_API_URL` | Agar domen `my.urtg.uz` bo'lmasa, o'zingizning domeningizga moslang |
| `HTTP_PORT` | A stsenariy: `80`. B stsenariy: standart `8090` (yoki boshqa bo'sh port) qoldiring |

Parol generatsiya qilish uchun qulay usul:

```bash
openssl rand -base64 32
```

---

## 4. Imijlarni build qilish va `APP_KEY` generatsiya qilish

```bash
docker compose build
docker compose run --rm artisan key:generate --show
```

Chiqqan `base64:...` qatorini nusxalab, `.env` faylidagi `APP_KEY=` ga qo'ying (`nano .env`).

> **Diqqat:** Bu buyruqni faqat bir marta, birinchi joylashtirishda ishlating. Keyinchalik `APP_KEY` ni qayta generatsiya qilmang — bu barcha Sanctum tokenlarini va shifrlangan ma'lumotlarni o'qib bo'lmaydigan qilib qo'yadi.

---

## 5. Bazani ko'tarish va migratsiya

```bash
docker compose up -d db
docker compose run --rm artisan migrate --force
```

### Kerakli reference-seederlar (production uchun majburiy)

```bash
docker compose run --rm artisan db:seed --class=RolePermissionSeeder --force
docker compose run --rm artisan db:seed --class=DocumentTypeSeeder --force
docker compose run --rm artisan db:seed --class=IssueCategorySeeder --force
docker compose run --rm artisan db:seed --class=TaskCategorySeeder --force
```

> `IssueCategorySeeder` va `TaskCategorySeeder` — muammo va topshiriq kategoriyalarining boshlang'ich ro'yxati (keyin ilova ichida tahrirlanadi). Muammo qayd etish formasi kategoriyasiz ishlamaydi, shuning uchun `IssueCategorySeeder` majburiy.

> `Organization/Department/Position/EmployeeSeeder` (oddiy `db:seed --force` shularni ham ishga tushiradi) — demo ma'lumotlar, haqiqiy production bazaga **ishlatmang**. Haqiqiy tashkilot/bo'lim/xodimlarni ilova ichidan yoki CSV import orqali qo'shing.

---

## 6. Butun stekni ishga tushirish

```bash
docker compose up -d
docker compose ps
```

6 ta konteyner ishga tushishi kerak: `db`, `backend`, `backend-nginx`, `scheduler`, `frontend`, `nginx`. (`artisan` — profildan tashqarida, faqat qo'lda chaqirilganda ishlaydi.)

Loglarni tekshiring:

```bash
docker compose logs -f backend
docker compose logs -f nginx
```

---

## 7. Birinchi super-admin foydalanuvchini yaratish

Hech qanday seeder production uchun tayyor admin yaratmaydi — buni bir martalik `tinker` orqali qo'lda bajaring:

```bash
docker compose run --rm artisan tinker
```

Tinker ichida (misol, o'z qiymatlaringiz bilan almashtiring):

```php
$org = \App\Models\Organization::create([
    'name' => 'Urganchtransgaz MCHJ',
    'short_name' => 'UTG',
    'code' => 'UTG-CENTRAL',
    'type' => 'central',
]);

$dept = \App\Models\Department::create([
    'organization_id' => $org->id,
    'name' => 'Boshqaruv',
    'short_name' => 'BSH',
    'code' => 'UTG-CENTRAL-001',
]);

$user = \App\Models\User::create([
    'name' => 'Super Admin',
    'username' => 'superadmin',
    'email' => 'admin@my.urtg.uz',
    'password' => bcrypt('KUCHLI-PAROL-BU-YERGA'),
]);
$user->assignRole('super-admin');

\App\Models\Employee::create([
    'user_id' => $user->id,
    'organization_id' => $org->id,
    'department_id' => $dept->id,
    'employee_number' => 'EMP00001',
    'first_name' => 'Super',
    'last_name' => 'Admin',
    'hire_date' => now(),
    'status' => 'active',
]);
```

(Aniq model maydonlari o'zgargan bo'lishi mumkin — `backend/database/seeders/` ichidagi mavjud seederlarga qarang, ular aynan qanday maydonlar to'ldirilishini ko'rsatadi.) Login qilgandan so'ng qolgan tashkilotlar, bo'limlar va xodimlarni veb-ilova orqali qo'shing.

`exit` bilan tinkerdan chiqing.

---

## 8. HTTPS / SSL sozlash

`docker/nginx/default.conf` hozircha faqat HTTP (`:80`) bilan ishlaydi — u qandaydir TLS terminatsiya qiluvchi narsaning orqasida turishga mo'ljallangan.

### B stsenariy — serverda boshqa loyihalar bor

Bu holatda VPS da allaqachon umumiy reverse-proxy (masalan, tashqi nginx, Nginx Proxy Manager yoki Traefik) `:80`/`:443` ni egallab turadi. Bu stekni ochiq internetga chiqarmang — u faqat `127.0.0.1:${HTTP_PORT}` (standart `8090`) da tinglaydi.

Mavjud reverse-proxyingizda `my.urtg.uz` uchun yangi server/host bloki qo'shing, u SSL sertifikatni (masalan, Certbot orqali) ushbu proxy darajasida boshqaradi va so'rovlarni `127.0.0.1:8090` ga proxy_pass qiladi. Bu yo'riqnoma sizning mavjud proxy konfiguratsiyangizga bog'liq bo'lgani uchun aniq buyruqlar berilmaydi — proxyingiz hujjatlariga qarang.

### A stsenariy — bu alohida, faqat shu loyiha uchun server

Certbot'ni to'g'ridan-to'g'ri shu compose stekiga qo'shamiz (webroot usuli — `default.conf` da `/.well-known/acme-challenge/` joyi allaqachon tayyorlangan).

**8.1.** `docker-compose.yml` dagi `nginx` xizmatida `443` portini va sertifikat volumelarini oching (izohlangan qatorlarni faollashtiring):

```yaml
  nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "${HTTP_PORT:-8090}:80"
      - "443:443"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./docker/certbot/www:/var/www/certbot:ro
      - ./docker/certbot/conf:/etc/letsencrypt:ro
    depends_on:
      - backend-nginx
      - frontend

  certbot:
    image: certbot/certbot
    volumes:
      - ./docker/certbot/www:/var/www/certbot
      - ./docker/certbot/conf:/etc/letsencrypt
```

Va `.env` da:

```
HTTP_PORT=80
```

**8.2.** Sertifikatni birinchi marta olish (bu vaqtda `default.conf` hali plain HTTP bo'lishi kerak, chunki 443 hali sertifikatsiz ishlamaydi):

```bash
mkdir -p docker/certbot/www docker/certbot/conf
docker compose up -d nginx
docker compose run --rm certbot certonly \
  --webroot -w /var/www/certbot \
  -d my.urtg.uz \
  --email sizning-emailingiz@example.com \
  --agree-tos --no-eff-email
```

**8.3.** `docker/nginx/default.conf` ga 443 uchun ikkinchi server blokini qo'shing va birinchisini HTTP→HTTPS redirectga aylantiring:

```nginx
server {
    listen 80;
    server_name my.urtg.uz;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    server_name my.urtg.uz;

    ssl_certificate     /etc/letsencrypt/live/my.urtg.uz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/my.urtg.uz/privkey.pem;

    client_max_body_size 25M;

    location /api/ {
        proxy_pass http://backend-nginx:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        proxy_pass http://frontend:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**8.4.** Qayta ishga tushiring:

```bash
docker compose up -d
```

**8.5.** Avtomatik yangilanish uchun serverning o'zida (host darajasida) cron qo'shing:

```bash
sudo crontab -e
```

Qator qo'shing (har kuni tunda tekshiradi, muddati kelganda yangilaydi):

```
0 3 * * * cd /opt/my-urtg && docker compose run --rm certbot renew --webroot -w /var/www/certbot -q && docker compose exec nginx nginx -s reload
```

---

## 9. Boshqa xavfsizlik tekshiruvlari

- `APP_DEBUG=false` ekanini `.env` da tasdiqlang (stack-trace production'da hech qachon chiqmasligi kerak).
- CORS: `backend/config/cors.php` mavjud bo'lsa, `allowed_origins` ni `https://my.urtg.uz` ga qattiq belgilang (README shu haqda ogohlantiradi — standart holatda hamma origin ruxsat etilgan, chunki autentifikatsiya token asosida, cookie asosida emas).
- `docker compose ps` orqali `db` xizmatida `ports:` yo'qligini tasdiqlang — u faqat ichki tarmoqda bo'lishi kerak.

---

## 10. Zaxira nusxa olish (backup)

Muhim ma'lumotlar ikki joyda: `db-data` (MySQL) va `backend-storage` (yuklangan hujjatlar/fotosuratlar) named volume'larida.

### Bazadan kunlik backup skripti

`/opt/my-urtg/backup.sh` yarating:

```bash
#!/bin/bash
set -e
cd /opt/my-urtg
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR=/opt/backups/my-urtg
mkdir -p "$BACKUP_DIR"

source .env
docker compose exec -T db mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" my_urtg | gzip > "$BACKUP_DIR/db-$TIMESTAMP.sql.gz"

docker run --rm \
  -v my-urtg_backend-storage:/data:ro \
  -v "$BACKUP_DIR":/backup \
  alpine tar czf "/backup/storage-$TIMESTAMP.tar.gz" -C /data .

find "$BACKUP_DIR" -type f -mtime +14 -delete
```

```bash
chmod +x /opt/my-urtg/backup.sh
```

> `my-urtg_backend-storage` — volume nomi odatda `<papka_nomi>_backend-storage` shaklida bo'ladi. Aniqlash uchun: `docker volume ls | grep backend-storage`.

Cron ga qo'shing (har kuni tunda):

```bash
sudo crontab -e
```

```
0 2 * * * /opt/my-urtg/backup.sh >> /var/log/my-urtg-backup.log 2>&1
```

Tavsiya: `$BACKUP_DIR`ni vaqti-vaqti bilan boshqa serverga yoki S3/object storage'ga ko'chiring — faqat shu VPS'da saqlash yetarli emas (disk yoki server halokati holatida).

---

## 11. Yangilanishlarni joylashtirish (redeploy)

Kodga o'zgartirish kiritilganda:

```bash
cd /opt/my-urtg
git pull
docker compose build
docker compose run --rm artisan migrate --force   # faqat yangi migratsiyalar bo'lsa
docker compose up -d
```

Yangi rol ruxsatlari (masalan, `issues.report`, `task_categories.manage`) migratsiya orqali mavjud rollarga avtomatik qo'shiladi — `RolePermissionSeeder`ni qayta ishga tushirish shart emas. Agar relizda yangi reference-seeder paydo bo'lsa, uni bir marta ishga tushiring; masalan, topshiriq kategoriyalari qo'shilgan reliz uchun:

```bash
docker compose run --rm artisan db:seed --class=TaskCategorySeeder --force
```

Reference-seederlar `firstOrCreate` bilan yozilgan, ularni qayta ishga tushirish mavjud yozuvlarni takrorlamaydi va o'zgartirmaydi.

Eski, endi ishlatilmayotgan imijlarni vaqti-vaqti bilan tozalash:

```bash
docker image prune -f
```

---

## 12. Monitoring va loglar

```bash
docker compose logs -f backend        # Laravel loglari
docker compose logs -f scheduler      # rejalashtirilgan job'lar (attendance, exam reminders va h.k.)
docker compose logs -f nginx          # reverse-proxy
docker compose ps                     # konteynerlar holati
docker stats                          # CPU/RAM real vaqtda
```

Konteynerlar `restart: unless-stopped` bilan sozlangan — server qayta yuklansa (reboot) ular avtomatik qayta ko'tariladi, faqat Docker'ning o'zi ishga tushirilgan bo'lishi kerak:

```bash
sudo systemctl enable docker
```

---

## Qisqa nazorat ro'yxati

- [ ] DNS: `my.urtg.uz` → VPS IP
- [ ] `ufw`: faqat 22/80/443 ochiq
- [ ] `.env`: kuchli `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, to'g'ri `APP_KEY`
- [ ] `APP_DEBUG=false`
- [ ] Migratsiya + `RolePermissionSeeder` + `DocumentTypeSeeder` + `IssueCategorySeeder` + `TaskCategorySeeder` bajarilgan
- [ ] Demo seederlar (`OrganizationSeeder` va h.k.) **ishlatilmagan**
- [ ] Birinchi super-admin qo'lda yaratilgan
- [ ] HTTPS ishlayapti, `http://` avtomatik `https://` ga yo'naltiriladi
- [ ] Kunlik backup cron ishlayapti va backup fayllar haqiqatan hosil bo'lyapti
- [ ] `docker compose logs` da xatolik yo'q
