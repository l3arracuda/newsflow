# PHASE 02 — Database & Domain Model

## Objective
สร้าง schema/domain foundation ที่รองรับ workflow ทั้งระบบ แต่ยังไม่ fetch ข่าวจริง

## Required entities
อย่างน้อย:
- sources
- articles
- article_snapshots
- workflow_runs
- workflow_step_runs
- prompt_templates
- generated_posts
- generated_assets
- review_decisions
- publications
- audit_logs

ปรับชื่อได้ถ้ามีเหตุผล แต่ต้องอธิบายใน report

## Requirements

### Source
เก็บ:
- name
- key/slug unique
- base_url
- listing_url
- adapter
- active
- config JSON ที่ไม่เก็บ secret

### Article
เก็บอย่างน้อย:
- source_id
- source_external_id nullable
- source_url
- canonical_url nullable
- title
- source_published_at nullable
- discovered_at
- content_hash nullable
- status
- metadata JSON
- timestamps

DB constraints:
- ป้องกัน source+source_url ซ้ำ
- index fields ที่ใช้ค้นหา/status/date

### Snapshot
เก็บ raw/normalized text ที่จำเป็นสำหรับ audit
อย่าออกแบบเพื่อ clone ทั้งเว็บ
เก็บ fetched_at และ checksum

### WorkflowRun / StepRun
รองรับ:
- run type
- status
- started/finished
- error summary
- attempt
- metadata
- relation ไป article เมื่อเหมาะสม

### GeneratedPost
รองรับ draft text, source attribution, version, status

### GeneratedAsset
รองรับ image/provider/path/hash/metadata

### ReviewDecision
approve/reject/request_changes + user + note + timestamp

### Publication
channel/provider/external_post_id/external_url/status/published_at/idempotency_key

### AuditLog
actor, event, entity_type/id, before/after metadata ที่เหมาะสม

## Status design
ใช้ PHP enums สำหรับ finite states และ casts ใน models

## Seed
สร้าง Source เริ่มต้น:
- key: thairath_society
- name: ThaiRath Society
- listing URL: https://www.thairath.co.th/news/society
- active: true
- adapter: `thairath`

## Tests
ต้องมี:
- migration works
- relations
- unique constraint duplicate URL
- enum/status casts
- seed source created once/idempotent

## Do NOT
- ยังไม่ HTTP fetch จริง
- ยังไม่ AI
- ยังไม่ UI ใหญ่
- ยังไม่ publish

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
