# GitHub Push Guide — Chan030318

Step-by-step guide to push both projects to GitHub under username **Chan030318**.

---

## Prerequisites

- Git installed and configured
- GitHub account: **Chan030318**
- Personal Access Token (PAT) or SSH key set up

### Configure Git identity (one-time)

```bash
git config --global user.name "Alvin Chan"
git config --global user.email "alvinchanwunlong@gmail.com"
```

### Create a Personal Access Token (if not done)

1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token → select `repo` scope → copy the token
3. When Git asks for password, paste the token (not your GitHub password)

---

## Part 1: Push Sandezi OA System

### Step 1 — Create GitHub repo

1. Go to https://github.com/new
2. Repository name: `sandezi-oa`
3. Description: `Full-stack internal OA & asset management system for a live-streaming e-commerce company — PHP/MySQL, 3-role RBAC, device lifecycle, CSV reporting`
4. Set to **Public** (for portfolio) or **Private**
5. Do NOT initialise with README, .gitignore, or license (repo is already set up locally)
6. Click **Create repository**

### Step 2 — Connect and push

Open PowerShell in the OA project directory:

```powershell
cd "C:\Users\asus\Documents\39.105.92.5\sandezi_oa_phase1_plus\sandezi_oa_phase1_plus"
```

```bash
# Verify nothing sensitive is tracked
git ls-files | findstr config.php
# Expected: no output (empty)

# Add remote
git remote add origin https://github.com/Chan030318/sandezi-oa.git

# Verify remote
git remote -v

# Push
git push -u origin master
```

When prompted for credentials:
- Username: `Chan030318`
- Password: paste your Personal Access Token

### Step 3 — Add GitHub repo description

After push, on GitHub:
1. Click the gear icon next to "About"
2. Add topics: `php`, `mysql`, `oa-system`, `rbac`, `asset-management`, `internal-tools`
3. Add website URL if you have one

---

## Part 2: Push Sandezi Website

### Step 1 — Pre-push checks for website

```powershell
cd "C:\Users\asus\Documents\39.105.92.5\sandezi-website"
```

Check for large files:
```bash
git ls-files | findstr video
# If video files appear, consider using Git LFS or removing them
```

Commit pending files:
```bash
# Add smartcalc.php if you want it in the repo
git add smartcalc.php
git commit -m "add SmartCalc Malaysia calculator"

# Update .gitignore to exclude .claude/
# Edit .gitignore and add: .claude/
git add .gitignore
git commit -m "update .gitignore to exclude .claude directory"
```

### Step 2 — Create GitHub repo

1. Go to https://github.com/new
2. Repository name: `sandezi-website`
3. Description: `Custom PHP CMS and public website for a live-streaming e-commerce brand — product showcase, news, FAQ, admin panel, SEO`
4. Do NOT initialise with README
5. Click **Create repository**

### Step 3 — Connect and push

```bash
git remote add origin https://github.com/Chan030318/sandezi-website.git
git remote -v
git push -u origin master
```

---

## Part 3: Set up GitHub Profile README

The file `GITHUB_PROFILE_README.md` in the OA project directory contains your profile README.

1. Create a special repo: https://github.com/new
   - Repository name: **`Chan030318`** (must match your username exactly)
   - Set to **Public**
   - Check "Add a README file"
   - Click **Create repository**

2. Copy the content from `GITHUB_PROFILE_README.md`

3. Edit the README on GitHub (click the pencil icon on the `README.md` file)

4. Paste the content and commit

5. Visit https://github.com/Chan030318 to verify the profile card shows

---

## Part 4: Pin repositories

After pushing both repos:

1. Go to https://github.com/Chan030318
2. Click "Customize your pins"
3. Pin: `sandezi-oa` and `sandezi-website`
4. These will appear on your profile as featured work

---

## Troubleshooting

**"src refspec master does not match any"**
```bash
git branch
# If the branch is "main" not "master":
git push -u origin main
```

**"rejected — non-fast-forward"**
```bash
# Only if repo was created with files on GitHub:
git pull origin master --allow-unrelated-histories
git push -u origin master
```

**"remote: Repository not found"**
- Verify the repo exists on GitHub
- Verify username spelling: `Chan030318`
- Verify your token has `repo` scope

**Large file rejected (100MB limit)**
```bash
# Find large files
git ls-files | xargs ls -la 2>/dev/null | sort -k5 -rn | head -20
# If video files are tracked, remove them:
git rm --cached video/your-file.mp4
echo "video/" >> .gitignore
git add .gitignore
git commit -m "remove large video files from tracking"
```

---

## Final verification

After both repos are pushed:

- [ ] https://github.com/Chan030318/sandezi-oa — repo visible, README renders
- [ ] https://github.com/Chan030318/sandezi-website — repo visible
- [ ] https://github.com/Chan030318 — profile README shows, repos pinned
- [ ] `config.php` NOT visible in GitHub repo (check Files tab)
- [ ] `config/admin_config.php` NOT visible in website repo

---

*Guide generated: 2026-06-18*
