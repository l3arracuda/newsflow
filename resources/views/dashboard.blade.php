<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            NewsFlow — แดชบอร์ด
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-lg font-semibold">ยินดีต้อนรับ, {{ auth()->user()->name }}</h1>
                    <p class="mt-2 text-sm text-gray-600">ระบบพร้อมสำหรับการตั้งค่าและตรวจสอบ workflow ข่าวใน Phase ถัดไป</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
