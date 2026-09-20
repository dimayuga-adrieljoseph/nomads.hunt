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

## Environment

| Variable | Default | Notes |
|---|---|---|
| `DB_CONNECTION` | `mysql` | XAMPP MySQL |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `3306` | XAMPP default |
| `DB_DATABASE` | `nomads_hunt` | Create this in phpMyAdmin |
| `DB_USERNAME` | `root` | XAMPP default |
| `DB_PASSWORD` | *(empty)* | XAMPP default — change in production |

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
