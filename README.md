# nomads.hunt

A full-stack web application built with **Vue.js** (frontend) and **Laravel** (backend), using **MySQL via XAMPP** for local development.

---

## Project Structure

```
nomads.hunt/
├── backend/    # Laravel 13 API
└── frontend/   # Vue 3 + TypeScript + Vite
```

---

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) — for MySQL during development
- PHP 8.2+
- Composer
- Node.js 18+
- npm

---

## Getting Started

### 1. Start XAMPP MySQL

Open the XAMPP Control Panel and start the **MySQL** module.

Then create the database:

```sql
CREATE DATABASE nomads_hunt;
```

---

### 2. Backend (Laravel)

```bash
cd backend

# Install PHP dependencies (first time only)
composer install

# Copy env and generate key (first time only)
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start the dev server
php artisan serve
```

Laravel will run at **http://localhost:8000**.

---

### 3. Frontend (Vue.js)

```bash
cd frontend

# Install JS dependencies (first time only)
npm install

# Start the dev server
npm run dev
```

Vue will run at **http://localhost:5173**.

API calls to `/api/*` are automatically proxied to the Laravel backend via Vite's dev proxy — no CORS issues during development.

---

## The Claim Ladder (two-stage lifecycle)

MINE and STEAL both run through **two separate stages**. Claim expiration does
**not** mean the claimant failed — it means they survived the claim period and are
now allowed to pay:

```
              claim_expires_at            payment_expires_at
 CLAIM period ────────────────► PAYMENT window ──────────────► next claimant
 (holds the piece,               (same claimant may             (brand new
  others may STEAL)               now pay)                       CLAIM period)
```

| Stage | Claimant can pay? | What happens at the deadline |
|---|---|---|
| `phase = claim` | No | Payment window opens for the **same** claimant |
| `phase = payment` | Yes | Claim expires → next queued claimant gets a **new claim period** |

* **MINE** — first Mine holds the piece (`claim` phase). Later Mines queue.
* **STEAL** — overrides the active Mine during its `claim` phase, then runs its own
  `claim` → `payment` sequence. Mine is locked once a Steal is active.
* **GRAB** — buy now: skips the claim stage entirely (`grab` → `payment` → `SOLD`).
* Every queued claimant gets their own claim period; the queue is never skipped and
  ordering is preserved. The product only returns to `AVAILABLE` when the queue is
  exhausted.

Configure both stages in `backend/.env`:

```text
CLAIM_HOLD_SECONDS=60        # CLAIM stage duration
PAYMENT_WINDOW_SECONDS=60    # PAYMENT window duration
```

Timers shown in Vue are display-only — Laravel stores `claim_expires_at`,
`payment_starts_at` and `payment_expires_at` and re-validates on every request
(`php artisan claims:expire` and the admin **Force Expire** button run the same
logic, and the API also advances overdue stages lazily so no scheduler is needed).

---

## Environment

| Variable | Default | Notes |
|---|---|---|
| `DB_CONNECTION` | `mysql` | XAMPP MySQL |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `3306` | XAMPP default |
| `DB_DATABASE` | `nomads_hunt` | Create this in phpMyAdmin |
| `DB_USERNAME` | `root` | XAMPP default |
| `DB_PASSWORD` | *(empty)* | XAMPP default — change in production |
| `CLAIM_HOLD_SECONDS` | `60` | Duration of the claim (hold) stage |
| `PAYMENT_WINDOW_SECONDS` | `60` | Duration of the payment window |
| `FRONTEND_URL` | `http://localhost:5173` | Used for CORS |

---

## API Health Check

Once the backend is running, test it:

```
GET http://localhost:8000/api/health
```

Expected response:

```json
{ "status": "ok", "message": "NomadsHunt API is running." }
```
