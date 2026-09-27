<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-sm text-slate-500"><a href="{{ route('articles.index') }}" class="text-blue-700 hover:underline">ข่าวทั้งหมด</a> / รายละเอียดข่าว</p><h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $article->title }}</h1></div>
            @include('partials.status-badge', ['status' => $article->status])
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
            <div><h2 class="font-semibold text-slate-900">ข้อมูลข่าวและต้นทาง</h2><dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-slate-500">แหล่งข่าว</dt><dd class="text-slate-800">{{ $article->source->name }}</dd>
                <dt class="text-slate-500">รหัสต้นทาง</dt><dd class="break-all text-slate-800">{{ $article->source_external_id ?? '—' }}</dd>
                <dt class="text-slate-500">เผยแพร่ต้นทาง</dt><dd class="text-slate-800">{{ $article->source_published_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt class="text-slate-500">ค้นพบเมื่อ</dt><dd class="text-slate-800">{{ $article->discovered_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt class="text-slate-500">Adapter</dt><dd class="text-slate-800">{{ $article->source->adapter }}</dd>
                <dt class="text-slate-500">สแกนแหล่งข่าวสำเร็จล่าสุด</dt><dd class="text-slate-800">{{ $article->source->last_scanned_at?->format('d/m/Y H:i') ?? 'ยังไม่มีข้อมูล' }}</dd>
            </dl></div>
            <div class="flex flex-col items-start gap-3 md:border-l md:border-slate-100 md:pl-5">
                <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium text-blue-700 hover:underline">เปิดข่าวต้นทาง ↗</a>
                <p class="text-xs text-slate-500">Canonical URL: <span class="break-all">{{ $article->canonical_url ?? '—' }}</span></p>
                <p class="text-xs text-slate-500">Content checksum: <span class="break-all font-mono">{{ $article->content_hash ?? '—' }}</span></p>
                <p class="text-xs text-slate-500">ข้อมูลแหล่งข่าวที่ปลอดภัย: {{ $article->source->name }} · {{ $article->source->key }} · {{ $article->source->base_url }}</p>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Snapshot และเนื้อหาที่จัดรูปแบบแล้ว</h2><p class="mt-1 text-xs text-slate-500">เก็บเฉพาะข้อความที่จำเป็นต่อ workflow</p></div>
            <div class="space-y-4 p-5">
                @forelse ($article->snapshots as $snapshot)
                    <details class="rounded-lg border border-slate-200 p-4" @if ($loop->first) open @endif>
                        <summary class="cursor-pointer text-sm font-medium text-slate-800">ดึงเมื่อ {{ $snapshot->fetched_at?->format('d/m/Y H:i:s') }} · checksum {{ Str::limit($snapshot->checksum, 16) }}</summary>
                        <div class="mt-4 max-h-96 overflow-y-auto whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ $snapshot->normalized_excerpt }}</div>
                    </details>
                @empty
                    <p class="text-sm text-slate-500">ยังไม่มี snapshot</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Workflow timeline</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse ($article->workflowRuns as $run)
                    <div class="p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3"><a href="{{ route('workflows.show', $run) }}" class="font-semibold text-blue-700 hover:underline">Run #{{ $run->id }}</a>@include('partials.status-badge', ['status' => $run->status])</div>
                        <p class="mt-1 text-xs text-slate-500">เริ่ม {{ $run->started_at?->format('d/m/Y H:i:s') ?? '—' }} · จบ {{ $run->finished_at?->format('d/m/Y H:i:s') ?? '—' }} · attempt {{ $run->attempt }}</p>
                        @if ($run->safe_error_summary)<p class="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $run->safe_error_summary }}</p>@endif
                        <ol class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($run->steps->sortBy('id') as $step)
                                <li class="rounded-lg border border-slate-200 p-3">
                                    <div class="flex items-center justify-between gap-2"><span class="text-sm font-medium text-slate-800">{{ $step->name }}</span>@include('partials.status-badge', ['status' => $step->status])</div>
                                    <p class="mt-1 text-xs text-slate-500">ครั้งที่ {{ $step->attempt }} · {{ $step->started_at?->format('H:i:s') ?? '—' }} – {{ $step->finished_at?->format('H:i:s') ?? '—' }}</p>
                                    @if ($step->safe_error_summary)<p class="mt-2 break-words text-xs text-red-700">{{ $step->safe_error_summary }}</p>@endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @empty
                    <p class="p-5 text-sm text-slate-500">ยังไม่มี workflow</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Draft และสื่อที่สร้าง</h2></div>
            <div class="space-y-4 p-5">
                @forelse ($article->generatedPosts as $post)
                    <article class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-medium text-slate-800">Draft #{{ $post->id }} · รุ่น {{ $post->version }}</h3>@include('partials.status-badge', ['status' => $post->status])</div>
                        @if (($post->metadata['placeholder'] ?? false) === true)<p class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">เนื้อหานี้เป็น placeholder สำหรับทดสอบ ไม่ใช่โพสต์พร้อมเผยแพร่</p>@endif
                        <div class="mt-3 whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $post->draft_text }}</div>
                        <p class="mt-2 text-xs text-slate-500">ที่มา: {{ $post->source_attribution }} · <a href="{{ $post->source_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 hover:underline">ลิงก์ต้นทาง</a></p>
                        @if ($post->reviewDecisions->isNotEmpty())<p class="mt-2 text-xs text-slate-600">ผลตรวจทานล่าสุด: {{ $post->reviewDecisions->sortByDesc('decided_at')->first()->decision }} · {{ $post->reviewDecisions->sortByDesc('decided_at')->first()->user?->name ?? 'ระบบ' }}</p>@endif
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            @forelse ($post->assets as $asset)
                                <div class="rounded-lg border border-dashed border-slate-300 p-3 text-xs text-slate-600">
                                    <p>ภาพ #{{ $asset->id }} · รุ่น {{ $asset->version }} · {{ $asset->provider }} · {{ $asset->width }}×{{ $asset->height }} · {{ $asset->mime_type ?? 'ชนิดไฟล์ไม่ระบุ' }}</p>
                                    <p class="mt-1">Prompt v{{ $asset->prompt_version }} · SHA-256 {{ Str::limit($asset->content_hash, 20) }} · {{ $asset->status }}</p>
                                    @if ($asset->metadata['generated_illustration'] ?? false)<p class="mt-1 font-medium text-emerald-700">ภาพประกอบที่สร้างใหม่ ไม่ใช่ภาพเหตุการณ์จริง</p>@endif
                                    @if ($asset->metadata['fake'] ?? false)<p class="mt-1 font-medium text-amber-700">ไฟล์ทดสอบจาก fake provider (ภาพตัวอย่างขนาดเล็ก ไม่ใช่ภาพใช้งานจริง)</p>@endif
                                    @if ($asset->prompt_text)<details class="mt-2"><summary class="cursor-pointer font-medium">ดู prompt ที่ใช้</summary><p class="mt-1 whitespace-pre-wrap">{{ $asset->prompt_text }}</p></details>@endif
                                    <p class="mt-1 break-all">ไฟล์: {{ $asset->disk }} / {{ $asset->path }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">ยังไม่มีสื่อแนบ</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">ยังไม่มี draft</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">ประวัติการตรวจสอบ (Audit)</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($auditLogs as $log)
                    @php($safeState = collect($log->after_state ?? [])->only(['article_status', 'run_status', 'step_key', 'error_class', 'attempt']))
                    <li class="flex flex-wrap items-start justify-between gap-2 px-5 py-3">
                        <div><p class="text-sm font-medium text-slate-800">{{ $log->event }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->actor?->name ?? 'ระบบ' }}@if ($safeState->isNotEmpty()) · {{ $safeState->map(fn ($value, $key) => $key.': '.$value)->implode(' · ') }}@endif</p></div>
                        <time class="text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</time>
                    </li>
                @empty
                    <li class="px-5 py-5 text-sm text-slate-500">ยังไม่มีประวัติ</li>
                @endforelse
            </ul>
            @if ($auditLogs->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $auditLogs->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
