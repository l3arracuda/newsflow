# PHASE 09 — Facebook Publisher Abstraction

## Objective
สร้าง publishing layer ที่ test ด้วย mock ได้ 100% และเตรียม live Meta/Facebook publishing โดยไม่ทำให้ test ไปโพสต์จริง

## Architecture
contract เช่น:
- `SocialPublisher`
- implementation:
  - `FakeFacebookPublisher`
  - `MetaFacebookPublisher`

## Publication requirements
input:
- approved post version
- approved generated asset
- page/channel config

output:
- external_post_id
- external_url nullable
- published_at
- provider response metadata ที่ sanitize แล้ว

## Safety/idempotency
- สร้าง idempotency key จาก publication intent/version
- ถ้า retry หลัง provider success แต่ app timeout ต้องมี strategy ลด duplicate post
- lock publication record
- publish เฉพาะ content version ที่ยัง approved
- token/secret ห้ามเข้า DB plain text ถ้าไม่จำเป็น; ใช้ env/secure config
- ห้าม log access token

## Modes
- `PUBLISH_DRIVER=fake` default สำหรับ dev/test
- `PUBLISH_DRIVER=meta` สำหรับ live เมื่อ credentials พร้อม
- `AUTO_PUBLISH=false` default

## UI
เพิ่ม:
- Publish button สำหรับ approved draft
- publication status
- external post link เมื่อมี
- retry failed publication อย่างปลอดภัย

## Meta implementation
ก่อนเขียน live adapter ให้ตรวจ official Meta docs/current Graph API behavior ที่ environment เข้าถึงได้
อย่าเดา endpoint/version/permission
หาก credentials ไม่มี ให้ implement adapter + validation + documented setup โดยไม่ claim ว่า live verified

## Tests
- fake publisher success
- provider failure
- retry
- double click publish ไม่สร้าง publication ซ้ำ
- unapproved content publish ไม่ได้
- edited-after-approval publish ไม่ได้
- secrets redacted in log

## Manual live verification
สร้าง checklist แยกและทำเป็น OPTIONAL
ห้ามใช้ live credentials ใน automated tests

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
