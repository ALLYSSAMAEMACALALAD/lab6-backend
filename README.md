# Product Management API (LavaLust)

LavaLust REST API with JWT authentication and product CRUD, backed by Aiven MySQL. Deployed on Render (Docker).

See **SETUP_GUIDE.md** for the full step-by-step. Quick start:

1. Run `database/schema.sql` on your Aiven MySQL (creates `products`, `users`, `refresh_tokens`, demo user `admin` / `Admin@123`).
2. Save Aiven's CA certificate as `app/certs/ca.pem`.
3. Copy `.env.example` to `.env` and fill it in (never commit `.env`).
4. `php -S localhost:8080 -t public public/index.php`

Endpoints: `POST /api/login`, `POST /api/refresh`, `POST /api/logout`, `GET /api/me`,
`GET|POST /api/products`, `GET|PUT|PATCH|DELETE /api/products/{id}` (all product routes need `Authorization: Bearer <token>`).
