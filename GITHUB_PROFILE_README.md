# Hi, I'm Alvin Chan Wun Long 👋

**Software Engineer** based in Kuala Lumpur, Malaysia.

I build practical web systems — from internal operations tools to public-facing platforms. I focus on clean architecture, real security (not just checkbox security), and systems that actually get used by people.

---

## 🛠 Tech Stack

**Languages & Runtime**

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)

**Frameworks & Libraries**

![Next.js](https://img.shields.io/badge/Next.js-000000?style=flat-square&logo=nextdotjs&logoColor=white)
![React](https://img.shields.io/badge/React-61DAFB?style=flat-square&logo=react&logoColor=black)

**Database & Tools**

![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Git](https://img.shields.io/badge/Git-F05032?style=flat-square&logo=git&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-181717?style=flat-square&logo=github&logoColor=white)

---

## 🚀 Featured Projects

### 1. Sandezi OA & Asset Management System

> Full-stack internal OA system for a live-streaming e-commerce company — HR management, shift scheduling, leave approval, and device asset lifecycle tracking.

**What I built:**
- 3-role RBAC (Admin / Manager / Employee) with 16 permission boundaries, dual-layer enforcement
- Complete device asset lifecycle: inventory → borrow workflow → maintenance → scrap, with DB transactions ensuring atomic state sync
- Date-overlap conflict detection for device borrow requests
- 5-type CSV report export with UTF-8 BOM (Excel-ready)
- Full security layer: 29 CSRF check points · 246 XSS prevention sites · 90 PDO Prepared Statements · zero GET-based mutations

**Tech:** PHP 7.4 · MySQL · PDO · HTML/CSS · Vanilla JS · Apache  
**Scale:** 29 PHP files · ~4,200 lines · 11 DB tables · 22 Git commits

---

### 2. Sandezi Official Website CMS

> Custom-built CMS and public website for a live-streaming e-commerce brand — product showcase, news management, FAQ, and a lightweight admin panel.

**What I built:**
- File-based content management with a JSON data store — no third-party CMS dependency
- Admin panel for managing products, announcements, news articles, and FAQ
- SEO-optimised pages: dynamic sitemap.xml generation, robots.txt, Open Graph meta tags
- Stable slug URLs for news articles to preserve link integrity
- CSRF-protected admin actions; image upload with server-side validation

**Tech:** PHP · HTML/CSS · JavaScript · JSON

---

### 3. SmartCalc Malaysia

> A free, no-signup web calculator suite built specifically for Malaysians — three tools in one page.

**What I built:**
- **BMI Calculator** — metric/imperial input, WHO category classification
- **Salary Calculator** — estimates EPF (employee & employer), SOCSO, EIS, and PCB/MTD deductions based on gross salary
- **Loan Repayment Planner** — monthly instalment, total interest, and amortisation breakdown
- Fully client-side (no data sent to server), mobile-responsive, single PHP file delivery

**Tech:** PHP · Vanilla JavaScript · HTML/CSS  
**Live:** [39.105.92.5/smartcalc.php](http://39.105.92.5/smartcalc.php)

---

## 📚 Currently Learning

- **Next.js / React** — building towards full-stack TypeScript development
- **REST API design** — structuring backend services for frontend/mobile consumption
- **System design fundamentals** — scalability patterns for moving beyond single-server deployments

---

## 📊 GitHub Stats

![Alvin's GitHub Stats](https://github-readme-stats.vercel.app/api?username=YOUR_GITHUB_USERNAME&show_icons=true&theme=default&hide_border=true&count_private=true)

![Top Languages](https://github-readme-stats.vercel.app/api/top-langs/?username=YOUR_GITHUB_USERNAME&layout=compact&hide_border=true&theme=default)

> Replace `YOUR_GITHUB_USERNAME` with your actual GitHub username to activate the stats cards.

---

## 📬 Contact

- **Email:** alvinchanwunlong@gmail.com
- **Location:** Kuala Lumpur, Malaysia
- **LinkedIn:** *(add your LinkedIn URL)*

---

*Currently open to Software Engineer roles in KL/remote. Interested in backend, full-stack, or internal tooling.*
