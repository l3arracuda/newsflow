<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-xl font-semibold text-slate-900">ข่าวทั้งหมด</h1><p class="mt-1 text-sm text-slate-500">ค้นหาและติดตามสถานะข่าวในระบบ</p></div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('articles.discover') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'กำลังดึงข่าว…';">
                    @csrf
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-wait disabled:opacity-70">ดึงข่าวล่าสุด</button>
                </form>
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-blue-700 hover:underline">กลับแดชบอร์ด</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('discovery_result'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                ตรวจข่าวล่าสุดแล้ว: พบ {{ session('discovery_result.candidates') }} ข่าวใหม่ {{ session('discovery_result.created') }} ข่าว และเป็นข่าวเดิม {{ session('discovery_result.existing') }} ข่าว
            </div>
        @endif
        @if (session('discovery_error'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('discovery_error') }}</div>
        @endif
        <form method="GET" action="{{ route('articles.index') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
            <label class="text-sm text-slate-600">คำค้น
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ค้นจากหัวข้อข่าว" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </label>
            <label class="text-sm text-slate-600">แหล่งข่าว
                <select name="source_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">ทุกแหล่งข่าว</option>
                    @foreach ($sources as $source)<option value="{{ $source->id }}" @selected(($filters['source_id'] ?? '') == $source->id)>{{ $source->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-slate-600">สถานะ
                <select name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">ทุกสถานะ</option>
                    @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str_replace('_', ' ', $status->value) }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-slate-600">ค้นพบตั้งแต่
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </label>
            <label class="text-sm text-slate-600">ถึงวันที่
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </label>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">กรอง</button>
                <a href="{{ route('articles.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">ล้าง</a>
            </div>
            @if ($errors->any())<p class="text-sm text-red-700 sm:col-span-2 lg:col-span-6">กรุณาตรวจสอบตัวกรองวันที่หรือข้อมูลที่เลือก</p>@endif
        </form>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">รายการข่าว</h2><span class="text-sm text-slate-500">{{ $articles->total() }} รายการ</span></div>
            <div class="overflow-x-auto">
                <table class="min-w-[1050px] divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">วันที่ค้นพบ</th><th class="px-4 py-3">ข่าว</th><th class="px-4 py-3">แหล่งข่าว</th><th class="px-4 py-3">สถานะข่าว</th><th class="px-4 py-3">Workflow</th><th class="px-4 py-3">ตรวจทาน</th><th class="px-4 py-3">เผยแพร่</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($articles as $article)
                            <tr class="align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500"><div>{{ $article->discovered_at?->format('d/m/Y H:i') ?? '—' }}</div><div class="mt-1">ต้นทาง: {{ $article->source_published_at?->format('d/m/Y H:i') ?? '—' }}</div></td>
                                <td class="max-w-sm px-4 py-4"><a href="{{ route('articles.show', $article) }}" class="font-medium leading-5 text-slate-900 hover:text-blue-700">{{ $article->title }}</a></td>
                                <td class="px-4 py-4 text-slate-600">{{ $article->source?->name ?? '—' }}</td>
                                <td class="px-4 py-4">@include('partials.status-badge', ['status' => $article->status])</td>
                                <td class="px-4 py-4">@if ($article->latestWorkflowRun)<a href="{{ route('workflows.show', $article->latestWorkflowRun) }}">@include('partials.status-badge', ['status' => $article->latestWorkflowRun->status])</a>@else<span class="text-slate-400">ยังไม่เริ่ม</span>@endif</td>
                                <td class="px-4 py-4">@include('partials.status-badge', ['status' => $article->latestGeneratedPost?->status])</td>
                                <td class="px-4 py-4">@include('partials.status-badge', ['status' => $article->latestGeneratedPost?->latestPublication?->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">ไม่พบข่าวตามเงื่อนไข</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($articles->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $articles->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
