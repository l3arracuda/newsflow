<?php

namespace App\Workflows;

class WorkflowStepCatalog
{
    public const STEPS = [
        ['key' => 'discover', 'name' => 'ยืนยันข่าวที่ค้นพบ'],
        ['key' => 'fetch_detail', 'name' => 'ดึงรายละเอียดข่าว'],
        ['key' => 'extract_facts', 'name' => 'แยกข้อเท็จจริง'],
        ['key' => 'summarize', 'name' => 'สรุปข่าว'],
        ['key' => 'rewrite', 'name' => 'เรียบเรียงโพสต์'],
        ['key' => 'fact_check', 'name' => 'ตรวจสอบข้อเท็จจริง'],
        ['key' => 'image_prompt', 'name' => 'เตรียมคำสั่งสร้างภาพ'],
        ['key' => 'image_generate', 'name' => 'สร้างภาพประกอบ'],
        ['key' => 'awaiting_review', 'name' => 'รอตรวจทานโดยผู้ดูแล'],
    ];
}
