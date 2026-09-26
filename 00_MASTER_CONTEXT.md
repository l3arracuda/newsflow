# MASTER CONTEXT — NewsFlow

อ่านไฟล์นี้และ `AGENTS.md` ก่อนทำงานทุก Phase

## Product Goal
สร้าง web app ที่ผู้ดูแลเห็นกระบวนการทั้งหมดของข่าวแต่ละชิ้นได้ตั้งแต่ discovery จน publish พร้อม log/audit ที่ตรวจสอบย้อนหลังได้

## Initial workflow

```text
Scheduled Scan
  ↓
Fetch Source Listing
  ↓
Normalize Candidate
  ↓
Duplicate Check
  ↓
Fetch Article Detail
  ↓
Extract Source Facts
  ↓
AI Summary
  ↓
AI Rewrite
  ↓
AI Fact-Consistency Check
  ↓
Generate Image Prompt
  ↓
Generate New Illustration
  ↓
Human Review
  ↓
Approve
  ↓
Publish
  ↓
Store External Post ID + URL + Audit Log
```

## Version 1 scope
Source เริ่มต้น: ThaiRath Society
Public source URL:
https://www.thairath.co.th/news/society

อย่าออกแบบ domain ให้ผูกกับ ThaiRath เท่านั้น ต้องรองรับ source adapters เพิ่มในอนาคต

## Roles
V1 มี Admin role อย่างน้อย 1 role
อนาคตอาจมี Editor/Approver

## Core domain concepts
- Source
- Article
- ArticleSnapshot / fetched content
- WorkflowRun
- WorkflowStepRun
- PromptTemplate
- GeneratedPost
- GeneratedAsset
- ReviewDecision
- PublishJob / Publication
- AuditLog

## Article statuses — concept
ใช้ enum จริงตาม implementation ที่เหมาะสม ตัวอย่าง:
- discovered
- fetched
- processing
- ready_for_review
- approved
- rejected
- publishing
- published
- failed

## Workflow guarantees
- URL เดิมไม่ควรสร้าง article ซ้ำ
- canonical URL/hash ใช้ช่วย dedupe
- job retry แล้วต้องไม่ publish ซ้ำ
- failed step ต้อง retry เฉพาะจุดได้
- ทุก step ต้องมี start/end/status/error metadata
- external call ทุกครั้งต้องมี timeout
- publisher ต้องมี idempotency strategy

## Content policy/product behavior
- สรุปและเรียบเรียงใหม่ ไม่คัดลอกบทความเต็ม
- เก็บ source URL, publisher name, source published_at
- Caption ต้องมี source attribution และ link ต้นทาง
- อย่านำ image ต้นทางมา repost โดยอัตโนมัติ
- generated image ต้องมี metadata ว่าเป็น generated/illustrative asset
- ข่าวอ่อนไหว/การเมือง/อุบัติเหตุ/อาชญากรรมควร default human review
- ห้ามสร้างข้อเท็จจริงใหม่ที่ไม่มีใน source snapshot

## Runtime configuration
กำหนดผ่าน `.env` หรือ config:
- DB
- Redis
- AI provider/key/model
- Image provider/key/model
- Facebook/Meta credentials
- scheduler time zone = Asia/Bangkok
- publish mode = review/manual by default

## Out of scope for early phases
- TikTok
- YouTube Shorts
- Instagram
- Multi-tenant SaaS
- Mobile app
- Autonomous auto-publish without approval
- Large-scale scraping

## Definition of Done
ระบบจะเรียกว่า V1 พร้อมใช้งานเมื่อ Phase 1–12 ผ่านทั้งหมดและ E2E flow สามารถ:
1. detect ข่าวใหม่
2. ไม่สร้าง duplicate
3. สร้าง draft
4. สร้าง/ผูกภาพใหม่
5. ให้ admin review
6. publish ผ่าน mock และมี path สำหรับ live Facebook
7. เก็บ publication + audit trail
8. retry failure ได้
