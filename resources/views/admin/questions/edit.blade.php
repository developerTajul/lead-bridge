@extends('layouts.master')

@section('title', 'Edit Question')

@section('header_title')
    <h2 class="text-xl font-semibold text-gray-800">Edit Question</h2>
@endsection

@section('header_actions')
    <a href="{{ route('admin.questions.index') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-medium rounded-lg flex items-center">
        <i class="fa-solid fa-arrow-left mr-1.5"></i> Back to List
    </a>
@endsection

@section('content')
    @php
        $selectedTaxonomyIds = array_map('intval', (array) old('taxonomies', $question->taxonomyIds));
        $selectedTagIds = array_map('intval', (array) old('tags', $question->tagIds));
        $selectedExamSessionIds = array_map('intval', (array) old('exam_sessions', $question->examSessionIds));
    @endphp

    <div class="p-6" style="min-height: calc(100vh - 180px);">
        <div class="max-w-6xl mx-auto">

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
                <span class="px-2.5 py-1 text-xs font-semibold text-indigo-800 bg-indigo-50 rounded-md">questions Table</span>
                <h3 class="text-xl font-bold text-gray-800 mt-2">Edit Question</h3>
                <p class="text-xs text-gray-500 mt-1">Modify the question text, options, and configuration.</p>
            </div>

            <form action="{{ route('admin.questions.update', $question->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">

                    {{-- Main column: question, options, additional details --}}
                    <div class="xl:col-span-2 space-y-6">

                        <!-- Question -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-gray-100">
                                <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 text-xs">
                                    <i class="fa-solid fa-circle-question"></i>
                                </span>
                                <h3 class="text-sm font-bold text-gray-800">Question</h3>
                            </div>

                            <div class="mb-4">
                                <label for="question_text" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Question Text</label>
                                <textarea id="question_text" name="question_text" rows="4" required placeholder="e.g. What is the capital of France?" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('question_text', $question->questionText) }}</textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Options</label>
                                <p class="text-xs text-gray-500 mb-3">Tap the radio next to the correct answer.</p>

                                <div class="space-y-3">
                                    @forelse ($question->options as $optionIndex => $option)
                                        <div class="flex items-center gap-3">
                                            <input type="radio"
                                                   name="correct_option"
                                                   value="{{ $optionIndex }}"
                                                   @checked(old('correct_option') !== null ? (string) old('correct_option') === (string) $optionIndex : $option->isCorrect)
                                                   title="Mark as correct answer"
                                                   class="h-4 w-4 shrink-0 text-indigo-600 border-gray-300 focus:ring-indigo-500 cursor-pointer">
                                            <input type="text"
                                                   name="options[{{ $optionIndex }}][option_text]"
                                                   value="{{ old('options.' . $optionIndex . '.option_text', $option->optionText) }}"
                                                   placeholder="Option {{ $optionIndex + 1 }}"
                                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <input type="hidden" name="options[{{ $optionIndex }}][sort_order]" value="{{ $option->sortOrder }}">
                                        </div>
                                    @empty
                                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                            This question has no options yet. Re-save it with at least two options.
                                        </p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <!-- Additional Details -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-gray-100">
                                <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 text-xs">
                                    <i class="fa-solid fa-circle-info"></i>
                                </span>
                                <h3 class="text-sm font-bold text-gray-800">Additional Details <span class="font-medium text-gray-400">(optional)</span></h3>
                            </div>

                            <div class="space-y-4">
                                <!-- Explanation -->
                                <div>
                                    <label for="explanation" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Explanation</label>
                                    <textarea id="explanation" name="explanation" rows="3" placeholder="e.g. Paris is the capital and most populous city of France." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('explanation', $question->explanation) }}</textarea>
                                </div>

                                <!-- Study Link -->
                                <div>
                                    <label for="study_link" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Study Link</label>
                                    <input type="url" id="study_link" name="study_link" value="{{ old('study_link', $question->studyLink) }}" placeholder="e.g. https://example.com/lessons/french-capital" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <!-- Hint Text -->
                                <div>
                                    <label for="hint_text" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Hint</label>
                                    <input type="text" id="hint_text" name="hint_text" value="{{ old('hint_text', $question->hintText) }}" maxlength="255" placeholder="e.g. Think of the Seine river." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sidebar: save, taxonomy, configuration, associations --}}
                    <div class="space-y-6">

                        <!-- Save -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-gray-100">
                                <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 text-xs">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                </span>
                                <h3 class="text-sm font-bold text-gray-800">Save</h3>
                            </div>

                            <button type="submit" class="w-full px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                                Update Question
                            </button>
                            <a href="{{ route('admin.questions.index') }}" class="block w-full text-center mt-2 px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">Cancel</a>
                        </div>

                        <!-- Taxonomy -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-gray-100">
                                <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 text-xs">
                                    <i class="fa-solid fa-sitemap"></i>
                                </span>
                                <h3 class="text-sm font-bold text-gray-800">Taxonomy</h3>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label for="taxonomy_category" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Category</label>
                                    <select id="taxonomy_category" name="taxonomies[]" data-placeholder="Select a category" class="question-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value=""></option>
                                        @forelse ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(in_array($category->id, $selectedTaxonomyIds))>{{ $category->name }}</option>
                                        @empty
                                            <option value="" disabled>No categories available yet</option>
                                        @endforelse
                                    </select>
                                </div>

                                <div>
                                    <label for="taxonomy_subject" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Subject</label>
                                    <select id="taxonomy_subject" name="taxonomies[]" data-placeholder="Select a subject" class="question-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value=""></option>
                                        @forelse ($subjects as $subject)
                                            <option value="{{ $subject->id }}" @selected(in_array($subject->id, $selectedTaxonomyIds))>{{ $subject->name }}</option>
                                        @empty
                                            <option value="" disabled>No subjects available yet</option>
                                        @endforelse
                                    </select>
                                </div>

                                <div>
                                    <label for="taxonomy_chapter" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Chapter <span class="font-normal normal-case text-gray-400">(optional)</span></label>
                                    <select id="taxonomy_chapter" name="taxonomies[]" data-placeholder="Select a chapter" class="question-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value=""></option>
                                        @forelse ($chapters as $chapter)
                                            <option value="{{ $chapter->id }}" @selected(in_array($chapter->id, $selectedTaxonomyIds))>{{ $chapter->name }}</option>
                                        @empty
                                            <option value="" disabled>No chapters available yet</option>
                                        @endforelse
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Question Configuration -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-gray-100">
                                <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 text-xs">
                                    <i class="fa-solid fa-sliders"></i>
                                </span>
                                <h3 class="text-sm font-bold text-gray-800">Question Configuration</h3>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Question Type -->
                                <div>
                                    <label for="question_type" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Type</label>
                                    <select id="question_type" name="question_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @foreach (\Core\Question\Domain\Enums\QuestionType::cases() as $type)
                                            <option value="{{ $type->value }}" @selected(old('question_type', $question->questionType) === $type->value)>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Difficulty Level -->
                                <div>
                                    <label for="difficulty_level" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Difficulty</label>
                                    <select id="difficulty_level" name="difficulty_level" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @foreach (\Core\Question\Domain\Enums\QuestionDifficultyLevel::cases() as $level)
                                            <option value="{{ $level->value }}" @selected(old('difficulty_level', $question->difficultyLevel) === $level->value)>
                                                {{ $level->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Is Active -->
                            <div class="flex items-center justify-between px-4 py-3 border border-gray-200 rounded-xl mt-4">
                                <div>
                                    <label for="is_active" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider">Active</label>
                                    <p class="text-xs text-gray-500 mt-0.5">Active questions are available for use.</p>
                                </div>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $question->isActive)) class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            </div>
                        </div>

                        <!-- Associations -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center gap-2 pb-3 mb-5 border-b border-gray-100">
                                <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 text-xs">
                                    <i class="fa-solid fa-link"></i>
                                </span>
                                <h3 class="text-sm font-bold text-gray-800">Associations <span class="font-medium text-gray-400">(optional)</span></h3>
                            </div>

                            <div class="space-y-4">
                                <!-- Exam Sessions -->
                                <div>
                                    <label for="exam_sessions" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Exam Sessions</label>
                                    <select id="exam_sessions" name="exam_sessions[]" multiple data-placeholder="Select exam sessions" class="question-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @forelse ($examSessions as $session)
                                            <option value="{{ $session->id }}" @selected(in_array($session->id, $selectedExamSessionIds))>
                                                {{ $session->name }}{{ $session->year ? ' (' . $session->year . ')' : '' }}
                                            </option>
                                        @empty
                                            <option value="" disabled>No exam sessions available yet</option>
                                        @endforelse
                                    </select>
                                </div>

                                <!-- Tags -->
                                <div>
                                    <label for="tags" class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Tags</label>
                                    <select id="tags" name="tags[]" multiple data-placeholder="Select tags" class="question-select w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @forelse ($tags as $tag)
                                            <option value="{{ $tag->id }}" @selected(in_array($tag->id, $selectedTagIds))>{{ $tag->name }}</option>
                                        @empty
                                            <option value="" disabled>No tags available yet</option>
                                        @endforelse
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>

    @push('styles')
        <style>
            /* Make Select2 match the Tailwind input styling of this form */
            .question-select + .select2-container .select2-selection--single {
                height: 42px;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                font-size: 14px;
            }
            .question-select + .select2-container .select2-selection--single .select2-selection__rendered {
                line-height: 40px;
                padding-left: 12px;
                color: #374151;
            }
            .question-select + .select2-container .select2-selection--single .select2-selection__arrow {
                height: 40px;
            }
            .question-select + .select2-container .select2-selection--single .select2-selection__placeholder {
                color: #9ca3af;
            }

            /* Multiple mode (tags, exam sessions) */
            .question-select + .select2-container .select2-selection--multiple {
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                font-size: 14px;
                min-height: 42px;
            }
            .question-select + .select2-container .select2-selection--multiple .select2-selection__choice {
                background-color: #eef2ff;
                border: 1px solid #c7d2fe;
                color: #3730a3;
                border-radius: 0.5rem;
                font-size: 13px;
            }
            .question-select + .select2-container .select2-selection--multiple .select2-selection__rendered {
                padding: 4px 8px;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            $(function () {
                $('.question-select').each(function () {
                    var $el = $(this);
                    $el.select2({
                        placeholder: $el.data('placeholder') || 'Select...',
                        allowClear: true,
                        multiple: $el.prop('multiple'),
                        width: '100%',
                    });
                });
            });
        </script>
    @endpush
@endsection