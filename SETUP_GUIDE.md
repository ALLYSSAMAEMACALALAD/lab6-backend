# Laboratory Exercise No. 6 — Setup Guide
**CRUD with Authentication: React.js + LavaLust API + Aiven MySQL + Render**

You received two projects:

| Folder | What it is | Goes to |
|---|---|---|
| `lavalust-api/` | Backend (PHP, LavaLust framework) | GitHub repo #1 → deployed on **Render** (Docker) |
| `product-frontend/` | Frontend (React + Vite + Axios) | GitHub repo #2 → deployed on **Render** (Static Site) |

**How the pieces connect:** React → (HTTP + JWT token) → LavaLust API on Render → Aiven MySQL.
React never talks to the database directly.

**Demo login (created by the SQL file):** username `admin`, password `Admin@123`

Do the steps **in this order**: Aiven → run the API locally → run React locally → GitHub → Render API → Render frontend → final CORS fix → screenshots.

---

## Part 1 — Create the Aiven MySQL database

1. Go to **https://aiven.io** and sign up / log in.
2. Click **Create service** → choose **MySQL**.
3. Pick the **Free** plan (if shown), choose any cloud/region close to you, name it e.g. `product-db`, then **Create service**.
4. Wait until the status says **Running** (a few minutes).
5. Open the service **Overview** page and copy the **Connection information**:
   - Host · Port · User (`avnadmin`) · Password · Database (`defaultdb`)
6. Click **Download** next to **CA certificate** and save the file as `ca.pem`.
   (Aiven only accepts encrypted connections, so the API needs this file.)

**Create the tables.** Use any MySQL client — pick one:

- **MySQL command line**
  ```bash
  mysql --host=HOST --port=PORT --user=avnadmin --password --ssl-ca=ca.pem defaultdb < lavalust-api/database/schema.sql
  ```
- **DBeaver / MySQL Workbench / HeidiSQL (GUI):** create a new MySQL connection with the host, port, user, password and database above; enable **SSL** and select `ca.pem` as the CA certificate; connect; open `lavalust-api/database/schema.sql`; run the whole script.

**Check:** run `SHOW TABLES;` — you should see `products`, `users`, `refresh_tokens`. Run `SELECT * FROM products;` — 3 sample rows.

> 📸 **Screenshot needed — "Aiven database":** take it of the Aiven service Overview page (password hidden!) and/or your SQL client showing the `products` table with data.

---

## Part 2 — Configure and run the LavaLust API locally

Requirements: **PHP 8.1+** with the `pdo_mysql` extension.

1. Put `ca.pem` into `lavalust-api/app/certs/ca.pem`.
   (It is a public certificate, safe to commit. It is **not** a secret.)
2. In `lavalust-api/`, copy `.env.example` to `.env`.
3. Generate two **different** secrets (each at least 32 characters):
   ```bash
   php lava jwt:generate --show
   ```
   or, if you prefer Node:
   ```bash
   node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
   ```
   (run it twice). 
4. Fill in `.env`:
   ```ini
   DB_DRIVER=mysql
   DB_HOST=<Aiven host>
   DB_PORT=<Aiven port>
   DB_USER=avnadmin
   DB_PASSWORD=<Aiven password>
   DB_NAME=defaultdb
   DB_CHARSET=utf8mb4
   JWT_SECRET=<secret #1>
   REFRESH_TOKEN_KEY=<secret #2>
   CORS_ORIGINS=http://localhost:5173
   ```
   `.env` is in `.gitignore`, so your passwords will **not** go to GitHub.
5. Start the server:
   ```bash
   php -S localhost:8080 -t public public/index.php
   ```
6. Open **http://localhost:8080/** — you should see `{"name":"Product Management API (LavaLust)","status":"running",...}`.

