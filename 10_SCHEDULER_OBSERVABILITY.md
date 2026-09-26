# PHASE 10 — Scheduler, Run Control, Observability

## Objective
ให้ระบบตรวจข่าวอัตโนมัติวันละ 3 รอบเวลาไทย และผู้ดูแลรู้ทันทีว่ารอบไหนสำเร็จ/ล้มเหลว

## Schedule
Timezone: Asia/Bangkok
default scan times:
- 08:00
- 14:00
- 20:00

เวลา override ได้ผ่าน config โดยไม่แก้ code

## Requirements
- Laravel Scheduler
- prevent overlapping scan
- distributed lock ถ้าใช้หลาย worker/server
- source active flag respected
- each scheduled scan creates WorkflowRun/scan run
- command สำหรับ manual scan
- queue health visibility
- failed jobs tracking
- retry/backoff policy
- stale-running detection

## Dashboard metrics
อย่างน้อย:
- last successful scan per source
- last failed scan
- articles discovered today
- drafts generated today
- awaiting review
- publications today
- failed steps 24h
- queue pending/failed ถ้าดึงได้

## Alerts
สร้าง alert abstraction / logger event
ยังไม่จำเป็นต้องผูก LINE/email จริง
อย่างน้อย dashboard prominent warning เมื่อ:
- source ไม่สำเร็จเกิน threshold
- queue failed
- workflow stuck

## Tests
- schedule definitions
- overlap prevention
- inactive source skipped
- stale run detection
- metric queries
- timezone behavior

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
