@extends('layouts.master')

@section('title', 'Add Activity')

@section('header_title')
    <h2 class="text-xl font-semibold text-gray-800">Add New Activity</h2>
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
                <span class="px-2.5 py-1 text-xs font-semibold text-indigo-800 bg-indigo-50 rounded-md">activities Table</span>
                <h3 class="text-xl font-bold text-gray-800 mt-2">Create Activity</h3>
                <p class="text-xs text-gray-500 mt-1">Log a call, meeting, email, or task against a record.</p>
            </div>

            <form action="{{ route('admin.activities.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Subject -->
                <div>
                    <label for="subject" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Subject</label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}" placeholder="e.g. Discovery call with the prospect" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Type -->
                <div>
                    <label for="type" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Activity Type</label>
                    <select id="type" name="type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="" {{ old('type') === null ? 'selected' : '' }}>Select Type...</option>
                        <option value="call" {{ old('type') === 'call' ? 'selected' : '' }}>Call</option>
                        <option value="meeting" {{ old('type') === 'meeting' ? 'selected' : '' }}>Meeting</option>
                        <option value="email" {{ old('type') === 'email' ? 'selected' : '' }}>Email</option>
                        <option value="task" {{ old('type') === 'task' ? 'selected' : '' }}>Task</option>
                    </select>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Description (Optional)</label>
                    <textarea id="description" name="description" rows="4" placeholder="Notes, agenda, or outcome..." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('description') }}</textarea>
                </div>

                <!-- Due Date -->
                <div>
                    <label for="due_date" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Due Date (Optional)</label>
                    <input type="datetime-local" id="due_date" name="due_date" value="{{ old('due_date') }}" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Trackable Type -->
                <div>
                    <label for="trackable_type" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Attach To</label>
                    <select id="trackable_type" name="trackable_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="" {{ old('trackable_type') === null ? 'selected' : '' }}>Select Record Type...</option>
                        @foreach ($trackableTypes ?? [] as $trackableType)
                            <option value="{{ $trackableType['value'] }}" {{ old('trackable_type') === $trackableType['value'] ? 'selected' : '' }}>{{ $trackableType['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">The kind of record this activity belongs to.</p>
                </div>

                <!-- Trackable Id -->
                <div>
                    <label for="trackable_id" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Record ID</label>
                    <input type="number" id="trackable_id" name="trackable_id" value="{{ old('trackable_id') }}" min="1" placeholder="e.g. 42" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">The ID of the record selected above.</p>
                </div>

                <!-- User Id -->
                <div>
                    <label for="user_id" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Assigned User (Optional)</label>
                    <input type="number" id="user_id" name="user_id" value="{{ old('user_id') }}" min="1" placeholder="e.g. 7" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to leave the activity unassigned.</p>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex items-center justify-end space-x-3">
                    <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">Save Activity</button>
                </div>
            </form>

        </div>
    </div>
@endsection