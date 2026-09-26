# PHASE 08 — Human Review & Approval Gate

## Objective
ทำ review workflow ให้ Admin ตรวจ text/facts/image ก่อน publish และทุกการแก้ไข trace ได้

## Article review screen
แสดง side-by-side/section ชัด:
- source title + source URL
- normalized source content
- extracted facts
- summary
- Facebook draft
- fact-check result
- generated image
- workflow timeline

## Actions
- Edit draft
- Regenerate summary
- Regenerate rewrite
- Regenerate image
- Approve
- Reject
- Request changes
- Re-run fact check

## Rules
- Approve ได้เมื่อ:
  - required text exists
  - fact check pass หรือ admin explicit override พร้อม reason
  - image exists หรือ adminเลือก no-image ตาม policy
- override ต้อง audit user/time/reason
- approved content ต้อง snapshot/version lock
- หลัง approve ถ้ามีการ edit → approval invalidated และต้อง approve ใหม่
- default ห้าม auto-publishจากการ approve ถ้า publish action แยกไว้

## Audit
เก็บ:
- reviewer
- decision
- note
- version ids
- timestamp
- overrides

## Authorization
เฉพาะ authorized admin/reviewer

## Tests
- cannot approve incomplete draft
- approve happy path
- edit after approval invalidates approval
- override requires reason
- unauthorized action blocked
- regenerate keeps previous version for audit

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
