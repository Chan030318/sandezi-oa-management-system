# Sandezi OA & Asset Management System

> A full-featured internal Office Automation system built for a live-streaming e-commerce company, covering HR management, shift scheduling, leave approval, device asset tracking, and operational reporting — all within a role-based access control architecture.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Architecture](#system-architecture)
- [Database Design](#database-design)
- [Security Features](#security-features)
- [Role & Permission Matrix](#role--permission-matrix)
- [Installation](#installation)
- [Project Structure](#project-structure)
- [Screenshots](#screenshots)
- [Roadmap](#roadmap)

---

## Overview

Sandezi OA is a **PHP/MySQL web-based internal management system** developed across 3 phases:

| Phase | Scope | Status |
|---|---|---|
| Phase 1 | Core HR: employees, departments, scheduling, leave, announcements | ✅ Complete |
| Phase 2 | User management, login logs, reports, search & pagination, security hardening | ✅ Complete |
| Phase 3 | Device asset management: inventory, borrowing workflow, maintenance & scrap tracking, asset reports | ✅ Complete |
| Phase 4 | Venue / studio management | 🔲 Planned |
| Phase 5 | Streamer performance & operations analytics | 🔲 Planned |

**Tech Stack:** PHP 7.4+, MySQL 5.7+, PDO, HTML5/CSS3, vanilla JS  
**Lines of code:** ~4,200 PHP · ~210 CSS  
**Database tables:** 11  
**PHP files:** 29

---

## Features

### 👥 HR Management
- **Employee Directory** — CRUD with keyword search (name, email, phone, position, department) and pagination (20/page)
- **Department Management** — CRUD with referential integrity check before deletion
- **Shift Scheduling** — Define shift templates; assign employees to dates; enforce one-shift-per-employee-per-day
- **Weekly Schedule View** — Grid overview of all employees' weekly assignments
- **Leave Application** — Multi-type leave requests with date-range validation
- **Leave Approval** — Admin/Manager approve or reject with remarks; status tracking

### 📢 Announcements
- Create, edit, delete announcements (Admin/Manager)
- All staff can view; `pre-wrap` rendering preserves formatting

### 👤 User & Access Management
- User account CRUD with role assignment (Admin / Manager / Employee)
- Link system accounts to employee records
- Enable/disable accounts with self-protection (cannot deactivate own account)
- Password reset by Admin

### 🔐 Self-Service
- Profile editing: name, email, phone (syncs to linked employee record)
- Self-service password change with current password verification

### 📋 Login Audit Log
- Every login attempt (success & failure) recorded: email, user ID, IP, User-Agent, timestamp
- Admin-only view with email/IP/status search and pagination

### 📦 Device Asset Management
- **Device Inventory** — Full CRUD; fields: device code, asset code, name, category, brand, model, serial number, department, owner, status
- **Borrow Workflow** — Employee submits request → Admin/Manager approves/rejects → device returned; date-overlap conflict detection; atomic status sync via DB transactions
- **Maintenance Tracking** — Report issues; Admin/Manager updates status (pending → in-repair → completed / scrapped); each status transition syncs device status automatically
- **Status lifecycle:** `空闲` (idle) → `使用中` (in-use) → `维修中` (in-repair) → `报废` (scrapped)

### 📊 Report Export
- 5 CSV export types, all with **UTF-8 BOM** for seamless Excel compatibility:
  - Shift Schedule Records
  - Leave Records
  - Device Inventory
  - Device Borrow Records
  - Device Maintenance Records
- Date range filter on all reports; device status filter on inventory & maintenance exports

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Browser (Client)                        │
└───────────────────────────────┬─────────────────────────────────┘
                                │ HTTPS
┌───────────────────────────────▼─────────────────────────────────┐
│                    Apache / PHP 7.4+                            │
│                                                                 │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────────────┐   │
│  │ auth.php │  │  db.php  │  │  header.php / footer.php   │   │
│  │ (RBAC)   │  │  (PDO)   │  │  (Layout + Navigation)     │   │
│  └────┬─────┘  └────┬─────┘  └────────────────────────────┘   │
│       │              │                                          │
│  ┌────▼──────────────▼──────────────────────────────────────┐  │
│  │              Business Logic Layer (PHP Pages)             │  │
│  │  employees · departments · schedule · leave · devices     │  │
│  │  device_borrow · device_maintenance · reports · users     │  │
│  └───────────────────────────┬──────────────────────────────┘  │
└──────────────────────────────│──────────────────────────────────┘
                               │ PDO Prepared Statements
┌──────────────────────────────▼──────────────────────────────────┐
│                      MySQL 5.7+ Database                        │
│                                                                 │
│  departments · employees · users · shifts · schedules           │
│  leaves · announcements · login_logs                            │
│  devices · device_borrows · device_maintenance                  │
└─────────────────────────────────────────────────────────────────┘
```

**Design Pattern:** Procedural PHP with shared includes (`auth.php`, `db.php`, `header.php`)  
**Session Management:** PHP native sessions with CSRF token  
**No framework dependency** — zero external PHP dependencies beyond PDO

---

## Database Design

### Entity Relationship Overview

```
departments ◄─── employees ◄─── users
                     │               │
                  schedules       login_logs
                  leaves
                  device_borrows ──► devices ◄── device_maintenance
                  announcements (created_by → users)
```

### Table Summary

| Table | Purpose | Key Fields |
|---|---|---|
| `departments` | Organisational units | id, name, description |
| `employees` | Staff records | name, department_id, position, phone, email, status |
| `users` | System accounts | employee_id, email, password_hash, role, status |
| `shifts` | Shift templates | name, start_time, end_time |
| `schedules` | Daily assignments | employee_id, shift_id, work_date *(UNIQUE)* |
| `leaves` | Leave requests | employee_id, type, start/end_date, status |
| `announcements` | Company notices | title, content, created_by |
| `login_logs` | Audit trail | email, user_id, ip, user_agent, status |
| `devices` | Asset registry | device_code *(UNIQUE)*, name, category, status |
| `device_borrows` | Borrow workflow | device_id, employee_id, dates, status, approved_by |
| `device_maintenance` | Repair history | device_id, issue_title, status, cost, handled_by |

---

## Security Features

| Feature | Implementation |
|---|---|
| **CSRF Protection** | Token-per-session; `csrf_field()` on every POST form; `verify_csrf()` on every handler — **29 verification points** |
| **XSS Prevention** | `safe()` = `htmlspecialchars(ENT_QUOTES, UTF-8)` — **246 call sites** across all templates |
| **SQL Injection Prevention** | PDO Prepared Statements exclusively — **90 prepare() calls**, zero string interpolation in queries |
| **Password Security** | `password_hash(PASSWORD_DEFAULT)` (bcrypt); `password_verify()` for all checks |
| **ID Tampering Prevention** | `intval()` on every `$_GET`/`$_POST` ID parameter — **78 call sites** |
| **IDOR Prevention** | Role double-check at handler level; employees can only access own records |
| **No GET-based Mutations** | All delete/update operations via POST forms only |
| **DB Error Masking** | `try/catch PDOException` → `error_log()` only; generic user-facing message |
| **Atomic Transactions** | `beginTransaction()` / `commit()` / `rollBack()` for device status sync |
| **Config Exclusion** | `config.php` in `.gitignore`; `config.example.php` as deployment template |

---

## Role & Permission Matrix

| Feature | Admin | Manager | Employee |
|---|:---:|:---:|:---:|
| Employee / Department CRUD | ✓ | ✓ | — |
| Shift / Schedule Management | ✓ | ✓ | — |
| Leave Approval | ✓ | ✓ | — |
| Announcements (Create/Edit/Delete) | ✓ | ✓ | — |
| Device CRUD | ✓ | ✓ | view only |
| Borrow Approval & Return | ✓ | ✓ | — |
| Maintenance Status Update | ✓ | ✓ | — |
| Report Export (CSV) | ✓ | ✓ | — |
| User Account Management | ✓ | — | — |
| Login Audit Log | ✓ | — | — |
| Leave / Borrow / Repair Submission | ✓ | ✓ | ✓ |
| View Own Schedule / Leave | ✓ | ✓ | ✓ |
| Profile & Password (Self) | ✓ | ✓ | ✓ |

---

## Installation

### Requirements
- PHP 7.4+ with PDO and PDO_MySQL
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (or Nginx)

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/your-username/sandezi-oa.git
cd sandezi-oa

# 2. Set up database
mysql -u root -p < deploy_production.sql

# 3. Configure application
cp config.example.php config.php
# Edit config.php with your DB credentials and app name

# 4. Access the system
# http://your-domain/
# Default Admin: admin@sandezi.com / SdzAdmin2026!
# ⚠️ Change the default password immediately after first login
```

### Production Checklist
- [ ] Enable HTTPS (Let's Encrypt recommended)
- [ ] Change default Admin password
- [ ] Set `display_errors = Off` in `php.ini`
- [ ] Configure daily MySQL backup (`mysqldump` cron job)
- [ ] Add `Secure; HttpOnly; SameSite=Lax` to session cookie config

---

## Project Structure

```
sandezi-oa/
├── auth.php                  # Auth, RBAC helpers, CSRF, safe()
├── db.php                    # PDO connection with error masking
├── header.php                # Global layout: sidebar nav + topbar
├── footer.php                # Closing layout tags
├── login.php                 # Login form + audit log writer
├── logout.php                # Session destruction
├── dashboard.php             # Overview statistics
│
├── employees.php             # Employee CRUD + search + pagination
├── departments.php           # Department CRUD
├── shifts.php                # Shift template CRUD
├── schedule.php              # Daily scheduling
├── weekly_schedule.php       # Weekly overview grid
│
├── leave_apply.php           # Leave request (employee)
├── leave_manage.php          # Leave approval (admin/manager)
├── my_leave.php              # Own leave history
├── my_schedule.php           # Own schedule view
│
├── announcements.php         # Announcements CRUD + view
│
├── users.php                 # User account management
├── user_edit.php             # Edit role / status / password
├── login_logs.php            # Login audit log viewer
│
├── profile.php               # Self-service profile edit
├── change_password.php       # Self-service password change
│
├── devices.php               # Device inventory CRUD
├── device_borrow.php         # Borrow application + own records
├── device_borrow_manage.php  # Borrow approval + return management
├── device_maintenance.php    # Maintenance report + management
│
├── reports.php               # CSV export (5 report types)
│
├── assets/
│   ├── style.css             # ~210 lines custom design system
│   └── logo.jpg              # Company logo
│
├── config.php                # DB credentials (git-ignored)
├── config.example.php        # Deployment template
├── deploy_production.sql     # Production schema + seed data
└── schema.sql                # Development schema with test data
```

---

## Screenshots

> *(Add screenshots after deployment)*

| Page | Description |
|---|---|
| Login | Login page with company branding |
| Dashboard | Overview statistics |
| Employees | Staff list with keyword search |
| Weekly Schedule | Grid view of weekly assignments |
| Devices | Inventory with coloured status badges |
| Borrow Manage | Approval queue with return modal |
| Reports | 5-category CSV export page |

---

## Roadmap

| Feature | Status |
|---|---|
| Phase 4 — Venue / studio booking | 🔲 Planned |
| Phase 5 — Streamer analytics dashboard | 🔲 Planned |
| QR Code labels for device assets | 🔲 Backlog |
| Device photo uploads | 🔲 Backlog |
| Periodic inventory audit module | 🔲 Backlog |
| Attendance / time-clock module | 🔲 Backlog |
| WeChat Work approval webhook | 🔲 Backlog |
| Login rate limiting | 🔲 Backlog |
| Mobile-responsive UI | 🔲 Backlog |

---

## License

Internal proprietary system. Not licensed for public distribution.

---

*Built with PHP, MySQL, and a lot of `verify_csrf()` calls.*
