# ID Tracker System

A complete Laravel-based Employee ID Management System with Excel import/export, role-based access control, status tracking, and full audit trail.

---

## Requirements

| Tool        | Version     |
|-------------|-------------|
| PHP         | 8.2+        |
| Laravel     | 12.x        |
| MySQL/MariaDB | 5.7+ / 10.3+ |
| Composer    | 2.x         |
| XAMPP       | Latest      |

---

## Installation

### 1. Set Up MySQL

Open phpMyAdmin or MySQL CLI and create the database:

```sql
CREATE DATABASE id_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configure Environment

```bash
copy .env.example .env
```

Edit `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=id_tracker
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Install Dependencies

```bash
composer install
```

### 4. Generate App Key

```bash
php artisan key:generate
```

### 5. Run Migrations and Seeders

```bash
php artisan migrate --seed
```

This creates all tables and seeds:
- **Admin account**: `admin` / `Admin@1234`
- **Staff account**: `staff1` / `Staff@1234`
- **7 sample ID records** with various statuses

### 6. Access the Application

**Via XAMPP Apache:**
```
http://localhost/id-tracker/public/
```

**Via built-in PHP server:**
```bash
php artisan serve
```
Then open: `http://localhost:8000`

---

## Default Accounts

| Username | Password   | Role          |
|----------|------------|---------------|
| admin    | Admin@1234 | Administrator |
| staff1   | Staff@1234 | User          |

> **Change these passwords immediately in production.**

---

## Features

### Dashboard
- Status summary cards (Total, Pending, For Processing, Ready, Released, Lost, Damaged, Cancelled)
- Click any status card to filter the ID list
- Recent activity feed (last 20 status changes)

### ID Records
- Full CRUD (admin only for create/edit/delete)
- Search by Name, ID Number, Position
- Filter by Status, Position, Date Hired range
- Sortable columns
- Pagination (25 per page)
- Soft deletes with restore

### Status Management
- Admin-only status changes (enforced server-side via Policy + Service + FormRequest)
- Every change recorded in audit history with old status, new status, who changed it, and optional remarks
- Bulk status change for multiple records at once
- Transaction-protected: if history fails, status update is rolled back

### ID Statuses
```
PENDING → FOR PROCESSING → READY → RELEASED
                                 → LOST
                                 → DAMAGED
                                 → CANCELLED
```

### Excel Import
- Validates required headers: `NAME, POS, IDNO, DATEH, BDATE, ECON, IMG, SIGN`
- Preview before committing (shows new/existing/invalid counts)
- Import modes: Add New Only | Update Existing Only | Add & Update
- Duplicate IDNO handling: updates employee info without changing status
- Full import log with error details

### Excel Export
- **Standard Template**: 8-column format (NAME, POS, IDNO, DATEH, BDATE, ECON, IMG, SIGN)
- **Tracker Report**: Includes STATUS column
- Filter by status or search term before exporting

### User Management (Admin only)
- Create, edit, activate/deactivate users
- Roles: Administrator, User
- Reset passwords
- Deactivated users are blocked at login

---

## Security

- CSRF protection on all forms
- Server-side authorization via Laravel Policies (not just UI hiding)
- `EnsureUserIsActive` middleware on every authenticated request
- Status changes require admin role at: FormRequest → Controller → Policy → Service
- Mass-assignment protection via `$fillable`
- Passwords hashed with bcrypt (12 rounds)
- SQL injection protection through Eloquent ORM
- Status values validated against `IdStatus` enum

---

## Testing

```bash
php artisan test
```

Critical test: regular user attempting `PATCH /id-records/1/status` returns **403 Forbidden** and the database status remains unchanged.

---

## Excel Template Format

| NAME | POS | IDNO | DATEH | BDATE | ECON | IMG | SIGN |
|------|-----|------|-------|-------|------|-----|------|
| Juan Dela Cruz | Staff | 100001 | 01/15/2020 | 05/10/1990 | Maria: 09171234567 | Z:\path\image.png | Z:\path\sign.png |

- **IDNO** is stored as a string (preserves leading zeroes)
- **DATEH / BDATE** accept `MM/DD/YYYY` format
- **IMG / SIGN** are stored as path strings (network paths like `Z:\...` are preserved as-is)
- Download the blank template from the Import page

---

## Project Structure

```
app/
  Enums/           IdStatus.php, UserRole.php
  Exports/         IdRecordExport.php, IdRecordTemplateExport.php
  Http/
    Controllers/   DashboardController, IdRecordController, IdStatusController,
                   IdStatusHistoryController, ImportController, ExportController,
                   UserController, Auth/LoginController
    Middleware/    EnsureUserIsActive.php
    Requests/      StoreIdRecordRequest, UpdateIdRecordRequest, ChangeStatusRequest,
                   BulkChangeStatusRequest, StoreUserRequest, UpdateUserRequest
  Imports/         IdRecordImport.php
  Models/          User, IdRecord, IdStatusHistory, ImportLog
  Policies/        IdRecordPolicy, UserPolicy
  Services/        IdStatusService, IdImportService

database/
  migrations/      users, id_records, id_status_histories, import_logs
  seeders/         UserSeeder, IdRecordSeeder

resources/views/
  layouts/         app.blade.php
  auth/            login.blade.php
  dashboard/       index.blade.php
  id-records/      index, show, create, edit, trashed + partials
  history/         index.blade.php
  imports/         index, preview, result
  exports/         index.blade.php
  users/           index, show, create, edit + partials
  errors/          403.blade.php, 404.blade.php

tests/Feature/
  AuthTest.php
  IdStatusTest.php
  ExcelImportTest.php
```
