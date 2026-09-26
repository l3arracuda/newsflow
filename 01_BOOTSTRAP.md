# PHASE 01 — Project Bootstrap & Health Baseline

## Objective
สร้างฐาน Laravel ที่ run ได้จริง มี authentication, dashboard shell, environment validation และ test baseline โดยยังไม่สร้าง business workflow ข่าว

## Instructions for Codex

1. อ่าน `AGENTS.md` และ `00_MASTER_CONTEXT.md`
2. ตรวจ repo ก่อน:
   - ถ้ายังไม่มี Laravel project ให้ bootstrap Laravel 12
   - ถ้ามี project แล้ว ห้าม reinstall; ปรับเฉพาะที่จำเป็น
3. ตั้งค่า:
   - MySQL connection ผ่าน env
   - timezone `Asia/Bangkok`
   - app locale `th` และ fallback ที่เหมาะสม
4. เพิ่ม authentication แบบเรียบง่ายสำหรับ Admin
   - เลือก official/simple Laravel-compatible approach
   - ห้ามเพิ่ม social login
5. สร้าง authenticated dashboard shell ที่ route `/dashboard`
6. เพิ่ม `/health` endpoint:
   - app status
   - DB connectivity
   - ห้าม expose secrets
7. สร้าง `.env.example` ที่มีเฉพาะ placeholder
8. เพิ่ม feature tests:
   - guest เข้า dashboard ไม่ได้
   - authenticated admin เข้า dashboard ได้
   - health endpoint ตอบสำเร็จเมื่อ DB พร้อม
9. สร้าง README dev setup เฉพาะที่จำเป็นถ้ายังไม่มี

## Do NOT
- ห้ามสร้าง news crawler
- ห้ามสร้าง AI integration
- ห้าม Facebook integration
- ห้าม Redis/Horizon ถ้ายังไม่จำเป็น
- ห้ามเริ่ม schema business จำนวนมาก

## Acceptance Gate
PASS เมื่อ:
- app boot ได้
- migrations run ได้
- login/logout ทำงาน
- `/dashboard` protected
- `/health` ทำงาน
- tests ทั้งหมดผ่าน
- ไม่มี secret ใน git diff

## วิธีจบงาน Phase นี้

หลัง implementation:
1. Run formatter/linter ที่มี
2. Run targeted tests ของ Phase นี้
3. Run full test suite ที่มีอยู่
4. อัปเดต `PHASE_REPORT.md`
5. สรุป manual test แบบ step-by-step
6. ถ้ามี failure ห้ามเริ่ม Phase ถัดไป

จบคำตอบด้วย `ACCEPTANCE GATE: PASS` หรือ `ACCEPTANCE GATE: FAIL`
แล้วหยุดรอคำสั่งต่อไป
