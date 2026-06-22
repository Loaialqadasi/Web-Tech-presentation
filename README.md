# UniEvents — University Event Management & Ticketing System

**FreshDev Team 9** | SECJ3483 Web Technology | Phase 3 Demo

---

## Overview

UniEvents is a full-stack **Single Page Application (SPA)** for managing university campus events, ticket bookings, forum discussions, and community feedback. The frontend is built with **Vue.js 3** and the backend is a **RESTful API** built with **PHP Slim 4** that talks to a **MySQL** database via **PDO** (prepared statements). Authentication uses **JWT** with role-based access control.

This repository contains both the frontend and the backend so the entire stack can be deployed together.

---

## Technology Stack

| Layer              | Technology                  | Purpose                                                                  |
| ------------------ | --------------------------- | ------------------------------------------------------------------------ |
| Frontend           | Vue.js 3 + Vue Router       | SPA with routing, reusable components, forms, and client-side validation |
| Styling            | Tailwind CSS 3              | Utility-first responsive design                                          |
| HTTP Client        | Axios                       | Asynchronous API communication (AJAX)                                    |
| State Management   | Vue `reactive()`            | Lightweight reactive auth/booking state (Pinia installed, currently unused) |
| **Backend**        | **PHP Slim 4**              | **RESTful API with JSON request/response, route groups, middleware**     |
| **Database**       | **MySQL** (via PDO)         | **Persistent storage with prepared statements (SQL-injection safe)**     |
| Authentication     | Firebase PHP-JWT            | Token-based auth, 24-hour expiry, Bearer scheme                          |
| Password Security  | PHP `password_hash()` (bcrypt) | Secure password hashing (compatible with bcryptjs)                    |
| Deployment         | Render (Docker) + Vercel      | Free tier — PHP backend as Docker image, Vue SPA on Vercel's CDN |

> The stack matches the SECJ3483 course brief: *RESTful backend using PHP Slim, MySQL database integration with PDO, JWT-based authentication.*

---

## Project Structure

```
unievent-final/
├── frontend/                       # Vue.js 3 SPA
│   ├── src/
│   │   ├── main.js                 # App entry point
│   │   ├── App.vue                 # Root component (Navbar + Footer chrome)
│   │   ├── style.css               # Global Tailwind + base styles
│   │   ├── router/
│   │   │   └── index.js            # Vue Router config + auth navigation guard
│   │   ├── service/
│   │   │   ├── api.js              # Axios client with JWT interceptor
│   │   │   └── auth.js             # Auth state + login/register/logout
│   │   ├── utils/
│   │   │   ├── validators.js       # ★ Shared form validation rules + schemas
│   │   │   └── format.js           # ★ Date / price / category formatting
│   │   ├── components/
│   │   │   └── shared/             # ★ Reusable across ALL modules
│   │   │       ├── EventCard.vue       # Event display (default + compact)
│   │   │       ├── StatCard.vue        # KPI tile for dashboards
│   │   │       ├── EmptyState.vue      # Friendly empty placeholder
│   │   │       ├── LoadingSpinner.vue  # Accessible loading indicator
│   │   │       ├── SearchBar.vue       # Debounced search input
│   │   │       └── CategoryFilter.vue  # Pill-style category tabs
│   │   └── views/
│   │       ├── GalleryView.vue        # ★ Loai: Event Gallery (public)
│   │       ├── DashboardView.vue      # ★ Loai: User Dashboard (auth)
│   │       ├── LoginView.vue          # Auth gate
│   │       └── _StubView.vue          # Placeholder for other modules
│   ├── public/
│   ├── index.html
│   ├── package.json
│   ├── vite.config.js               # Dev proxy → PHP backend
│   ├── tailwind.config.js
│   ├── postcss.config.js
│   ├── vercel.json                  # ★ SPA rewrite rules for Vercel
│   └── .env.example
│
├── backend/                        # PHP Slim 4 REST API
│   ├── public/
│   │   └── index.php               # Front controller (Slim app bootstrap)
│   ├── src/
│   │   ├── Database.php            # PDO singleton (MySQL)
│   │   ├── JwtHelper.php           # JWT encode/decode (HS256)
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php  # PSR-15 JWT verification
│   │   │   └── RoleMiddleware.php  # PSR-15 role guard
│   │   └── Routes/                 # One class per module (9 total)
│   │       ├── AuthRoutes.php          # /api/auth/*
│   │       ├── EventRoutes.php         # /api/events/*
│   │       ├── BookingRoutes.php       # /api/bookings/*
│   │       ├── PaymentRoutes.php       # /api/payments/*
│   │       ├── ForumRoutes.php         # /api/forum/*
│   │       ├── FeedbackRoutes.php      # /api/feedback/*
│   │       ├── NotificationRoutes.php  # /api/notifications/*
│   │       ├── CalendarRoutes.php      # /api/calendar/*
│   │       ├── DashboardRoutes.php     # /api/dashboard/*
│   │       └── JsonResponder.php       # Shared JSON helper trait
│   ├── database/
│   │   ├── schema.sql              # All 9 tables + foreign keys
│   │   └── seed.php                # Demo users + sample data
│   ├── composer.json
│   ├── .htaccess                   # Apache rewrite rules
│   ├── Dockerfile                  # ★ Render: PHP-FPM + Nginx image
│   ├── render.yaml                 # ★ Render Blueprint (one-click deploy)
│   ├── .dockerignore
│   ├── docker/
│   │   ├── nginx.conf              # Routes all /api/* → index.php
│   │   └── start.sh                # Entrypoint: php-fpm + nginx
│   └── .env.example
│
└── README.md                       # This file
```

