# PHASE 12 — End-to-End Verification & Production Readiness

## Objective
พิสูจน์ V1 ทั้ง flow ด้วย automated E2E/integration test และเตรียม deploy โดยไม่เพิ่ม feature ใหม่

## E2E scenarios

### Scenario A — Happy path
fixture listing มีข่าวใหม่ 1 รายการ
→ discover
→ fetch
→ extract facts
→ summary
→ rewrite
→ fact check PASS
→ image generated
→ awaiting review
→ admin approve
→ fake Facebook publish
→ publication stored
→ audit complete

### Scenario B — Duplicate
รัน source discovery ซ้ำ
→ article ไม่เพิ่ม
→ publication ไม่ซ้ำ

### Scenario C — AI failure
AI step fail
→ workflow failed
→ retry
→ resume
→ success

### Scenario D — Image failure
image provider fail
→ retryเฉพาะส่วนเหมาะสม
→ approval blocked จนพร้อม

### Scenario E — Fact mismatch
draft มี unsupported claim
→ fact check FAIL
→ normal approve blocked
→ override ต้องมี reason

### Scenario F — Publisher uncertainty
publisher success response simulation + local timeout/retry
→ idempotency prevents obvious duplicate intent

## Production readiness
ตรวจ:
- config cache compatibility
- queue worker command
- scheduler cron
- storage link/permissions
- DB indexes
- migrations rollback strategy
- backups
- logging
- retention
- health endpoint
- Redis
- supervisor/systemd example หรือ deployment platform equivalent
- HTTPS assumptions
- debug false

## Deliverables
สร้าง:
1. `DEPLOYMENT.md`
2. `OPERATIONS.md`
3. `BACKUP_RESTORE.md`
4. `E2E_REPORT.md`

## Final Acceptance Gate
PASS เมื่อ:
- full automated suite ผ่าน
- E2E scenarios ผ่านด้วย fake providers
- manual UI checklist พร้อม
- live integrations ที่ไม่มี credentials ต้องถูกระบุว่า "not live verified" อย่างชัดเจน
- ไม่มี unresolved critical security issue
- ไม่มี secret ใน repo
- deploy/rollback instructions ใช้งานได้

## Do NOT
- ห้ามเพิ่ม TikTok/YouTube/Instagram
- ห้าม refactor ใหญ่ที่ไม่จำเป็น
- ห้ามเปิด auto-publish default

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
