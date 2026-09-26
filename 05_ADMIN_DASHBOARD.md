# PHASE 05 — Admin Dashboard & Operational UI

## Objective
สร้าง UI ที่ทำให้ผู้ดูแลเห็นว่า flow ทำงานถึงไหน ติดตรงไหน และตรวจ article/log ได้โดยไม่เปิด DB

## Required screens

### Dashboard
แสดงอย่างน้อย:
- New/processing/awaiting review/published/failed counts
- workflows ล่าสุด
- failed workflows ล่าสุด
- source last scan
- publications today

### Articles list
filter:
- source
- status
- date
- keyword
pagination

columns:
- discovered/source published time
- title
- source
- status
- latest workflow status
- review/publication status

### Article detail
แสดง:
- source link
- source metadata
- snapshot/normalized content แบบอ่านได้
- workflow timeline
- generated draft placeholders
- asset placeholders
- logs/errors
- audit history

### Workflow detail
timeline ของทุก step:
- status
- start/end/duration
- attempt
- error
- retry action เฉพาะที่อนุญาต

## UI constraints
- ภาษาไทยเป็นหลัก
- responsive desktop/tablet
- status badge ชัด
- error detail ไม่โชว์ secret
- destructive action ต้อง confirm
- ใช้ Livewire/Blade ตาม stack ไม่เพิ่ม SPA framework โดยไม่จำเป็น

## Tests
- auth required
- filters
- article detail relations
- failed step visible
- retry action authorization
- pagination/query does not N+1 แบบหนัก

## Manual acceptance
Codex ต้องให้ checklist URL/หน้าที่ผู้ใช้เปิดดูและ expected result

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