★ = Loai's deliverables (Event Gallery, User Dashboard, shared UI patterns, validation utilities).

---

## REST API Endpoints

All responses are JSON. Endpoints marked **Auth** require `Authorization: Bearer <token>`.

### Authentication

| Method | Endpoint                       | Description                | Auth     |
| ------ | ------------------------------ | -------------------------- | -------- |
| POST   | `/api/auth/register`           | Register a new user        | No       |
| POST   | `/api/auth/login`              | Login and receive JWT      | No       |
| GET    | `/api/auth/profile`            | Get user profile           | Yes      |
| PUT    | `/api/auth/profile`            | Update user profile        | Yes      |
| POST   | `/api/auth/change-password`    | Change password            | Yes      |

### Events

| Method | Endpoint                       | Description                | Auth              |
| ------ | ------------------------------ | -------------------------- | ----------------- |
| GET    | `/api/events`                  | Get all events (filters)   | No                |
| GET    | `/api/events/categories/list`  | Get category list          | No                |
| GET    | `/api/events/{id}`             | Get single event           | No                |
| POST   | `/api/events`                  | Create event               | Organizer / Admin |
| PUT    | `/api/events/{id}`             | Update event               | Organizer / Admin |
| DELETE | `/api/events/{id}`             | Delete event               | Organizer / Admin |

### Bookings

| Method | Endpoint                       | Description                | Auth    |
| ------ | ------------------------------ | -------------------------- | ------- |
| GET    | `/api/bookings/user/{userId}`  | Get user's bookings        | Yes     |
| POST   | `/api/bookings`                | Create booking             | Student |
| PUT    | `/api/bookings/{id}`           | Update booking             | Yes     |
| DELETE | `/api/bookings/{id}`           | Cancel booking             | Yes     |

### Payments

| Method | Endpoint                       | Description                | Auth    |
| ------ | ------------------------------ | -------------------------- | ------- |
| POST   | `/api/payments`                | Simulate payment           | Student |
| GET    | `/api/payments/user/{userId}`  | Get payment history        | Yes     |

### Forum

| Method | Endpoint                                | Description                | Auth           |
| ------ | --------------------------------------- | -------------------------- | -------------- |
| GET    | `/api/forum/posts`                      | Get forum posts            | No             |
| GET    | `/api/forum/posts/{id}`                 | Get single post            | No             |
| POST   | `/api/forum/posts`                      | Create post                | Yes            |
| DELETE | `/api/forum/posts/{id}`                 | Delete post                | Author / Admin |
| GET    | `/api/forum/posts/{id}/comments`        | Get comments               | No             |
| POST   | `/api/forum/comments`                   | Add comment                | Yes            |
| DELETE | `/api/forum/comments/{id}`              | Delete comment             | Author / Admin |

### Feedback

| Method | Endpoint                       | Description                | Auth           |
| ------ | ------------------------------ | -------------------------- | -------------- |
| GET    | `/api/feedback`                 | Get all feedback           | No             |
| GET    | `/api/feedback/event/{eventId}` | Get event feedback         | No             |
| POST   | `/api/feedback`                 | Submit feedback            | Yes            |
| DELETE | `/api/feedback/{id}`            | Delete feedback            | Author / Admin |

### Notifications

