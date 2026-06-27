# HFST Fire Safety ERP Platform

A premium, custom Enterprise Resource Planning (ERP) platform designed for fire protection engineering and compliance management. Built using a high-performance decoupled architecture:
* **Backend:** Laravel 12 API (SQLite database sandbox, fully ready for PostgreSQL/MySQL)
* **Frontend:** SvelteKit 2 + Svelte 5 (styled with pure CSS in dark & light themes, powered by Svelte 5 Runes state management)

---

## 🚀 Getting Started

Follow these steps to run the application locally after cloning from GitHub.

### 1. Backend Setup (Laravel 12 API)

1. Open your terminal and navigate to the backend directory:
   ```bash
   cd backend
   ```
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Create your local SQLite database file:
   * **Windows (PowerShell):**
     ```powershell
     New-Item -Path "database/database.sqlite" -ItemType File
     ```
   * **Mac / Linux:**
     ```bash
     touch database/database.sqlite
     ```
4. Copy the environment template to create your `.env` configuration file:
   * **Windows:**
     ```powershell
     Copy-Item -Path ".env.example" -Destination ".env"
     ```
   * **Mac / Linux:**
     ```bash
     cp .env.example .env
     ```
5. Generate the application encryption key:
   ```bash
   php artisan key:generate
   ```
6. Run migrations and seed the database with verification data:
   ```bash
   php artisan migrate:fresh --seed
   ```
7. Start the local API server:
   ```bash
   php artisan serve
   ```
   The backend API will run on `http://127.0.0.1:8000`.

---

### 2. Frontend Setup (SvelteKit 2)

1. Open a new terminal and navigate to the frontend directory:
   ```bash
   cd frontend
   ```
2. Install Node dependencies:
   ```bash
   npm install
   ```
3. Start the Vite development server:
   ```bash
   npm run dev -- --port 5173
   ```
   The frontend application will run on `http://localhost:5173/`.

---

## 🔑 Administrator Login Credentials

Log in using the following details to access the dashboard and product directories:
* **Email:** `admin@hfsterp.com`
* **Password:** `admin123`

---

## 🛡 Key Compliance & ERP Design Rules

1. **Strict Data Integrity (Referential Locks):**
   Products that have historical transactions (e.g. references in delivery challans, quote versions, or stock ledgers) cannot be deleted. The API automatically blocks these requests with a `409 Conflict` database integrity notice.
2. **Double-Entry Stock Ledger:**
   Inventory values are calculated dynamically by parsing historical double-entry movements (e.g. GRN vs. Dispatches to project sites).
3. **NBR VAT Compliance:**
   The simple billing module supports toggling NBR 15% VAT addition or VAT-Free (Exempt) invoicing on standard equipment lists.
4. **Light & Dark Mode Support:**
   The webapp includes a fixed theme toggle button (`☀️`/`🌙`) at the top right to switch themes instantly.
