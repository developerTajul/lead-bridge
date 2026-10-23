@extends('layouts.master')

@section('title', 'Edit Pipeline')

@section('content')
    <div class="container-fluid px-3">
        <div class="row g-3">
            <div class="col-12">
                <div class="t-shadow t-bg-white p-4">

                    @if (session('error'))
                        <div class="alert alert-danger d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-exclamation-triangle xlg-text"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-primary text-uppercase sm-text">pipeline #{{ $pipeline->id }}</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Edit Pipeline</h3>
                            <p class="mb-0 text-muted sm-text">Updating {{ $pipeline->name }}.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.pipelines.kanban', $pipeline->id) }}"
                                class="btn btn-light sm-text text-uppercase">board</a>
                            <a href="{{ route('admin.pipelines.show', $pipeline->id) }}"
                                class="btn btn-light sm-text text-uppercase">view</a>
                            <a href="{{ route('admin.pipelines.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    {{-- An edit replaces the whole stage set, so what is shown here is
                         the complete replacement, not a change to merge in. A row the
                         user removes is dropped on save. --}}
                    @php
                        $oldStages = old('stages');
                        $stageRows = is_array($oldStages)
                            ? array_values($oldStages)
                            : array_map(
                                static fn ($stage) => [
                                    'name' => $stage->name,
                                    'order_position' => $stage->order_position,
                                    'probability' => $stage->probability,
                                ],
                                $pipeline->stages,
                            );
                    @endphp

                    <div class="alert alert-warning d-flex align-items-start gap-2 sm-text" role="alert">
                        <i class="las la-exclamation-circle xlg-text"></i>
                        <span>Saving replaces the stage set. A stage removed here is removed from the pipeline, and any
                            deal sitting in it is left without a stage.</span>
                    </div>

                    <form class="row g-3" action="{{ route('admin.pipelines.update', $pipeline->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="col-md-8">
                            <label for="name" class="form-label text-capitalize sm-text">Pipeline Name</label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $pipeline->name) }}"
                                maxlength="{{ \Core\Pipeline\Domain\PipelineRules::MAX_NAME_LENGTH }}"
                                class="form-control sm-text @error('name') is-invalid @enderror">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">A pipeline cannot be left without a name.</div>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_default" value="0">
                                <input type="checkbox" id="is_default" name="is_default" value="1"
                                    @checked(old('is_default', $pipeline->is_default))
                                    class="form-check-input @error('is_default') is-invalid @enderror">
                                <label for="is_default" class="form-check-label text-capitalize sm-text">
                                    Default pipeline
                                </label>
                                @error('is_default')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-12">
                            <hr class="my-2">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                <div>
                                    <h5 class="mb-1 text-capitalize">Stages</h5>
                                    <p class="mb-0 text-muted sm-text">
                                        At least one stage is required. Positions and names must be unique.
                                    </p>
                                </div>
                                <button type="button" id="add-stage"
                                    class="btn btn-light sm-text text-uppercase mt-2 mt-sm-0">
                                    <i class="las la-plus t-mr-5"></i>add stage
                                </button>
                            </div>

                            @error('stages')
                                <div class="alert alert-danger sm-text py-2" role="alert">{{ $message }}</div>
                            @enderror

                            <div class="table-responsive">
                                <table class="table table-bordered t-text-dark--light-15 sm-text align-middle mb-2">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="width: 9rem;">Position</th>
                                            <th scope="col">Stage Name</th>
                                            <th scope="col" style="width: 10rem;">Probability</th>
                                            <th scope="col" class="text-center" style="width: 6rem;">Remove</th>
                                        </tr>
                                    </thead>
                                    <tbody id="stage-rows">
                                        @forelse ($stageRows as $index => $stageRow)
                                            <tr class="stage-row">
                                                <td>
                                                    <input type="number"
                                                        name="stages[{{ $index }}][order_position]"
                                                        value="{{ $stageRow['order_position'] ?? $index + 1 }}"
                                                        min="{{ \Core\Pipeline\Domain\PipelineRules::MIN_ORDER_POSITION }}"
                                                        class="form-control sm-text @error("stages.$index.order_position") is-invalid @enderror">
                                                    @error("stages.$index.order_position")
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="text" name="stages[{{ $index }}][name]"
                                                        value="{{ $stageRow['name'] ?? '' }}"
                                                        maxlength="{{ \Core\Pipeline\Domain\PipelineRules::MAX_STAGE_NAME_LENGTH }}"
                                                        class="form-control sm-text @error("stages.$index.name") is-invalid @enderror">
                                                    @error("stages.$index.name")
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number"
                                                            name="stages[{{ $index }}][probability]"
                                                            value="{{ $stageRow['probability'] ?? 0 }}"
                                                            min="{{ \Core\Pipeline\Domain\PipelineRules::MIN_PROBABILITY }}"
                                                            max="{{ \Core\Pipeline\Domain\PipelineRules::MAX_PROBABILITY }}"
                                                            class="form-control sm-text @error("stages.$index.probability") is-invalid @enderror">
                                                        <span class="input-group-text sm-text">%</span>
                                                        @error("stages.$index.probability")
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button"
                                                        class="btn btn-light sm-text remove-stage"
                                                        title="Remove this stage">
                                                        <i class="las la-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            {{-- The form rules require a stage, so a pipeline
                                                 stored without one still gets a row to fill. --}}
                                            <tr class="stage-row">
                                                <td>
                                                    <input type="number" name="stages[0][order_position]" value="1"
                                                        min="{{ \Core\Pipeline\Domain\PipelineRules::MIN_ORDER_POSITION }}"
                                                        placeholder="1" class="form-control sm-text">
                                                </td>
                                                <td>
                                                    <input type="text" name="stages[0][name]" value=""
                                                        maxlength="{{ \Core\Pipeline\Domain\PipelineRules::MAX_STAGE_NAME_LENGTH }}"
                                                        placeholder="e.g. Qualification" class="form-control sm-text">
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" name="stages[0][probability]" value="0"
                                                            min="{{ \Core\Pipeline\Domain\PipelineRules::MIN_PROBABILITY }}"
                                                            max="{{ \Core\Pipeline\Domain\PipelineRules::MAX_PROBABILITY }}"
                                                            placeholder="0" class="form-control sm-text">
                                                        <span class="input-group-text sm-text">%</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-light sm-text remove-stage"
                                                        title="Remove this stage">
                                                        <i class="las la-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.pipelines.index') }}"
                                class="btn btn-light sm-text text-uppercase btn-min-width">cancel</a>
                            <button type="submit" class="btn btn-primary sm-text text-uppercase btn-min-width">
                                save changes
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <template id="stage-row-template">
        <tr class="stage-row">
            <td>
                <input type="number" name="stages[__INDEX__][order_position]" value="" min="1" placeholder="1"
                    class="form-control sm-text">
            </td>
            <td>
                <input type="text" name="stages[__INDEX__][name]" value=""
                    maxlength="{{ \Core\Pipeline\Domain\PipelineRules::MAX_STAGE_NAME_LENGTH }}"
                    placeholder="e.g. Qualification" class="form-control sm-text">
            </td>
            <td>
                <div class="input-group">
                    <input type="number" name="stages[__INDEX__][probability]" value="0"
                        min="{{ \Core\Pipeline\Domain\PipelineRules::MIN_PROBABILITY }}"
                        max="{{ \Core\Pipeline\Domain\PipelineRules::MAX_PROBABILITY }}" placeholder="0"
                        class="form-control sm-text">
                    <span class="input-group-text sm-text">%</span>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-light sm-text remove-stage" title="Remove this stage">
                    <i class="las la-times"></i>
                </button>
            </td>
        </tr>
    </template>
