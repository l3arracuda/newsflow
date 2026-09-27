<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-slate-500"><a href="{{ route('articles.show', $article) }}" class="text-blue-700 hover:underline">กลับไปหน้าข่าว</a> / ตรวจทานก่อนโพสต์</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">ชุดตรวจข่าว #{{ $article->id }}</h1>
            </div>
            @if ($post)@include('partials.status-badge', ['status' => $post->status])@endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if ($errors->has('review'))<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('review') }}</div>@endif

        <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">หัวข้อจากต้นทาง</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $article->title }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $article->source->name }} · เผยแพร่ {{ $article->source_published_at?->format('d/m/Y H:i') ?? 'ไม่ระบุ' }}</p>
                <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block break-all text-sm text-blue-700 hover:underline">เปิดข่าวต้นทาง ↗</a>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <h2 class="font-semibold text-slate-800">เนื้อหาต้นทางที่ระบบใช้</h2>
                <div class="mt-2 max-h-56 overflow-y-auto whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{{ $article->snapshots->first()?->normalized_excerpt ?? 'ยังไม่มี snapshot' }}</div>
            </div>
        </section>

        @if (!$post)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">ยังไม่มี draft สำหรับตรวจทาน ต้องให้ workflow สร้าง draft ก่อน</section>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div><p class="font-semibold text-slate-900">Draft #{{ $post->id }} · Version {{ $post->version }}</p><p class="mt-1 text-xs text-slate-500">ที่มา: {{ $post->source_attribution }} · {{ $post->source_url }}</p></div>
                <p class="text-xs text-slate-500">Approve จะบันทึกฉบับตรวจรับเท่านั้น ไม่ได้โพสต์ออกไป</p>
            </div>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="space-y-5">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-slate-900">ข้อเท็จจริงที่ดึงได้</h2>
                        @if ($facts)
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach (['event_action' => 'เหตุการณ์', 'people_organizations' => 'บุคคล / องค์กร', 'places' => 'สถานที่', 'dates_times' => 'วัน / เวลา', 'quantities_money' => 'จำนวน / มูลค่า', 'warnings_advice' => 'คำเตือน / คำแนะนำ'] as $key => $label)
                                    @if (!empty($facts[$key]))
                                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-slate-800">{{ is_array($facts[$key]) ? implode(' · ', $facts[$key]) : $facts[$key] }}</dd></div>
                                    @endif
                                @endforeach
                            </dl>
                            @if (!empty($facts['quantitative_claims']))
                                <div class="mt-4 space-y-2">
                                    <h3 class="text-xs font-semibold text-slate-700">ตัวเลขพร้อมประเภทและข้อความอ้างอิง</h3>
                                    @foreach ($facts['quantitative_claims'] as $claim)
                                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                                            <p class="font-medium text-slate-800">{{ $claim['subject'] ?? 'รายการไม่ระบุ' }} · {{ $claim['relation'] ?? 'ประเภทไม่ระบุ' }}: {{ $claim['value'] ?? '—' }} {{ $claim['unit'] ?? '' }}
                                                <span class="ml-1 text-xs {{ ($claim['evidence_verified'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">{{ ($claim['evidence_verified'] ?? false) ? 'ยืนยันข้อความต้นทางแล้ว' : 'ยังยืนยันข้อความอ้างอิงไม่ได้' }}</span>
                                            </p>
                                            @if (!empty($claim['source_quote']))<p class="mt-1 text-xs text-slate-600">ต้นทาง: {{ $claim['source_quote'] }}</p>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (!empty($facts['evidence_claims']))
                                <details class="mt-4 rounded-lg border border-slate-200 p-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-slate-700">ดูข้อเท็จจริงและข้อความต้นทางที่ใช้เป็นหลักฐาน</summary>
                                    <ul class="mt-2 space-y-2 text-xs text-slate-600">
                                        @foreach ($facts['evidence_claims'] as $claim)
                                            <li><span class="font-medium">{{ $claim['statement'] ?? 'ข้อเท็จจริง' }}</span> — {{ ($claim['evidence_verified'] ?? false) ? 'ยืนยัน quote แล้ว' : 'quote ไม่ตรงกับต้นทาง' }}<br>“{{ $claim['source_quote'] ?? '' }}”</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-amber-700">ไม่มี facts ที่บันทึกกับ draft นี้</p>
                        @endif
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold text-slate-900">สรุปข่าว</h2>
                            <form method="POST" action="{{ route('review.summary.regenerate', $post) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">สร้างสรุปใหม่</button></form>
                        </div>
                        <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $summary ?: 'ยังไม่มีสรุป' }}</p>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold text-slate-900">ผลตรวจความสอดคล้อง</h2>
                            <form method="POST" action="{{ route('review.fact-check', $post) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">ตรวจใหม่</button></form>
                        </div>
                        @if ($factCheck)
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $factCheckCurrent && $factCheck['pass'] ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $factCheckCurrent ? ($factCheck['pass'] ? 'ผ่าน' : 'ไม่ผ่าน') : 'ผลตรวจไม่ตรงกับ draft ปัจจุบัน' }}</span>
                                <span class="text-xs text-slate-500">ระดับ {{ $factCheck['severity'] ?? '—' }} · {{ $factCheck['checked_at'] ?? 'เวลาไม่ระบุ' }}</span>
                            </div>
                            @foreach (['mismatches' => 'จุดที่ไม่ตรงกัน', 'unsupported_claims' => 'ข้อกล่าวอ้างที่ไม่มีหลักฐาน'] as $key => $label)
                                @if (!empty($factCheck[$key]))
                                    <div class="mt-3 rounded-lg bg-red-50 p-3"><p class="text-xs font-semibold text-red-800">{{ $label }}</p>
                                        <ul class="mt-2 space-y-2 text-sm text-red-800">
                                            @foreach ($factCheck[$key] as $item)
                                                <li class="rounded-md bg-white/70 p-2">
                                                    @if (is_array($item))
                                                        <p><span class="font-medium">ข้อความในร่าง:</span> {{ $item['draft_quote'] ?? '—' }}</p>
                                                        @if (!empty($item['source_quote']))<p class="mt-1"><span class="font-medium">หลักฐานต้นทาง:</span> {{ $item['source_quote'] }}</p>@endif
                                                        @if (!empty($item['explanation']))<p class="mt-1 text-xs">{{ $item['explanation'] }}</p>@endif
                                                    @else
                                                        {{ $item }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                            @foreach ($factCheck['review_warnings'] ?? [] as $warning)
                                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">{{ $warning }}</p>
                            @endforeach
                        @else
                            <p class="mt-3 text-sm text-amber-700">ยังไม่มีผลตรวจที่ผูกกับ draft ฉบับนี้</p>
                        @endif
                    </article>
                </div>

                <div class="space-y-5">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold text-slate-900">ร่างโพสต์ Facebook</h2>
                            <form method="POST" action="{{ route('review.rewrite.regenerate', $post) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">เรียบเรียงใหม่</button></form>
                        </div>
                        <form method="POST" action="{{ route('review.edit', $post) }}" class="mt-3 space-y-3">@csrf @method('PATCH')
                            <textarea name="draft_text" rows="12" maxlength="12000" required class="w-full rounded-lg border-slate-300 text-sm leading-6 focus:border-blue-500 focus:ring-blue-500">{{ old('draft_text', $post->draft_text) }}</textarea>
                            <p class="text-xs text-slate-500">การบันทึกสร้าง draft version ใหม่ และล้างผล fact-check เดิม ต้องตรวจและอนุมัติฉบับใหม่</p>
                            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">บันทึกเป็นฉบับแก้ไข</button>
                        </form>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold text-slate-900">ภาพประกอบ</h2>
                            <form method="POST" action="{{ route('review.image.import-manual', $article) }}">@csrf<input type="hidden" name="post" value="{{ $post->id }}"><button class="rounded-md border border-blue-300 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-50">นำเข้าภาพจากโฟลเดอร์</button></form>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-600">วางภาพที่คุณสร้างเองใน <code class="break-all">{{ $manualImageDirectory }}</code> โดยตั้งชื่อ <code>{{ $article->id }}.png</code> (หรือ .jpg, .jpeg, .webp) แล้วกดนำเข้า ระบบจะเก็บสำเนาเป็น version ใหม่</p>
                        @if ($post->assets->isNotEmpty())
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($post->assets as $asset)
                                    <figure class="overflow-hidden rounded-lg border border-slate-200">
                                        <img src="{{ route('generated-assets.preview', $asset) }}" alt="ภาพประกอบที่สร้างใหม่ ไม่ใช่ภาพเหตุการณ์จริง" class="aspect-square w-full bg-slate-100 object-contain">
                                        <figcaption class="p-3 text-xs text-slate-600">ภาพ #{{ $asset->id }} · v{{ $asset->version }} · {{ $asset->provider }} · {{ $asset->width }}×{{ $asset->height }}<br>{{ Str::limit($asset->content_hash, 18) }}<br><span class="font-medium {{ $asset->provider === 'manual' ? 'text-blue-700' : 'text-emerald-700' }}">{{ $asset->provider === 'manual' ? 'ภาพที่แนบจากโฟลเดอร์ข่าว' : 'ภาพประกอบสร้างใหม่ ไม่ใช่ภาพข่าวจริง' }}</span>@if ($asset->metadata['fake'] ?? false)<br><span class="text-amber-700">ภาพ fixture สำหรับทดสอบ</span>@endif</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-3 text-sm text-amber-700">ยังไม่มีภาพ ให้วางภาพตาม ID ข่าวในโฟลเดอร์ด้านบนแล้วกดนำเข้า หรือระบุเหตุผลไม่ใช้ภาพในแบบอนุมัติ</p>
                        @endif
                    </article>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">Workflow timeline</h2>
                @if ($workflow)
                    <ol class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach ($workflow->steps->sortBy('id') as $step)
                        <li class="rounded-lg border border-slate-200 p-3"><div class="flex items-center justify-between gap-2"><span class="text-sm text-slate-800">{{ $step->name }}</span>@include('partials.status-badge', ['status' => $step->status])</div><p class="mt-1 text-xs text-slate-500">{{ $step->finished_at?->format('d/m/Y H:i:s') ?? 'ยังไม่เสร็จ' }}</p></li>
                    @endforeach</ol>
                @else<p class="mt-2 text-sm text-slate-500">ยังไม่มี workflow</p>@endif
            </section>

            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="font-semibold text-amber-950">ผลการตรวจของผู้ดูแล</h2>
                <p class="mt-1 text-xs text-amber-900">อนุมัติเป็นเพียงการล็อกชุดเนื้อหาและบันทึกผลตรวจ ไม่มีการส่งไป Facebook</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <form method="POST" action="{{ route('review.approve', $post) }}" class="space-y-2 rounded-lg bg-white p-4">@csrf
                        <label class="block text-xs font-medium text-slate-700">เหตุผล override เมื่อ fact-check ไม่ผ่านหรือไม่ใช่ฉบับล่าสุด</label><textarea name="override_reason" rows="2" class="w-full rounded-md border-slate-300 text-sm" placeholder="จำเป็นเฉพาะกรณี override"></textarea>
                        <label class="flex items-start gap-2 text-xs text-slate-700"><input type="checkbox" name="no_image" value="1" class="mt-0.5 rounded border-slate-300">อนุมัติโดยไม่ใช้ภาพ</label>
                        <textarea name="no_image_reason" rows="2" class="w-full rounded-md border-slate-300 text-sm" placeholder="เหตุผลกรณีเลือกไม่ใช้ภาพ"></textarea>
                        <textarea name="note" rows="2" class="w-full rounded-md border-slate-300 text-sm" placeholder="บันทึกเพิ่มเติม (ถ้ามี)"></textarea>
                        <button class="w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">อนุมัติฉบับนี้</button>
                    </form>
                    <form method="POST" action="{{ route('review.request-changes', $post) }}" class="space-y-2 rounded-lg bg-white p-4">@csrf
                        <label class="block text-xs font-medium text-slate-700">สิ่งที่ต้องแก้ (จำเป็น)</label><textarea name="note" rows="4" required minlength="3" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                        <button class="w-full rounded-lg border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100">ขอให้แก้ไข</button>
                    </form>
                    <form method="POST" action="{{ route('review.reject', $post) }}" class="space-y-2 rounded-lg bg-white p-4">@csrf
                        <label class="block text-xs font-medium text-slate-700">เหตุผลไม่อนุมัติ (จำเป็น)</label><textarea name="note" rows="4" required minlength="3" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                        <button class="w-full rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-800 hover:bg-red-50">ไม่อนุมัติ</button>
                    </form>
                </div>
                @if ($post->reviewDecisions->isNotEmpty())
                    <div class="mt-4 border-t border-amber-200 pt-4"><h3 class="text-sm font-semibold text-slate-800">ประวัติการตรวจ</h3><ul class="mt-2 space-y-1">@foreach ($post->reviewDecisions->sortByDesc('decided_at') as $decision)
                        <li class="text-xs text-slate-600">{{ $decision->decided_at?->format('d/m/Y H:i:s') }} · {{ $decision->user?->name ?? 'ผู้ใช้ที่ถูกลบ' }} · {{ $decision->decision->value }} · {{ $decision->note }}</li>
                    @endforeach</ul></div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
