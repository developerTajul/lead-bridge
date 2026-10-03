@extends('layouts.master')

@section('title', 'Add Exam Session')

@section('header_title')
    <h2 class="text-xl font-semibold text-gray-800">Add New Exam Session</h2>
@endsection

@section('header_actions')
    <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-medium rounded-lg flex items-center">
        <i class="fa-solid fa-arrow-left mr-1.5"></i> Back to Dashboard
    </a>
@endsection

@section('content')
    <div class="p-6 flex justify-center items-start" style="min-height: calc(100vh - 180px);">
        <div class="max-w-lg w-full bg-white rounded-xl shadow-sm border border-gray-200 p-8">

            @if (session('success'))
                <div class="mb-6 flex items-start gap-2 px-4 py-3 text-xs text-green-800 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fa-solid fa-circle-check mt-0.5"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 flex items-start gap-2 px-4 py-3 text-xs text-red-800 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 px-4 py-3 text-xs text-red-800 bg-red-50 border border-red-200 rounded-lg">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6 border-b border-gray-100 pb-4">
                <span class="px-2.5 py-1 text-xs font-semibold text-indigo-800 bg-indigo-50 rounded-md">exam_sessions Table</span>
                <h3 class="text-xl font-bold text-gray-800 mt-2">Create Exam Session</h3>
                <p class="text-xs text-gray-500 mt-1">Add a new exam session. The name must be unique per year.</p>
            </div>

            <form action="{{ route('admin.exam-sessions.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="255" placeholder="e.g. Half-Yearly, Final Term, Mock Exam" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Must be unique together with the year.</p>
                </div>

                <!-- Year -->
                <div>
                    <label for="year" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Year</label>
                    <input type="number" id="year" name="year" value="{{ old('year') }}" min="2000" max="2100" placeholder="e.g. 2024" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Optional. Leaving it blank allows the name to repeat across years.</p>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex items-center justify-end space-x-3">
                    <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">Save Exam Session</button>
                </div>
            </form>

        </div>
    </div>
@endsection
