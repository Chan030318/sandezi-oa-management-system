# Portfolio: Sandezi OA & Asset Management System

---

## Challenge

A fast-growing live-streaming e-commerce company was managing employee scheduling, leave requests, and device borrowing through WhatsApp messages, paper forms, and scattered Excel files. With 30+ staff across 5 departments and 50+ production devices (cameras, lights, computers), the manual process caused:

- Scheduling conflicts and no-shows due to miscommunication
- No visibility into which devices were available, borrowed, or broken
- Approval processes entirely via chat — no audit trail
- No centralised employee records or access control

The business needed an internal web system that all staff could use on any device, with clear permissions, without requiring any technical expertise from users.

---

## Solution

Designed and built a full-stack internal OA (Office Automation) system in PHP/MySQL across 3 development phases over ~6 weeks:

- **Phase 1:** Core HR — employee records, department management, shift scheduling, leave application & approval, company announcements
- **Phase 2:** Platform hardening — user account management, login audit logging, CSV report export, keyword search & pagination, full security patch pass
- **Phase 3:** Device asset management — inventory tracking, online borrow/return workflow with conflict detection, maintenance & scrap lifecycle, 5-type CSV reporting

The system replaced all WhatsApp/Excel processes and gave management real-time visibility into operations.

---

## Architecture

```
Browser → Apache/PHP 7.4 → PDO → MySQL 5.7
```

**Core design decisions:**

| Decision | Rationale |
|---|---|
| Procedural PHP, no framework | Zero onboarding friction for the client's future developer; no dependency management overhead for an internal tool of this scale |
| Shared `auth.php` include | Single source of truth for RBAC, CSRF helpers, and XSS sanitisation — enforced consistently across all 29 PHP files |
| PDO Prepared Statements only | Eliminates SQL injection at the data layer without an ORM; 90 prepare() calls across the codebase |
| DB Transactions for state changes | Device borrow approval and maintenance status updates touch 2 tables atomically — `beginTransaction()` / `rollBack()` prevents partial state corruption |
| UTF-8 BOM in CSV exports | Non-technical staff open reports directly in Excel on Windows — BOM prevents Chinese character encoding issues without requiring any user config |

**11 database tables** with FK constraints, ENUM status fields, and composite UNIQUE keys (e.g. `unique_employee_date` on schedules).

---

## Security

Built with defence-in-depth against the OWASP Top 10 most relevant to this application:

| Threat | Mitigation |
|---|---|
| CSRF | Synchroniser token pattern; 29 `verify_csrf()` call sites |
| XSS | `safe()` wrapper (htmlspecialchars ENT_QUOTES); 246 output call sites |
| SQL Injection | Exclusive use of PDO prepared statements; 90 prepare() calls |
| Broken Access Control | Role checked at route entry (`require_role()`) AND at POST handler level |
| IDOR | Employee-scoped queries; users can only read/write their own records |
| Sensitive Data Exposure | DB credentials in git-ignored `config.php`; bcrypt password hashing |
| Security Misconfiguration | DB errors caught and `error_log()`'d — never exposed to the browser |
| Insecure Design | No GET-based mutations; all state changes require POST |

---

## Result

- **29 PHP files**, ~4,200 lines of application code
- **11 database tables**, 3 with FK-enforced lifecycle relationships
- **5 CSV report types** replacing manual Excel extraction
- **3-role RBAC** (Admin / Manager / Employee) covering 16 distinct permission boundaries
- **22 git commits** with incremental feature delivery and a dedicated security patch commit
- System deployed on a shared Linux server running Apache + PHP + MySQL
- Covers the full device asset lifecycle from procurement logging to scrap — replacing a WhatsApp group as the "system of record"

**Production deployment artifacts included:**
- `deploy_production.sql` — clean production schema with seed data
- `config.example.php` — environment configuration template
- `INTERNAL_TESTING_REPORT.md` — stakeholder-facing test checklist

---

*Keywords: PHP, MySQL, PDO, RBAC, CSRF, OA System, Asset Management, Internal Tools*