@endsection

@section('scripts')
    <script>
        (function () {
            var rows = document.getElementById('stage-rows');
            var template = document.getElementById('stage-row-template');

            if (!rows || !template) {
                return;
            }

            // The highest index in use is tracked rather than reusing a removed
            // row's index, so the posted keys never collide.
            var nextIndex = rows.querySelectorAll('.stage-row').length;

            function nextPosition() {
                return rows.querySelectorAll('.stage-row').length + 1;
            }

            document.getElementById('add-stage').addEventListener('click', function () {
                var html = template.innerHTML.split('__INDEX__').join(nextIndex);

                nextIndex += 1;

                var row = document.createElement('tbody');
                row.innerHTML = html;

                var added = row.querySelector('.stage-row');
                added.querySelector('[name$="[order_position]"]').value = nextPosition();

                rows.appendChild(added);
            });

            // Delegated, so it covers rows the script adds as well as the ones
            // rendered from stored state or old input.
            rows.addEventListener('click', function (event) {
                var button = event.target.closest('.remove-stage');

                if (!button) {
                    return;
                }

                // One stage is the minimum the pipeline can be made of, so the
                // last remaining row stays put.
                if (rows.querySelectorAll('.stage-row').length <= 1) {
                    return;
                }

                button.closest('.stage-row').remove();
            });
        })();
    </script>
@endsection
