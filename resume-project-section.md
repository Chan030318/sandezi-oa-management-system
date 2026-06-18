# Resume — Project Section

Paste these blocks into your resume under a "Projects" section.

---

## FORMAT A — Concise (1-page resume style)

**Sandezi OA & Asset Management System** | PHP, MySQL, PDO, HTML/CSS, JavaScript | [GitHub](https://github.com/Chan030318/sandezi-oa)
- Designed and built a full-stack internal OA system for a live-streaming e-commerce company across 3 development phases (~6 weeks); replaced all WhatsApp/Excel manual workflows for 5 departments
- Implemented 3-role RBAC (Admin / Manager / Employee) with dual-layer enforcement; applied CSRF, XSS, and SQL injection protections across 29 PHP files (29 CSRF check points · 246 XSS sites · 90 prepared statements)
- Built complete device asset lifecycle: inventory → borrow workflow (date-overlap conflict detection + DB transactions for atomic state) → maintenance tracking → scrap, covering 50+ production devices
- Delivered 5-type CSV report export with UTF-8 BOM for Excel compatibility; deployed to Linux/Apache/MySQL production server

---

**Sandezi Official Website CMS** | PHP, HTML/CSS, JavaScript, JSON | [GitHub](https://github.com/Chan030318/sandezi-website)
- Built a custom file-based CMS and public-facing website for a live-streaming brand; admin panel manages products, news, announcements, and FAQ with no third-party CMS dependency
- SEO features: dynamic sitemap.xml generation, robots.txt, Open Graph meta tags, stable slug URLs for link integrity
- CSRF-protected admin panel; server-side image upload validation

---

**SmartCalc Malaysia** | PHP, Vanilla JavaScript, HTML/CSS | [Live](http://39.105.92.5/smartcalc.php)
- Built a free, client-side-only calculator suite for Malaysian users: BMI (metric/imperial), salary deductions (EPF/SOCSO/EIS/PCB), and loan repayment amortisation planner
- Fully mobile-responsive; no signup, no data collection, single PHP file delivery

---

## FORMAT B — Detailed (2-page resume / senior-format)

### Sandezi OA & Asset Management System
*Freelance Contract — Sandezi (三德子), Kuala Lumpur | [Month Year] – [Month Year]*
*PHP 7.4 · MySQL 5.7 · PDO · HTML5/CSS3 · Vanilla JS · Apache · Git*

A full-stack internal Office Automation system built for a live-streaming e-commerce company, replacing manual WhatsApp and Excel-based processes across HR and device operations for 30+ staff.

**Technical highlights:**
- **Architecture:** 29 PHP files with shared includes (`auth.php`, `db.php`) as a single source of truth for RBAC, CSRF helpers, and XSS sanitisation; no external framework dependencies
- **Security:** Synchroniser token CSRF (29 verification points), `htmlspecialchars` XSS escaping (246 call sites), PDO prepared statements only (90 prepare() calls), `intval()` on all ID parameters (78 call sites), bcrypt password hashing — zero GET-based mutations
- **Device lifecycle:** Designed a 4-status asset state machine (`空闲` → `使用中` → `维修中` → `报废`); used DB transactions (`beginTransaction/commit/rollBack`) for atomic state sync on borrow approval and maintenance status changes; implemented date-overlap conflict detection (`borrow_start <= date_to AND borrow_end >= date_from`)
- **Database:** 11 tables with FK constraints, ENUM status fields, and composite UNIQUE keys; `deploy_production.sql` for clean production deployment
- **Reporting:** 5 CSV export types with UTF-8 BOM prefix for Excel/Windows compatibility; date range and status filters

**Delivered across 3 phases:** Phase 1 (HR core) → Phase 2 (security hardening, audit logging, search & pagination) → Phase 3 (device asset management module)

---

## SKILLS SECTION (for resume)

**Languages:** PHP, JavaScript, TypeScript, HTML5, CSS3, SQL
**Frameworks:** Next.js, React
**Database:** MySQL, PDO
**Tools:** Git, GitHub, Apache, XAMPP
**Security:** CSRF, XSS prevention, SQL injection prevention (PDO), bcrypt, RBAC
**Concepts:** RBAC, REST API, OOP, Database design, Session management, CSV data export

---

*Update GitHub URLs and employment dates before printing.*
