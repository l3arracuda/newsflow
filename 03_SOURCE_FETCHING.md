# PHASE 03 — Source Adapter & Article Discovery

## Objective
ทำ source fetching แบบ pluggable ให้ดึง candidate จาก ThaiRath Society ได้อย่างสุภาพและ testable โดยไม่ผูกระบบกับ HTML selector เดียวแบบเปราะเกินไป

## Architecture
สร้าง contract เช่น:
- `NewsSourceAdapter`
  - `discover(Source $source): iterable`
  - `fetchArticle(Source $source, ArticleCandidate $candidate): ArticleDocument`

สร้าง registry/factory สำหรับเลือก adapter จาก `sources.adapter`

## ThaiRath adapter requirements
1. ใช้ listing URL จาก DB/config
2. ดึง candidate fields ที่หาได้:
   - title
   - source URL
   - source published time/date ถ้ามี
   - external id ถ้าสามารถ derive อย่างเสถียร
3. normalize URL
4. upsert/discover article โดยไม่ duplicate
5. detail fetch:
   - ดึงเฉพาะ text/metadata ที่จำเป็นต่อ summarization
   - clean navigation/footer/ads ที่ชัดเจน
   - เก็บ snapshot + checksum
6. HTTP client:
   - timeout
   - user-agent ที่ระบุแอปอย่างเหมาะสม
   - retry แบบจำกัด
   - rate-limit/throttle
   - error classification
7. เคารพ access restrictions:
   - ห้าม bypass CAPTCHA/paywall/anti-bot
   - ถ้า fetch ไม่ได้ ให้ fail อย่างโปร่งใส

## Parser robustness
- แยก HTTP transport ออกจาก HTML parser
- tests ต้องใช้ saved fixtures ไม่ยิงเว็บจริงทุกครั้ง
- selector strategy ควรมี fallback ที่สมเหตุผล
- ถ้า parse ไม่ได้ให้ log structured error

## Commands
สร้าง command สำหรับ manual testing เช่น:
- `news:discover thairath_society --dry-run`
- `news:discover thairath_society`

ชื่อ command เปลี่ยนได้แต่ต้องใช้งานง่าย

## Tests
- fixture listing → candidates ถูกต้อง
- duplicate discovery ไม่สร้าง record ใหม่
- fixture detail → normalized text
- malformed HTML → controlled failure
- timeout/429/5xx → retry/error behavior
- dry-run ไม่ mutate DB ตามนิยามที่เลือก

## Manual verification
ให้ report ระบุ command ที่ผู้ใช้รันเพื่อทดสอบ live 1 ครั้ง
live test ต้องเป็น optional และไม่เป็น requirement ของ automated suite

## Do NOT
- ห้ามเริ่ม AI
- ห้ามสร้าง image
- ห้าม publish

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
