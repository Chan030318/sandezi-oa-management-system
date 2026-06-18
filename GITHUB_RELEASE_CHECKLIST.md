# GitHub Release Checklist

**Project:** Sandezi OA & Asset Management System  
**Checked:** 2026-06-18  
**Status:** ✅ Ready to push

---

## Security Checks

| Item | Status | Notes |
|---|---|---|
| `config.php` excluded from git | ✅ Fixed | Was accidentally tracked — removed via `git rm --cached` in commit `7f60096` |
| `.gitignore` covers credentials | ✅ Fixed | Rewrote with UTF-8 encoding; covers `config.php`, `config.local.php`, logs, editor dirs |
| `config.example.php` provided | ✅ OK | Safe template with placeholder values only |
| No hardcoded passwords in PHP files | ✅ OK | All credentials loaded from `config.php` via `define()` |
| No API keys in codebase | ✅ OK | No external API integrations |
| No `.env` files | ✅ OK | Not applicable (PHP config pattern used) |
| `deploy_production.sql` safe to publish | ✅ OK | Contains schema + seed data only; no real user data |
| `schema.sql` safe to publish | ✅ OK | Development schema with anonymised test structure |

---

## Repository Structure Checks

| Item | Status | Notes |
|---|---|---|
| `README.md` exists | ✅ OK | Full professional README with architecture, security, installation |
| `LICENSE` | ⚠️ Optional | No LICENSE file — add MIT or keep as proprietary (see note below) |
| `.gitignore` correct | ✅ Fixed | Covers all necessary exclusions |
| `config.example.php` present | ✅ OK | Deployment template available |
| `deploy_production.sql` present | ✅ OK | Ready for production setup |
| Documentation files present | ✅ OK | `PORTFOLIO.md`, `PHASE3_DEVICE_REPORT.md`, `INTERNAL_TESTING_REPORT.md` |

---

## Content Checks

| Item | Status | Notes |
|---|---|---|
| No real employee personal data in code | ✅ OK | No PII in any PHP or SQL files |
| No real device serial numbers | ✅ OK | Schema only, no actual inventory data |
| No client credentials exposed | ✅ OK | All credentials removed |
| Company logo included | ✅ OK | `assets/logo.jpg` — confirm client approval if making repo public |

---

## LICENSE Note

- **If keeping repo public as portfolio:** Add MIT License (signals open attitude, common for portfolios)
- **If marking as proprietary work:** Add a note in README: *"Internal proprietary system, published for portfolio demonstration purposes."*
- **Recommendation:** Add the proprietary note to README — you built it for a client, so MIT may not be appropriate

---

## Pre-Push Final Commands

```bash
# Verify config.php is NOT tracked
git ls-files | grep config.php
# Expected output: nothing (empty)

# Verify .gitignore is working
git check-ignore -v config.php
# Expected: .gitignore:1:config.php  config.php

# Final status check
git status
# Expected: nothing to commit, working tree clean
```

---

## Sandezi Official Website Checklist

| Item | Status | Notes |
|---|---|---|
| `config/admin_config.php` excluded | ✅ OK | In `.gitignore` |
| `smartcalc.php` committed | ⚠️ Pending | Untracked — commit before push if including |
| `.claude/` excluded | ⚠️ Pending | Add `.claude/` to website `.gitignore` |
| `uploads/` folder in repo | ⚠️ Review | Contains client images — confirm client approval before making public |
| `video/` folder in repo | ⚠️ Review | Contains large MP4 file — may hit GitHub 100MB file limit |
| `README.md` exists | ❌ Missing | Only `README.txt` — convert to `.md` before GitHub upload |

---

*Checklist generated: 2026-06-18*
