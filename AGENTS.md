# AGENTS.md — NewsFlow

## Project mission
สร้าง Laravel web application สำหรับบริหาร workflow การติดตามข่าว สรุปข่าว สร้างสื่อ ตรวจสอบโดยมนุษย์ และเผยแพร่ไปยัง social channel อย่างตรวจสอบย้อนหลังได้

## Non-negotiable rules
1. ทำเฉพาะ Phase ที่ผู้ใช้สั่ง ห้ามเริ่ม Phase ถัดไปเอง
2. ก่อนแก้ code ให้อ่าน repository และของเดิมก่อน ห้ามทำลาย behavior ที่ผ่าน test แล้ว
3. ทุก Phase ต้องมี automated tests ที่เหมาะสม
4. หลังแก้ code ให้ run formatter/linter/test ที่โปรเจกต์มี
5. ถ้า test fail ต้องแก้หรือรายงาน FAIL ห้ามซ่อน
6. ห้าม hard-code secret/token/API key/page id
7. External integration ทุกชนิดต้องผ่าน interface/adapter เพื่อ mock/test ได้
8. ทุก workflow step ต้อง idempotent เท่าที่เป็นไปได้ และ retry ได้อย่างปลอดภัย
9. เก็บ audit trail ของการเปลี่ยนสถานะสำคัญ
10. ใช้ transaction/lock/unique constraint ป้องกัน duplicate ที่ DB layer ด้วย
11. ห้าม scrape โดย bypass CAPTCHA, paywall, authentication, anti-bot หรือ access control
12. เก็บเฉพาะข้อมูลจากต้นทางเท่าที่จำเป็นต่อ workflow; อย่าทำ clone บทความ
13. ภาพข่าวที่ระบบสร้างต้องเป็นภาพใหม่ ไม่ดาวน์โหลด/นำรูปต้นฉบับมา republish โดยอัตโนมัติ
14. Publish ต้อง default เป็น human approval จนกว่าจะมีการเปิด auto-publish อย่างชัดเจน
15. UI ใช้ภาษาไทยเป็นหลัก แต่ชื่อ class/table/code ใช้ English ตาม convention

## Preferred stack
- Laravel 12
- MySQL 8+
- Livewire + Blade + Tailwind
- Redis for queue/cache when introduced
- Laravel Scheduler
- Laravel filesystem for generated assets
- Tests: Pest หรือ PHPUnit ตามโครงเดิม

## Engineering conventions
- Fat service classes หลีกเลี่ยง; แยก responsibility
- Use DTO/value objects เมื่อช่วยให้ boundary ชัด
- Use enums สำหรับ status ที่จำกัดค่า
- Use events/jobs/services แบบอ่านง่าย
- Controllers/Livewire components ไม่ควรมี business logic หนัก
- External response ต้อง validate/normalize ก่อนเข้า domain
- Log ต้องไม่บันทึก secret หรือ full access token

## Required phase completion
ทุก Phase ต้องอัปเดต `PHASE_REPORT.md` และจบคำตอบด้วย:
- `ACCEPTANCE GATE: PASS` หรือ `ACCEPTANCE GATE: FAIL`
- รายการ command ที่ผู้ใช้ต้องรันเอง ถ้ามี
- หยุด และรอคำสั่ง Phase ถัดไป
