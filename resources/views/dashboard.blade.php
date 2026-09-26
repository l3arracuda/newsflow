<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">NewsFlow — แดชบอร์ด</h1>
                <p class="mt-1 text-sm text-slate-500">ภาพรวมการติดตามและประมวลผลข่าว</p>
            </div>
            <a href="{{ route('articles.index') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">ดูข่าวทั้งหมด</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section aria-label="สรุปสถานะข่าว" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([['ข่าวใหม่', $counts['new'], 'text-sky-700'], ['กำลังทำงาน', $counts['processing'], 'text-blue-700'], ['รอตรวจทาน', $counts['review'], 'text-amber-700'], ['เผยแพร่แล้ว', $counts['published'], 'text-emerald-700'], ['ผิดพลาด', $counts['failed'], 'text-red-700'], ['เผยแพร่วันนี้', $publicationsToday, 'text-violet-700']] as [$label, $count, $color])
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $color }}">{{ number_format($count) }}</p>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Workflow ล่าสุด</h2>
                    <span class="text-xs text-slate-500">{{ $recentRuns->count() }} รายการ</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3">ข่าว</th><th class="px-5 py-3">สถานะ</th><th class="px-5 py-3">เวลาเริ่ม</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($recentRuns as $run)
                                <tr>
                                    <td class="max-w-md px-5 py-3">
                                        <a class="font-medium text-slate-800 hover:text-blue-700" href="{{ route('workflows.show', $run) }}">{{ $run->article?->title ?? 'ไม่พบบทความ' }}</a>
                                        <p class="mt-1 text-xs text-slate-500">{{ $run->article?->source?->name ?? '—' }} · Run #{{ $run->id }}</p>
                                    </td>
                                    <td class="px-5 py-3">@include('partials.status-badge', ['status' => $run->status])</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $run->started_at?->format('d/m/Y H:i') ?? 'รอเริ่ม' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-8 text-center text-slate-500">ยังไม่มี workflow</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">แหล่งข่าว</h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($sources as $source)
                        <li class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-slate-800">{{ $source->name }}</span>
                                <span class="text-xs text-slate-500">{{ $source->articles_count }} ข่าว</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">สแกนสำเร็จล่าสุด: {{ $source->last_scanned_at?->format('d/m/Y H:i') ?? 'ยังไม่มีข้อมูล' }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-slate-500">ยังไม่มีแหล่งข่าว</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <section class="rounded-xl border border-red-200 bg-white shadow-sm">
            <div class="border-b border-red-100 px-5 py-4"><h2 class="font-semibold text-red-800">Workflow ที่ผิดพลาด</h2></div>
            @forelse ($failedRuns as $run)
                <a href="{{ route('workflows.show', $run) }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-red-50">
                    <span class="truncate text-sm font-medium text-slate-800">{{ $run->article?->title ?? 'ไม่พบบทความ' }}</span>
                    <span class="text-xs text-slate-500">Run #{{ $run->id }} · {{ $run->finished_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </a>
            @empty
                <p class="px-5 py-5 text-sm text-slate-500">ไม่มี workflow ที่ผิดพลาด</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
