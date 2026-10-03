@extends('layouts.master')

@section('title', 'Tags')

@section('header_title')
    <h2 class="text-xl font-semibold text-gray-800">Tag Management</h2>
@endsection

@section('header_actions')
    <a href="{{ route('admin.tags.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors flex items-center">
        <i class="fa-solid fa-plus mr-1.5"></i> Add Tag
    </a>
@endsection

@section('content')
    <div class="p-6">
        <div class="max-w-6xl bg-white rounded-xl shadow-sm border border-gray-200">

            @if (session('success'))
                <div class="m-6 mb-0 flex items-start gap-2 px-4 py-3 text-xs text-green-800 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fa-solid fa-circle-check mt-0.5"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error') || !empty($error))
                <div class="m-6 mb-0 flex items-start gap-2 px-4 py-3 text-xs text-red-800 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                    <span>{{ session('error') ?? $error }}</span>
                </div>
            @endif

            <div class="px-6 pt-6 pb-4 border-b border-gray-100">
                <div>
                    <span class="px-2.5 py-1 text-xs font-semibold text-indigo-800 bg-indigo-50 rounded-md">tags Table</span>
                    <h3 class="text-xl font-bold text-gray-800 mt-2">Tag List</h3>
                    <p class="text-xs text-gray-500 mt-1">View all tags.</p>
                </div>
                <span class="inline-block mt-2 px-3 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-full">
                    {{ $pagination->total }} total
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Slug</th>
                            <th class="px-6 py-3">Created</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tags as $tag)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-xs text-gray-500 font-mono">{{ $tag->id }}</td>
                                <td class="px-6 py-3 font-medium text-gray-800">{{ $tag->name }}</td>
                                <td class="px-6 py-3 text-xs text-gray-500">{{ $tag->slug }}</td>
                                <td class="px-6 py-3 text-xs text-gray-500">{{ $tag->createdAt }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="#" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-medium rounded-lg transition-colors">Edit</a>
                                        <form action="#" method="POST" onsubmit="return confirm('Are you sure to delete this data?');" style="display:inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-medium rounded-lg transition-colors">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <i class="fa-solid fa-tags text-3xl text-gray-300 mb-3"></i>
                                    <p class="text-sm text-gray-500">No tags found.</p>
                                    <a href="{{ route('admin.tags.create') }}" class="inline-block mt-3 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors">
                                        Add your first tag
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pagination->total > 0)
                <div class="px-6 py-4 flex items-center justify-between border-t border-gray-100">
                    <p class="text-xs text-gray-500">
                        Showing page {{ $pagination->currentPage }} of {{ $pagination->lastPage }}
                    </p>
                    <div class="flex items-center space-x-2">
                        @if ($pagination->currentPage > 1)
                            <a href="{{ route('admin.tags.index', ['page' => $pagination->currentPage - 1, 'per_page' => $pagination->perPage]) }}" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition-colors">
                                <i class="fa-solid fa-arrow-left mr-1"></i> Prev
                            </a>
                        @endif
                        @if ($pagination->currentPage < $pagination->lastPage)
                            <a href="{{ route('admin.tags.index', ['page' => $pagination->currentPage + 1, 'per_page' => $pagination->perPage]) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors">
                                Next <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
