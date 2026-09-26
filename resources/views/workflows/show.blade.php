<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-sm text-slate-500"><a href="{{ route('articles.show', $workflow->article) }}" class="text-blue-700 hover:underline">กลับไปหน้าข่าว</a> / Workflow</p><h1 class="mt-1 text-xl font-semibold text-slate-900">Workflow Run #{{ $workflow->id }}</h1></div>
            @include('partials.status-badge', ['status' => $workflow->status])
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if ($errors->has('retry'))<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('retry') }}</div>@endif
        <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
            <div><p class="text-xs text-slate-500">บทความ</p><a href="{{ route('articles.show', $workflow->article) }}" class="mt-1 block font-semibold text-slate-900 hover:text-blue-700">{{ $workflow->article->title }}</a><p class="mt-1 text-sm text-slate-500">{{ $workflow->article->source->name }} · บทความ #{{ $workflow->article_id }}</p></div>
            <dl class="grid grid-cols-2 gap-2 text-sm"><dt class="text-slate-500">ประเภท</dt><dd>{{ $workflow->run_type }}</dd><dt class="text-slate-500">Attempt</dt><dd>{{ $workflow->attempt }}</dd><dt class="text-slate-500">เริ่ม</dt><dd>{{ $workflow->started_at?->format('d/m/Y H:i:s') ?? '—' }}</dd><dt class="text-slate-500">จบ</dt><dd>{{ $workflow->finished_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></dl>
            @if ($workflow->safe_error_summary)<p class="rounded-lg bg-red-50 p-3 text-sm text-red-800 sm:col-span-2">{{ $workflow->safe_error_summary }}</p>@endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">ลำดับการทำงาน</h2><p class="mt-1 text-xs text-slate-500">แสดงทุก attempt เพื่อดูประวัติการ retry</p></div>
            <ol class="divide-y divide-slate-100">
                @forelse ($workflow->steps as $step)
                    <li class="grid gap-3 px-5 py-4 md:grid-cols-[1fr_auto]">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><h3 class="font-medium text-slate-900">{{ $step->name }}</h3><code class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $step->step_key }}</code>@include('partials.status-badge', ['status' => $step->status])</div>
                            <p class="mt-2 text-xs text-slate-500">ครั้งที่ {{ $step->attempt }} · เริ่ม {{ $step->started_at?->format('d/m/Y H:i:s') ?? '—' }} · จบ {{ $step->finished_at?->format('d/m/Y H:i:s') ?? '—' }} · ใช้เวลา {{ $step->started_at && $step->finished_at ? $step->started_at->diffInSeconds($step->finished_at).' วินาที' : '—' }}</p>
                            @if ($step->safe_error_summary)<p class="mt-2 break-words rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $step->safe_error_summary }}</p>@endif
                        </div>
                        @if ($workflow->status->value === 'failed' && $step->status->value === 'failed' && $failedSteps->contains('id', $step->id))
                            <form method="POST" action="{{ route('workflows.retry', $workflow) }}" class="self-center" onsubmit="return confirm('ยืนยันลองขั้นตอนนี้อีกครั้งหรือไม่?')">
                                @csrf<input type="hidden" name="step_key" value="{{ $step->step_key }}">
                                <button class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">ลองขั้นตอนนี้อีกครั้ง</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">ยังไม่มีขั้นตอน</li>
                @endforelse
            </ol>
            @if ($workflow->status->value === 'failed' && $failedSteps->isNotEmpty())
                <div class="border-t border-slate-100 px-5 py-4"><form method="POST" action="{{ route('workflows.retry', $workflow) }}" onsubmit="return confirm('ยืนยันลอง workflow ต่อจากขั้นตอนที่ล้มเหลวหรือไม่?')">@csrf<button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">ลองต่อจากขั้นที่ผิดพลาด</button></form></div>
            @endif
        </section>
    </div>
</x-app-layout>
