# Render par live deploy (testing)

**Important:** Database password / host **kabhi Git par mat daalo**. Sirf Render Dashboard ya apni machine ki `.env` file mein.

---

## 1. Credentials kahan rakhen?

| Jagah | File / UI | Git mein? |
|--------|-----------|-----------|
| **Backend (local)** | `backend/.env` | ❌ Never (`.gitignore`) |
| **Frontend (local)** | `frontend/.env.local` | ❌ Never |
| **Backend (Render live)** | [Render Dashboard](https://dashboard.render.com) → **Web Service (API)** → **Environment** | ❌ Never |
| **Frontend (Render live)** | Render → **Web Service (Next.js)** → **Environment** | ❌ Never |
| **Database (Render)** | Render → **PostgreSQL** (ya external MySQL) → **Connections** / Internal URL | ❌ Never |

### Backend – Render Environment variables (manual copy)

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=          # php artisan key:generate --show
APP_URL=https://YOUR-BACKEND.onrender.com
FRONTEND_URL=https://YOUR-FRONTEND.onrender.com

DB_CONNECTION=mysql
DB_HOST=          # Render se (External Hostname)
DB_PORT=          # e.g. 5432 (Postgres) or 3306 (MySQL)
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

JWT_SECRET=       # long random string
```

### Frontend – Render Environment

```
NEXT_PUBLIC_API_URL=https://YOUR-BACKEND.onrender.com
```

---

## 2. Git repositories

| Part | GitHub repo |
|------|-------------|
| Backend + monorepo root | `https://github.com/vipinsinghvp844/Full-Saas` (folder `backend/`) |
| Frontend | `https://github.com/vipinsinghvp844/Fitnexa` (folder `frontend/`) |

Render par:
- **Backend service** → Root Directory: `backend`
- **Frontend service** → Root Directory: `.` (Fitnexa repo root) **ya** monorepo mein `frontend`

---

## 3. Docker Desktop MySQL → Render database

### Option A – Testing ke liye (recommended): fresh schema + seed

Render par **khali** database connect karo, phir Render **Shell** ya one-off job:

```bash
cd backend
php artisan migrate --force
php artisan db:seed --force
```

Login: `superadmin@platform.com` / `password`

### Option B – Purana Docker data copy karna

**Step 1 – Docker se dump (apni machine):**

```powershell
docker ps
# MySQL container name dhundho, phir:
docker exec -i CONTAINER_NAME mysqldump -u root -proot123 fullsaas > fullsaas-backup.sql
```

(`root123` / `fullsaas` apne `backend/.env` se match karo.)

**Step 2 – Render par database type:**

- Agar Render **PostgreSQL** hai → MySQL dump direct import **nahi** chalega. Testing ke liye **Option A** use karo.
- Agar tumhare paas **MySQL** host hai (Render external / PlanetScale / etc.) → Render/hosting panel se import:

```bash
mysql -h HOST -P PORT -u USER -p DATABASE < fullsaas-backup.sql
```

**Step 3 – SSL:** Render Postgres/MySQL kabhi `MYSQL_ATTR_SSL_CA` / `DB_SSLMODE=require` maangta hai — hosting docs dekho.

---

## 4. Render service setup (short)

### Backend (Laravel)

- **Build:** `composer install --no-dev --optimize-autoloader && php artisan config:cache`
- **Start:** `php artisan serve --host=0.0.0.0 --port=$PORT`
- **Post-deploy (optional):** `php artisan migrate --force && php artisan storage:link`

### Frontend (Next.js)

- **Build:** `npm ci && npm run build`
- **Start:** `npm start`
- **Env:** `NEXT_PUBLIC_API_URL` = backend URL

---

## 5. Checklist

- [ ] `APP_KEY` set on Render backend
- [ ] `JWT_SECRET` set
- [ ] `FRONTEND_URL` = exact frontend URL (no trailing slash)
- [ ] `NEXT_PUBLIC_API_URL` = exact backend URL
- [ ] CORS: backend `FRONTEND_URL` mein frontend URL hai
- [ ] Database migrate/seed done on Render
- [ ] `php artisan storage:link` (uploads ke liye)

---

## 6. Tum credentials chat mein doge?

Agent **password git mein save nahi karega**. Tum Render Dashboard → Environment mein khud paste karo — upar wale variable names use karo.
