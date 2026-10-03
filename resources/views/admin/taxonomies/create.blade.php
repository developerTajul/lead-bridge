@extends('layouts.master')

@section('title', 'Add Taxonomy')

@section('header_title')
    <h2 class="text-xl font-semibold text-gray-800">Add New Taxonomy</h2>
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
                <span class="px-2.5 py-1 text-xs font-semibold text-indigo-800 bg-indigo-50 rounded-md">taxonomies Table</span>
                <h3 class="text-xl font-bold text-gray-800 mt-2">Create Taxonomy</h3>
                <p class="text-xs text-gray-500 mt-1">Add a new taxonomy (category, class, group, subject, or chapter).</p>
            </div>

            <form action="{{ route('admin.taxonomies.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Physics, Class 10, Academic" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Type -->
                <div>
                    <label for="type" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Taxonomy Type</label>
                    <select id="type" name="type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="" {{ old('type') === null ? 'selected' : '' }}>Select Type...</option>
                        <option value="category" {{ old('type') === 'category' ? 'selected' : '' }}>Category (e.g. Academic, Admission)</option>
                        <option value="class" {{ old('type') === 'class' ? 'selected' : '' }}>Class (e.g. Class 10, HSC)</option>
                        <option value="group" {{ old('type') === 'group' ? 'selected' : '' }}>Group (e.g. Science, Arts)</option>
                        <option value="subject" {{ old('type') === 'subject' ? 'selected' : '' }}>Subject (e.g. Physics, Bangla)</option>
                        <option value="chapter" {{ old('type') === 'chapter' ? 'selected' : '' }}>Chapter (e.g. Chapter 1)</option>
                    </select>
                </div>

                <!-- Root Taxonomy -->
                <div>
                    <label for="root_taxonomy" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Root Taxonomy (Optional)</label>
                    <select id="root_taxonomy" class="taxonomy-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">None (Top Level)</option>
                        @forelse ($taxonomies ?? [] as $taxonomy)
                            @if ($taxonomy->parentId === null)
                                <option value="{{ $taxonomy->id }}">{{ $taxonomy->name }}</option>
                            @endif
                        @empty
                            <option value="" disabled>No taxonomies available yet</option>
                        @endforelse
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Pick the top-level taxonomy this item belongs under.</p>
                </div>

                <!-- Sub-Taxonomy (depends on Root Taxonomy) -->
                <div>
                    <label for="sub_taxonomy" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Sub-Taxonomy (Optional)</label>
                    <select id="sub_taxonomy" name="parent_id" class="taxonomy-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">Select a root taxonomy first</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Choose the exact parent from the selected root's hierarchy.</p>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex items-center justify-end space-x-3">
                    <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">Save Taxonomy</button>
                </div>
            </form>

        </div>
    </div>

    @push('styles')
        <style>
            /* Make Select2 match the Tailwind input styling of this form */
            .taxonomy-select + .select2-container .select2-selection--single {
                height: 42px;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                font-size: 14px;
            }
            .taxonomy-select + .select2-container .select2-selection--single .select2-selection__rendered {
                line-height: 40px;
                padding-left: 12px;
                color: #374151;
            }
            .taxonomy-select + .select2-container .select2-selection--single .select2-selection__arrow {
                height: 40px;
            }
            .taxonomy-select + .select2-container .select2-selection--single .select2-selection__placeholder {
                color: #9ca3af;
            }

            /* Readable, visually-indented dropdown rows for the taxonomy tree */
            .select2-container--default .select2-results__option {
                padding: 8px 10px;
                font-size: 14px;
            }
            .taxonomy-option {
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .taxonomy-guide {
                color: #9ca3af;
                font-size: 12px;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            $(function () {
                const taxonomies = @json($taxonomies ?? []);

                const $rootSelect = $('#root_taxonomy');
                const $subSelect   = $('#sub_taxonomy');

                // id -> taxonomy lookup
                const byId = {};
                taxonomies.forEach(function (t) { byId[t.id] = t; });

                // Find the top-level ancestor of a taxonomy id
                function getRootAncestorId(id) {
                    var current = byId[id];
                    if (!current) return null;
                    while (current.parentId !== null && byId[current.parentId]) {
                        current = byId[current.parentId];
                    }
                    return current.id;
                }

                // Relative depth of a sub-taxonomy label under the chosen root (1 = direct child)
                function relativeDepth(label) {
                    return (label.match(/ > /g) || []).length + 1;
                }

                // Dropdown row: indent by depth so a deep hierarchy stays scannable
                function renderTaxonomyOption(state) {
                    if (!state.id || !state.element || !state.element.dataset) {
                        return state.text;
                    }
                    var depth = parseInt(state.element.dataset.depth || '0', 10);

                    var span = document.createElement('span');
                    span.className = 'taxonomy-option';
                    span.style.paddingLeft = ((depth - 1) * 16) + 'px';

                    if (depth > 1) {
                        var guide = document.createElement('span');
                        guide.className = 'taxonomy-guide';
                        guide.textContent = '└ ';
                        span.appendChild(guide);
                    }

                    var label = document.createElement('span');
                    label.textContent = state.text;
                    span.appendChild(label);
                    return span;
                }

                // Closed box: plain text of the chosen parent (no indentation)
                function renderTaxonomySelection(state) {
                    if (!state.id) {
                        return state.text;
                    }
                    var span = document.createElement('span');
                    span.textContent = state.text;
                    return span;
                }

                // Rebuild the sub-taxonomy options for the selected root, keeping Select2 alive
                function rebuildSub(rootId) {
                    var root = (rootId && byId[rootId]) ? byId[rootId] : null;
                    var rootPrefix = root ? (root.path + ' > ') : null;

                    var placeholderText = root
                        ? 'Choose a parent under "' + root.name + '" (optional)'
                        : 'Select a root taxonomy first';

                    // Placeholder option first; its text mirrors the placeholder so Select2 hides it
                    var options = [new Option(placeholderText, '')];

                    if (root) {
                        // Option to place the item directly under the selected root
                        options.push(new Option('↳ Directly under ' + root.name, String(root.id)));

                        // All descendants, with the root's name stripped and rows indented by depth
                        taxonomies
                            .filter(function (t) { return t.path.startsWith(rootPrefix); })
                            .sort(function (a, b) { return a.path.localeCompare(b.path); })
                            .forEach(function (t) {
                                var relativeLabel = t.path.slice(rootPrefix.length);
                                var opt = new Option(relativeLabel, String(t.id));
                                opt.dataset.depth = String(relativeDepth(relativeLabel));
                                options.push(opt);
                            });
                    }

                    $subSelect.empty().append(options).trigger('change');

                    // Default the parent to the selected root itself
                    $subSelect.val(root ? String(root.id) : '').trigger('change');

                    // Reflect the contextual placeholder text in the closed box
                    $subSelect
                        .siblings('.select2-container')
                        .find('.select2-selection__placeholder')
                        .text(placeholderText);
                }

                // --- Initialise root Select2 ---
                $rootSelect.select2({
                    placeholder: 'None (Top Level)',
                    allowClear: true,
                    width: '100%'
                });

                // --- Initialise sub Select2 once: searchable, clearable, indented rows ---
                $subSelect.select2({
                    placeholder: 'Select a root taxonomy first',
                    allowClear: true,
                    width: '100%',
                    templateResult: renderTaxonomyOption,
                    templateSelection: renderTaxonomySelection
                });

                // --- Wire cascading behaviour ---
                $rootSelect.on('change', function () {
                    rebuildSub(this.value ? parseInt(this.value, 10) : null);
                });

                // --- Restore selection after a validation error ---
                var oldParentId = @json(old('parent_id'));
                if (oldParentId) {
                    var rootId = getRootAncestorId(parseInt(oldParentId, 10));
                    if (rootId) {
                        $rootSelect.val(String(rootId)).trigger('change');
                        // rebuildSub defaulted to the root itself; override with the previously chosen parent
                        $subSelect.val(String(oldParentId)).trigger('change');
                    }
                }
            });
        </script>
    @endpush
@endsection
