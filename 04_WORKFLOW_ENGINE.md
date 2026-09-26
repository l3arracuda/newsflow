# PHASE 04 — Workflow Engine, Queue, Retry & Logs

## Objective
เปลี่ยน pipeline ให้เป็น workflow ที่มองเห็นสถานะและ retry ราย step ได้ โดยยังใช้ fake processors แทน AI/Image/Publisher

## Required workflow
อย่างน้อย:
1. discover
2. fetch_detail
3. extract_facts (fake placeholder allowed)
4. summarize (fake)
5. rewrite (fake)
6. fact_check (fake)
7. image_prompt (fake)
8. image_generate (fake)
9. awaiting_review

## Design requirements
- ใช้ queued jobs/services
- WorkflowRun + WorkflowStepRun ต้อง update atomic
- step statuses: pending/running/succeeded/failed/skipped อย่างเหมาะสม
- timestamps
- attempts
- error class/message แบบ sanitize
- retry failed step ได้
- idempotent: retry ไม่สร้าง generated record ซ้ำโดยไม่จำเป็น
- lock ป้องกัน article เดียวกันรัน processing ซ้อนกัน
- event/audit log สำหรับ state transitions สำคัญ

## Queue
เพิ่ม Redis queue ถ้า environment รองรับ
ถ้า test environment ใช้ sync/fake queue ให้ชัดเจน
เตรียม config ให้สลับได้

## Commands/actions
อย่างน้อยมีวิธี:
- start workflow สำหรับ article
- retry failed workflow/step
- inspect status

## Failure simulation
สร้าง test doubles ที่ทำให้ step ที่กำหนด fail ได้ เพื่อพิสูจน์ retry/resume

## Tests
- happy path ถึง awaiting_review
- failure กลาง pipeline
- retry แล้วเดินต่อ ไม่รันทุก step ซ้ำแบบไม่จำเป็น
- duplicate concurrent start ถูก block
- step logs ครบ
- exception ไม่ expose secret

## Do NOT
- ยังไม่ต่อ real AI/Image/Facebook

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