| Method | Endpoint                                | Description                | Auth |
| ------ | --------------------------------------- | -------------------------- | ---- |
| GET    | `/api/notifications/user/{userId}`      | Get notifications          | Yes  |
| POST   | `/api/notifications`                    | Create notification        | Yes  |
| PUT    | `/api/notifications/{id}/read`          | Mark as read               | Yes  |
| PUT    | `/api/notifications/read-all/{userId}`  | Mark all as read           | Yes  |
| DELETE | `/api/notifications/{id}`               | Delete notification        | Yes  |

### Calendar

| Method | Endpoint                       | Description                | Auth |
| ------ | ------------------------------ | -------------------------- | ---- |
| GET    | `/api/calendar/user/{userId}`   | Get calendar events        | Yes  |
| POST   | `/api/calendar`                 | Add calendar event         | Yes  |
| PUT    | `/api/calendar/{id}`            | Update calendar event      | Yes  |
| DELETE | `/api/calendar/{id}`            | Remove calendar event      | Yes  |

### Dashboard

| Method | Endpoint                  | Description                | Auth |
| ------ | ------------------------- | -------------------------- | ---- |
| GET    | `/api/dashboard/{userId}` | Get dashboard summary      | Yes  |

---

## Security Features

- **JWT Authentication**: Bearer tokens with 24-hour expiry, signed with HS256
- **Role-Based Access Control (RBAC)**: Student, Organizer, Admin roles enforced via PSR-15 middleware
- **Password Hashing**: PHP `password_hash()` with bcrypt (cost 10) — cross-compatible with bcryptjs hashes
- **SQL Injection Protection**: All queries use **PDO prepared statements** with bound parameters
- **Input Validation**: Server-side validation on every endpoint (email format, length, ranges, required fields)
- **CORS**: Configured via Slim middleware + Apache headers
- **HTTP Status Codes**: Proper 200, 201, 400, 401, 403, 404, 409, 500 responses

---

## Database Schema

9 related tables with foreign key constraints (InnoDB, utf8mb4):

```
users  ─┬─< events           (organizer_id)
        ├─< bookings         (user_id)
        ├─< forum_posts      (user_id)
        ├─< comments         (user_id)
        ├─< feedback         (user_id)
        ├─< notifications    (user_id)
        └─< calendar_events  (user_id)

events ─┬─< bookings         (event_id)
        ├─< forum_posts      (event_id)
        ├─< feedback         (event_id)
        └─< calendar_events  (event_id)

bookings ─< payments         (booking_id)
forum_posts ─< comments      (post_id)
```

See `backend/database/schema.sql` for the full DDL.

---

## Local Development Setup

### Prerequisites

- **PHP 8.1+** with PDO MySQL extension
- **Composer** (PHP dependency manager)
- **MySQL 5.7+** or MariaDB 10.3+ (or TiDB Cloud)
- **Node.js 18+** and npm

### Step 1 — Database

```bash
# Start MySQL, then import schema:
mysql -u root -p < backend/database/schema.sql

# Seed demo data (creates demo users with hashed passwords):
cd backend
cp .env.example .env       # Edit DB credentials
composer install
php database/seed.php
```

### Step 2 — Backend (PHP Slim)

```bash
cd backend
cp .env.example .env       # Set DB_HOST, DB_USER, DB_PASS, JWT_SECRET
composer install

# Start the PHP built-in server for local dev:
php -S localhost:8080 -t public

# Test the API:
curl http://localhost:8080/api/health
# → {"status":"ok","timestamp":"…","env":"development","backend":"PHP Slim 4"}
```

### Step 3 — Frontend (Vue.js)

```bash
cd frontend
cp .env.example .env       # VITE_API_BASE_URL can stay blank (Vite proxies /api → :8080)
npm install
npm run dev
# → http://localhost:5173
```

### Step 4 — Verify

1. Open http://localhost:5173
2. Click "Sign in" and use a demo account (see below)
3. Visit `/gallery` (public) and `/dashboard` (auth required)

---

## Demo Accounts

| Role      | Email                       | Password        |
| --------- | --------------------------- | --------------- |
| Organizer | organizer@unievents.test    | organizer123    |
| Student   | student@unievents.test      | student123      |
| Student   | loai@unievents.test         | loai123         |
| Admin     | admin@unievents.test        | admin123        |

> Passwords are bcrypt-hashed in the database. The seed script (`backend/database/seed.php`) generates them on first run.

---

## Deployment

