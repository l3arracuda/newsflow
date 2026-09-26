# PHASE 06 — AI Text Pipeline: Facts, Summary, Rewrite, Consistency Check

## Objective
แทน fake text steps ด้วย AI provider abstraction ที่สร้าง draft จาก source snapshot โดยลด hallucination และตรวจ consistency ก่อน review

## Architecture
สร้าง contracts เช่น:
- `AiTextProvider`
- `FactExtractor`
- `ArticleSummarizer`
- `SocialPostRewriter`
- `FactConsistencyChecker`

ห้าม business logic ผูกกับ vendor โดยตรง

## Prompt templates
ใช้ `prompt_templates` table
seed อย่างน้อย:
- FACT_EXTRACT
- NEWS_SUMMARY
- FACEBOOK_REWRITE
- FACT_CHECK

รองรับ:
- key
- version
- system/instruction text
- active
- parameters JSON
- updated_by
- timestamps

## Facts
Fact extraction ต้องให้ structured output ที่ validate ได้ เช่น:
- people/organizations
- places
- dates/times
- quantities/money
- event/action
- warnings/advice explicitly present in source
- source claims / attribution
schema ปรับได้ แต่ต้อง deterministic พอสำหรับ check

## Rewrite constraints
Draft:
- ภาษาไทย
- เขียนใหม่ ไม่ copy ย่อหน้ายาว
- ไม่เพิ่มข้อเท็จจริงที่ไม่มีใน facts
- มี source attribution + source URL
- title/hook ห้าม clickbait เกินข้อมูลจริง

## Fact check
เปรียบเทียบ generated draft กับ extracted facts
result structured:
- pass boolean
- severity
- mismatches[]
- unsupported_claims[]
- checked_at

ถ้าไม่ pass → ห้ามเข้าสถานะ ready_for_review อัตโนมัติ; ให้ flagged

## Provider
Implement provider อย่างน้อย:
- Fake provider สำหรับ tests
- Real provider adapter ที่ config ผ่าน env

ถ้าใช้ OpenAI:
- ใช้ official/current API/SDK pattern ที่ repository/runtime รองรับ
- ห้าม hard-code model; config ผ่าน env/config
- validate structured output
- timeout/retry
- token/usage/cost metadata ถ้าหาได้จาก response

## Safety
- source article text ถือเป็น untrusted input
- ป้องกัน prompt injection: เนื้อข่าวไม่มีสิทธิ์เปลี่ยน system instructions หรือสั่ง tool
- อย่าส่ง secret เข้า prompt

## Tests
- fake provider happy path
- malformed structured output
- unsupported claim ถูก fact check flag
- provider timeout
- retry does not duplicate versions incorrectly
- prompt template version recordedกับ output

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
