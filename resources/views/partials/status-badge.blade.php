@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) ($status ?? '');
    $labels = [
        'discovered' => 'ข่าวใหม่', 'fetched' => 'ดึงข้อมูลแล้ว', 'processing' => 'กำลังทำงาน',
        'ready_for_review' => 'รอตรวจทาน', 'flagged' => 'ติดธงตรวจสอบ', 'changes_requested' => 'ขอให้แก้ไข', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ',
        'publishing' => 'กำลังเผยแพร่', 'published' => 'เผยแพร่แล้ว', 'failed' => 'ผิดพลาด',
        'pending' => 'รอดำเนินการ', 'running' => 'กำลังทำงาน', 'succeeded' => 'สำเร็จ',
        'cancelled' => 'ยกเลิก', 'skipped' => 'ข้าม', 'draft' => 'ฉบับร่าง',
    ];
    $styles = [
        'failed' => 'bg-red-100 text-red-800', 'flagged' => 'bg-red-100 text-red-800', 'changes_requested' => 'bg-orange-100 text-orange-800', 'rejected' => 'bg-red-100 text-red-800',
        'published' => 'bg-emerald-100 text-emerald-800', 'succeeded' => 'bg-emerald-100 text-emerald-800',
        'approved' => 'bg-emerald-100 text-emerald-800', 'ready_for_review' => 'bg-amber-100 text-amber-800',
        'processing' => 'bg-blue-100 text-blue-800', 'running' => 'bg-blue-100 text-blue-800',
        'publishing' => 'bg-blue-100 text-blue-800', 'discovered' => 'bg-sky-100 text-sky-800',
        'pending' => 'bg-gray-100 text-gray-700', 'skipped' => 'bg-gray-100 text-gray-700',
    ];
@endphp
<span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $styles[$value] ?? 'bg-slate-100 text-slate-700' }}">
    {{ $labels[$value] ?? ($value !== '' ? $value : '—') }}
</span>