> **Recommended free stack for SECJ3483 demo**:
> **Render** (PHP Slim backend, Docker) + **Vercel** (Vue frontend) + **TiDB Cloud** (MySQL).
> All three have free tiers that are generous enough for a course demo.

### Option A — Render (PHP backend) + Vercel (Vue frontend) ⭐ recommended

This is the deployment used by the FreshDev team. The PHP Slim 4 backend ships
as a Docker image (see `backend/Dockerfile` + `backend/docker/`), so Render's
native Docker runtime runs it without any PHP-specific setup.

#### Step 1 — Database (TiDB Cloud free tier)

1. Sign up at [tidbcloud.com](https://tidbcloud.com) → create a free cluster
2. Note the connection details: `host`, `port` (4000), `user`, `password`
3. Allow public IP access (or set `DB_SSL=true`)
4. Import schema: in the TiDB SQL editor, run `backend/database/schema.sql`
5. (Optional) Seed demo data: run `php backend/database/seed.php` locally
   with the TiDB credentials in `.env`

#### Step 2 — Backend on Render

1. Push this repo to GitHub
2. On Render → **New → Web Service** → connect the repo
3. Configure:
   - **Root Directory**: `backend`
   - **Runtime**: Docker (auto-detected from `Dockerfile`)
   - **Plan**: Free
   - **Health Check Path**: `/api/health`
4. Set environment variables (Render UI):

   | Key             | Value                                            |
   | --------------- | ------------------------------------------------ |
   | `APP_ENV`       | `production`                                     |
   | `APP_DEBUG`     | `false`                                          |
   | `DB_HOST`       | `gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com` |
   | `DB_PORT`       | `4000`                                           |
   | `DB_NAME`       | `unievent_db`                                    |
   | `DB_USER`       | `<your_tidb_user>.root`                          |
   | `DB_PASS`       | `<your_tidb_password>`                           |
   | `DB_SSL`        | `true`                                           |
   | `JWT_SECRET`    | `<long random string>` (use `openssl rand -hex 32`) |
   | `JWT_EXPIRES_HOURS` | `24`                                         |
   | `CORS_ORIGIN`   | `https://<your-app>.vercel.app` (set after Step 3) |

5. Deploy → wait for "Live" status → test `https://<your-app>.onrender.com/api/health`

> **One-click alternative**: in Render click **New → Blueprint**, select the
> repo, and Render will read `backend/render.yaml` automatically — only env
> vars marked `sync: false` need to be filled in manually.

#### Step 3 — Frontend on Vercel

1. Push the same repo to GitHub (Vercel reads from the same repo)
2. On Vercel → **New Project** → import the repo
3. Configure:
   - **Root Directory**: `frontend`
   - **Framework Preset**: Vite (auto-detected)
   - **Build Command**: `npm run build`
   - **Output Directory**: `dist`
4. Set environment variable: `VITE_API_BASE_URL=https://<your-app>.onrender.com`
5. Deploy → you get `https://<your-app>.vercel.app`
6. **Go back to Render** and update `CORS_ORIGIN` to the Vercel URL, redeploy

#### Step 4 — Verify

| Check | URL                                                            | Expected                  |
| ----- | -------------------------------------------------------------- | ------------------------- |
| API   | `https://<backend>.onrender.com/api/health`                    | `{"status":"ok",...}`     |
| Frontend | `https://<frontend>.vercel.app/gallery`                    | Loads event gallery       |
| Auth  | `POST https://<backend>.onrender.com/api/auth/login`           | Returns JWT               |
| CORS  | From frontend → backend                                        | No CORS errors in console |

> **Render free-tier caveat**: the service sleeps after 15 min of inactivity
> and takes ~50 s to spin up on the next request. The first call after sleep
> may time out — just refresh. For a smoother demo, ping `/api/health` from
> [cron-job.org](https://cron-job.org) every 10 min to keep it warm.

---

### Option B — Shared hosting (cPanel / Apache + PHP + MySQL)

1. **Upload** the `backend/` folder to your hosting (e.g. `public_html/unievent/api/`)
2. **Run** `composer install` on the server (or upload `vendor/` from local)
3. **Create** a MySQL database and import `backend/database/schema.sql`
4. **Run** `php database/seed.php` once (via SSH or cron) to seed demo data
5. **Create** `.env` from `.env.example` with production DB credentials and a strong `JWT_SECRET`
6. **Build** the frontend: `cd frontend && npm install && npm run build`
7. **Upload** `frontend/dist/` contents to `public_html/unievent/`
8. **Configure** `.htaccess` so the SPA's routes fall back to `index.html`

### Option C — VPS (Ubuntu/Debian with Apache + PHP-FPM)

```bash
# Install prerequisites
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring \
                    composer mysql-server nodejs npm nginx

# Clone project, set permissions
sudo chown -R www-data:www-data /var/www/unievent

# Nginx vhost example (/etc/nginx/sites-available/unievent):
# server {
#   listen 80;
#   server_name unievent.example.com;
#   root /var/www/unievent/frontend/dist;
#   location / { try_files $uri $uri/ /index.html; }
#   location /api/ {
#     fastcgi_pass unix:/run/php/php8.2-fpm.sock;
#     fastcgi_param SCRIPT_FILENAME /var/www/unievent/backend/public/index.php;
#     include fastcgi_params;
#   }
# }
```

### Option D — TiDB Cloud connection (any PHP host)

The PDO connection works with TiDB out of the box. Just set in `backend/.env`:

```
DB_HOST=gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com
DB_PORT=4000
DB_USER=your_tidb_user.root
DB_PASS=your_tidb_password
DB_NAME=unievent_db
DB_SSL=true
```

---

## Testing Guide

1. **Login**: Use any demo account from the table above
2. **Browse Events**: Visit `/gallery` to see events from the database (no login needed)
3. **Filter & Search**: Use the category pills, search bar, and sort dropdown
4. **View Dashboard**: After login, `/dashboard` shows your bookings, stats, and notifications
5. **Book Tickets**: As a student, book tickets for an event
6. **Simulate Payment**: Complete the payment flow (90% success rate for demo)
7. **Manage Events**: As organizer, create/edit/delete events at `/manage-events`
8. **Forum Discussion**: Create posts and comments at `/forum`
9. **Submit Feedback**: Rate and review events at `/feedback`
10. **Calendar Sync**: Booked events appear in your calendar automatically
11. **Notifications**: Booking/payment confirmations appear in the notifications panel
12. **JWT Verification**: Try accessing `/api/dashboard/1` without a token → 401 Unauthorized

---

## Team — FreshDev (Section 02)

| No. | Name                                  | Matrics     | Role              | Modules                                          |
| --- | ------------------------------------- | ----------- | ----------------- | ------------------------------------------------ |
| 1   | Siti Nur Fathiyyah binti Marzukee     | A23CS0269   | Team Lead         | Event Management, Notification & Calendar        |
| 2   | Muhammad Amir Zafri Bin Mohd Adhar    | A23CS0120   | Backend Lead      | Ticket Booking, Payment (PHP Slim REST API)      |
| 3   | Fatema Junaed                         | A23CS0016   | Frontend Lead     | Forum Discussion, Community Feedback             |
| 4   | Loai Rafaat Hameed AlQadasi           | A23EC9010   | UI/UX & Testing   | **Event Gallery, User Dashboard, README**        |

---

## Notes for Team Members

### For Fatema (Frontend Lead — Forum, Feedback)

Use the shared components established by Loai to keep the visual language consistent:

- `components/shared/EventCard.vue` — for showing event context in forum posts
- `components/shared/EmptyState.vue` — for "no posts yet" / "no comments yet"
- `components/shared/LoadingSpinner.vue` — for async data fetches
- `components/shared/SearchBar.vue` — for searching forum posts
- `utils/validators.js` — use `forumPostSchema` and `feedbackSchema` for form validation
- `utils/format.js` — for date formatting in post listings

### For Siti (Team Lead — Event Management, Notifications, Calendar)

- Reuse `EventCard.vue` in the event management list view
- Use `StatCard.vue` for the organizer dashboard (events created, total bookings, etc.)
- The notification icon styling in `DashboardView.vue` (`.notif-item__icon--success` etc.) should be reused in the full Notifications page

### For Amir (Backend Lead — Bookings, Payments)

The PHP Slim backend is already scaffolded in `backend/`. To extend it:

- Add new routes inside the relevant `src/Routes/*Routes.php` class
- Use the `JsonResponder` trait for consistent JSON responses
- Use `App\Database::pdo()` for all DB access — already a singleton PDO
- Apply `AuthMiddleware` and `RoleMiddleware` to protected routes:

```php
$g->post('/example', function (Request $req, Response $res) { ... })
  ->add(new RoleMiddleware('student'))
  ->add(new AuthMiddleware());
```