**Test with the API Tester** (https://api-tester.marasigan.dev/) or curl:

| # | Request | Body (JSON) | Expected |
|---|---|---|---|
| 1 | `POST http://localhost:8080/api/login` | `{"username":"admin","password":"Admin@123"}` | 200 + `tokens.access_token` |
| 2 | `GET /api/products` (no token) | — | **401 Unauthorized** (proves it is protected) |
| 3 | `GET /api/products` + header `Authorization: Bearer <access_token>` | — | 200 + list |
| 4 | `POST /api/products` + header | `{"product_name":"Pen","description":"Blue","price":12.5,"quantity":100}` | 201 |
| 5 | `PUT /api/products/1` + header | same fields as above | 200 |
| 6 | `DELETE /api/products/1` + header | — | 200 |

If step 1 shows a database error, re-check `DB_*` values and that `app/certs/ca.pem` exists.

### API endpoints (for your documentation)

| Method | URL | Auth | Purpose |
|---|---|---|---|
| POST | `/api/login` | no | Get access + refresh tokens |
| POST | `/api/refresh` | refresh token | Get new tokens |
| POST | `/api/logout` | yes | Revoke refresh token |
| GET | `/api/me` | yes | Current user |
| GET | `/api/products` | yes | List products |
| GET | `/api/products/{id}` | yes | One product |
| POST | `/api/products` | yes | Add product |
| PUT / PATCH | `/api/products/{id}` | yes | Update product |
| DELETE | `/api/products/{id}` | yes | Delete product |

---

## Part 3 — Configure and run the React app locally

Requirements: **Node.js 18+**.

1. In `product-frontend/`, copy `.env.example` to `.env`:
   ```ini
   VITE_API_URL=http://localhost:8080
   ```
2. Install and run:
   ```bash
   npm install
   npm run dev
   ```
3. Open **http://localhost:5173**, log in with `admin` / `Admin@123`, and try Add / Edit / Delete / Logout.

> 📸 **Screenshots needed:** Login page · Product list · Add product (form open) · Edit product (form open) · Delete product (after deleting, or the confirm dialog).

---

## Part 4 — Push both projects to GitHub

Create **two empty repositories** on GitHub (e.g. `product-api-lavalust` and `product-frontend-react`). Do not add a README/.gitignore on GitHub.

**API** (from inside `lavalust-api/`):
```bash
git init
git add .
git commit -m "LavaLust product API"
git branch -M main
git remote add origin https://github.com/<you>/product-api-lavalust.git
git push -u origin main
```
**Frontend** (from inside `product-frontend/`): same commands with the other repo URL.

**Before pushing, verify** that `.env` does **not** appear in `git status` / on GitHub. If you ever pushed a real password by mistake, change the password in Aiven immediately.

---

## Part 5 — Deploy the API to Render

1. Go to **https://render.com** → sign in with GitHub.
2. **New +** → **Web Service** → select your API repo.
3. Settings:
   - **Language / Runtime:** `Docker` (the repo already has a `Dockerfile`)
   - **Branch:** `main`
   - **Instance type:** Free
4. Under **Environment Variables**, add (same names as your `.env`):

   | Key | Value |
   |---|---|
   | `DB_DRIVER` | `mysql` |
   | `DB_HOST` | Aiven host |
   | `DB_PORT` | Aiven port |
   | `DB_USER` | `avnadmin` |
   | `DB_PASSWORD` | Aiven password |
   | `DB_NAME` | `defaultdb` |
   | `DB_CHARSET` | `utf8mb4` |
   | `JWT_SECRET` | secret #1 (32+ chars) |
   | `REFRESH_TOKEN_KEY` | secret #2 (different) |
   | `CORS_ORIGINS` | `http://localhost:5173` for now (we update it in Part 7) |

5. Click **Create Web Service** and wait for the build to say **Live**.
6. Copy your API URL, e.g. `https://product-api-xxxx.onrender.com`.
7. Open it in the browser — you should see the `"status":"running"` JSON. Then test `POST <url>/api/login` in the API Tester.

> ⚠️ Free Render services sleep when idle — the first request after a break can take ~1 minute. Open the API URL once before your demo to wake it up.

**This is your "Render API URL" for submission.**

---

## Part 6 — Deploy the React app to Render

1. **New +** → **Static Site** → select your frontend repo.
2. Settings:
   - **Build Command:** `npm install && npm run build`
   - **Publish Directory:** `dist`
3. **Environment Variable:** `VITE_API_URL` = your Render API URL (no trailing slash), e.g. `https://product-api-xxxx.onrender.com`
4. **Create Static Site**, wait until it is **Live**, and copy the URL, e.g. `https://product-frontend-xxxx.onrender.com`.

> `VITE_API_URL` is read **at build time**. If you change it later, trigger **Manual Deploy → Clear build cache & deploy**.

**This is your "Frontend URL" for submission.**

---

## Part 7 — Allow the frontend to call the API (CORS)

1. Render → your **API** service → **Environment**.
2. Edit `CORS_ORIGINS` to include both URLs, comma-separated, **no trailing slash, no spaces needed**:
   ```
   http://localhost:5173,https://product-frontend-xxxx.onrender.com
   ```
3. Save — Render redeploys automatically.
4. Open the Frontend URL, log in, and do a full Add → Edit → Delete → Logout.

---

## Part 8 — Submission checklist

- [ ] GitHub repo of the LavaLust API
- [ ] GitHub repo of the React app
- [ ] Render API URL
- [ ] Frontend URL
- [ ] Screenshots: Login · Product list · Add product · Edit product · Delete product · Aiven database
- [ ] Be ready to demo: **Login → Product list → Add / Edit / Delete**

Take the screenshots from the **deployed** Frontend URL if you can (better evidence than localhost).

---

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Browser console: *blocked by CORS policy* | Frontend URL missing in `CORS_ORIGINS` (exact match, `https://`, no trailing `/`). Save & wait for redeploy. |
| Login page says *Cannot reach the API* | Wrong `VITE_API_URL`, API asleep (wait ~1 min and retry), or you changed the variable without redeploying the static site. |
| API shows database/connection error | Wrong `DB_*` values, `app/certs/ca.pem` not committed/present, or the Aiven service is powered off (turn it on in the Aiven console). |
| `Api Helper ... set up incorrectly` / `jwt_secret is missing or too short` | `JWT_SECRET` / `REFRESH_TOKEN_KEY` missing, shorter than 32 chars, identical to each other, or too repetitive. |
| `Invalid username or password` | Did you run the **whole** `schema.sql` (it inserts the `admin` user)? |
| Render build fails | Check the Render logs; make sure Language is **Docker** and the `Dockerfile` is in the repo root. |
| Logged out after ~15 min | Normal token expiry; the app refreshes automatically, but if the refresh token was revoked/expired you simply log in again. |

## Changing the admin password

Generate a new hash and update the row:
```bash
php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT);"
```
```sql
UPDATE users SET password = '<paste hash>' WHERE username = 'admin';
```

## What was changed in the LavaLust framework (for your report)

- `app/config/api.php` — API helper enabled, JWT issuer/audience set.
- `app/config/config.php` — `base_url` and CORS origins read from environment variables.
- `app/config/routes.php` — API routes.
- `app/controllers/Auth.php`, `Products.php` — login/refresh/logout and product CRUD, all using the **LavaLust API library** (`$this->api->body()`, `respond()`, `respond_error()`, `require_jwt()`, `issue_tokens()`).
- `scheme/database/Database.php` — small addition so MySQL connections use the Aiven CA certificate (TLS), which Aiven requires.
- `Dockerfile` — PHP 8.3 and listens on Render's `$PORT`.
- `database/schema.sql` — tables, demo user, sample products.
