# NewsFlow — Codex Development Prompt Pack

ชุด Prompt สำหรับพัฒนา Web App จัดการ Workflow ข่าวแบบตรวจสอบทีละ Phase

## Tech Stack หลัก
- Laravel 12
- PHP 8.2+ (ใช้เวอร์ชันที่โปรเจกต์/Composer รองรับจริง)
- MySQL 8+
- Redis สำหรับ Queue/Cache (Phase หลัง)
- Laravel Queue / Scheduler
- Livewire + Blade + Tailwind CSS
- PHPUnit/Pest ตามที่โปรเจกต์มีอยู่
- API integrations ต้องซ่อนหลัง interface/adapter
- ห้าม hard-code API key, token, secret หรือ page id

## เป้าหมายระบบ
ตรวจข่าวจากแหล่งที่กำหนด → เก็บ DB → ตรวจซ้ำ → สกัดข้อมูล → สรุป/Rewrite → สร้างภาพ → ตรวจ Fact → รออนุมัติ → Publish → Log ทุกขั้น

## วิธีใช้งานกับ Codex

1. สร้าง repo/project เปล่าหรือ repo ที่ต้องการใช้
2. วาง `AGENTS.md` ไว้ที่ root ของ repo
3. วาง `00_MASTER_CONTEXT.md` ไว้ใน repo หรือแนบให้ Codex อ่านก่อนเริ่ม
4. ส่ง Prompt ทีละ Phase ตามลำดับ
5. **ห้ามส่งหลาย Phase พร้อมกัน**
6. หลัง Codex ทำเสร็จ ให้ตรวจ `PHASE_REPORT.md` และผล test
7. ถ้า Acceptance Gate ผ่าน ให้ commit และ push branch สำหรับตรวจรับก่อน ห้าม merge หรือสร้าง tag จนกว่าจะได้รับการตรวจรับจากผู้ใช้
8. ถ้าไม่ผ่าน ให้ Codexแก้ Phase เดิมจนผ่านก่อน

## ลำดับ Phase

| Phase | ไฟล์ | เป้าหมาย |
|---|---|---|
| 0 | `00_MASTER_CONTEXT.md` | กติกาและ architecture กลาง |
| 1 | `01_BOOTSTRAP.md` | Bootstrap Laravel + health check + auth |
| 2 | `02_DATABASE_DOMAIN.md` | Schema/Models/State ของระบบ |
| 3 | `03_SOURCE_FETCHING.md` | Source adapter + ThaiRath listing/detail fetch |
| 4 | `04_WORKFLOW_ENGINE.md` | Workflow Run + Step + Queue + Retry |
| 5 | `05_ADMIN_DASHBOARD.md` | Dashboard / Article / Logs |
| 6 | `06_AI_TEXT_PIPELINE.md` | Extract facts + Summary + Rewrite + Fact check |
| 7 | `07_IMAGE_PIPELINE.md` | Image prompt + generation adapter + storage |
| 8 | `08_REVIEW_APPROVAL.md` | Human review / approve / reject / regenerate |
| 9 | `09_FACEBOOK_PUBLISHER.md` | Facebook publishing abstraction + mock/live-ready |
| 10 | `10_SCHEDULER_OBSERVABILITY.md` | 3 รอบ/วัน + lock + alerts + metrics |
| 11 | `11_SECURITY_COMPLIANCE.md` | Security, copyright/source policy, abuse controls |
| 12 | `12_E2E_PRODUCTION.md` | E2E test + deployment readiness |

## กติกาการผ่าน Phase

Codex ต้องสร้าง/อัปเดตไฟล์ `PHASE_REPORT.md` ทุก Phase โดยมี:
- Summary
- Files changed
- Migrations added
- Commands run
- Tests run + exact result
- Manual verification steps
- Known limitations
- Acceptance Gate: PASS / FAIL

ถ้า test ยัง FAIL ต้องระบุ FAIL และห้ามอ้างว่า Phase เสร็จ

## Version Control ที่แนะนำ

ทำแต่ละ Phase บน branch แยก ตรวจ test และอัปเดต `PHASE_REPORT.md` ก่อน commit และ push branch ขึ้น GitHub เพื่อเก็บ checkpoint จากนั้นหยุดรอผู้ใช้ตรวจรับ ห้าม merge หรือสร้าง tag จนกว่าจะได้รับคำสั่ง

```bash
git add .
git commit -m "phase-01: bootstrap application baseline"
git push -u origin dev/phase-01-bootstrap
```

ทำแบบเดียวกันทุก Phase โดยเปลี่ยนชื่อ branch และ commit message ให้ตรงกับ Phase นั้น

## Phase 01 — Local development

Requirements: PHP 8.2+, Composer, Node.js/npm, and MySQL 8+.

```bash
composer install
copy .env.example .env
php artisan key:generate
npm ci
npm run build
```

Set the MySQL database name, username, and password in `.env`, then run:

```bash
php artisan newsflow:check-environment
php artisan migrate
php artisan newsflow:create-admin
php artisan test
php artisan serve
```

Open `/login`, sign in with the administrator account, and use `/dashboard`. `/health` returns app and database status without configuration values. Public account registration is disabled.

## หมายเหตุสำคัญ

ระบบนี้ตั้งใจใช้ข้อมูลข่าวเพื่อ "สรุป/เรียบเรียงใหม่และอ้างอิงต้นทาง" ไม่ใช่ clone หรือ republish บทความ/รูปต้นฉบับทั้งชิ้น
ตัว fetcher ต้องเคารพ robots.txt, rate limits, terms ที่เกี่ยวข้อง และห้าม bypass paywall, anti-bot หรือ access control
