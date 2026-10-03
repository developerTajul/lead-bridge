@extends('layouts.master')

@section('title', 'Add User')

@section('header_title')
    <h2 class="text-xl font-semibold text-gray-800">Add New User</h2>
@endsection

@section('header_actions')
    <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-medium rounded-lg flex items-center">
        <i class="fa-solid fa-arrow-left mr-1.5"></i> Back to List
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
                <span class="px-2.5 py-1 text-xs font-semibold text-indigo-800 bg-indigo-50 rounded-md">users Table</span>
                <h3 class="text-xl font-bold text-gray-800 mt-2">Create User</h3>
                <p class="text-xs text-gray-500 mt-1">Add a new user account with role and status.</p>
            </div>

            <form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. John Doe" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="e.g. john@example.com" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" id="password" name="password" placeholder="Minimum 8 characters" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Password Confirmation -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Image -->
                <div>
                    <label for="image" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Profile Image</label>
                    <input type="file" id="image" name="image" accept="image/jpg,image/jpeg,image/png,image/webp" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Role</label>
                    <select id="role" name="role" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="MEMBER" {{ old('role') === 'MEMBER' ? 'selected' : '' }}>Member</option>
                        <option value="LIBRARIAN" {{ old('role') === 'LIBRARIAN' ? 'selected' : '' }}>Librarian</option>
                        <option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="PENDING" {{ old('status') === 'PENDING' ? 'selected' : '' }}>Pending</option>
                        <option value="APPROVED" {{ old('status') === 'APPROVED' ? 'selected' : '' }}>Approved</option>
                        <option value="SUSPENDED" {{ old('status') === 'SUSPENDED' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex items-center justify-end space-x-3">
                    <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">Save User</button>
                </div>
            </form>

        </div>
    </div>
@endsection
