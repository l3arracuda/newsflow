# PHASE 07 — Image Prompt & Generated Asset Pipeline

## Objective
สร้างภาพประกอบใหม่จากข่าวผ่าน provider abstraction โดยไม่ reuse รูปต้นฉบับอัตโนมัติ และ trace ได้ว่าภาพมาจาก prompt/version ไหน

## Architecture
contracts เช่น:
- `ImagePromptBuilder`
- `ImageGenerationProvider`
- `GeneratedAssetStorage`

## Image prompt generation
input:
- extracted facts
- article category
- content sensitivity
output:
- prompt
- negative/avoid rules ถ้ารองรับ
- aspect ratio
- style metadata
- safety notes

## Visual policy
- default เป็น editorial illustration / photorealistic illustrative scene ตามความเหมาะสม
- ห้ามอ้างว่า generated scene คือภาพเหตุการณ์จริง
- ห้ามสร้าง text/logo ของ publisher ต้นทางโดยอัตโนมัติ
- ห้ามดึง source image มาใช้เป็น reference อัตโนมัติ
- ข่าวอาชญากรรม/อุบัติเหตุ/ผู้เสียหาย: หลีกเลี่ยง graphic detail และการสร้างหน้าบุคคลจริงแบบไม่มีเหตุจำเป็น
- metadata ต้องระบุว่า generated illustration

## Asset record
เก็บ:
- provider
- provider asset id nullable
- storage disk/path
- mime
- width/height
- checksum
- prompt version / prompt text or reference
- generation metadata
- status

## Provider
อย่างน้อย:
- Fake provider for tests
- Real provider adapter configurable by env
ถ้า API ให้ URL ชั่วคราว ต้อง download/store ภายใน controlled storage ตาม policy

## Regenerate
รองรับ regenerate image โดยสร้าง asset version ใหม่
ของเดิมไม่ถูกลบทิ้งทันทีเพื่อ audit

## Tests
- fake generation
- failed provider
- asset persisted
- checksum
- regenerate produces new version
- source image URL ไม่ถูกใช้เป็น generated asset input โดย default

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
