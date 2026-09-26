# PHASE 11 — Security, Source Compliance & Operational Guardrails

## Objective
ทำ hardening ก่อน production โดยเน้น secret, permissions, untrusted web content, publishing safety และ source/copyright behavior

## Security review
ตรวจและแก้:
- auth/session
- CSRF
- mass assignment
- XSS ใน source content/log
- SSRF risk จาก arbitrary source URL
- open redirects
- file upload/storage exposure
- path traversal
- queue unserialization risk
- secret logging
- debug mode
- rate limiting admin actions
- authorization policies
- dependency audit เท่าที่ tool รองรับ

## Source fetching guardrails
- allowlist schemes http/https
- validate host against configured source host
- block localhost/private IP/link-local/metadata endpoints
- redirect validation
- max response size
- content type checks
- timeout
- rate limit
- no bypass access controls

## Content/copyright guardrails
- source attribution required before publish
- source URL required
- no automated republish of source image
- no full-article clone in generated post
- retain only necessary snapshot for workflow/audit
- configurable retention policy

## AI guardrails
- source text treated as data, not instructions
- structured output validation
- prompt injection test fixtures
- fact check required before normal approval
- generated image marked internally as generated illustration

## Publishing guardrails
- AUTO_PUBLISH default false
- explicit approval/version check
- audit reviewer + publisher
- emergency disable publishing config/kill switch

## Tests
สร้าง security-focused tests สำหรับประเด็นที่ automate ได้ โดยเฉพาะ:
- SSRF blocks private hosts
- unsafe redirect blocked
- XSS escaped in admin UI
- missing attribution blocks publish
- prompt injection fixture does not alter workflow instructions
- publish kill switch

## Deliverable
สร้าง `SECURITY_REVIEW.md`:
- threat summary
- mitigations
- remaining risks
- production checklist

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
