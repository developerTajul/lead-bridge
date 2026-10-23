@php
    /**
     * The fields both the create and the edit form show. The company and
     * contact pickers offer a "no link" entry, because a deal is allowed to
     * carry neither; the pipeline and stage pickers do not, because a deal
     * always sits in one of each.
     */
    $options = $options ?? null;

    $pipelineOptions = $options?->pipelines ?? [];
    $stageOptions = $options?->stages ?? [];
    $companyOptions = $options?->companies ?? [];
    $contactOptions = $options?->contacts ?? [];

    // The stage list is narrowed to the pipeline being edited, so a stage can
    // never be saved against a pipeline it does not belong to. On a rejected
    // submission the old values win, so the choice the user made survives.
    $selectedPipelineId = old('pipeline_id', $deal->pipeline_id ?? null);
    $selectedStageId = old('deal_stage_id', $deal->deal_stage_id ?? null);
@endphp

<div class="col-md-8">
    <label for="title" class="form-label text-capitalize sm-text">Deal Title</label>
    <input type="text" id="title" name="title"
        value="{{ old('title', $deal->title ?? '') }}"
        maxlength="{{ \Core\Deal\Domain\DealRules::MAX_TITLE_LENGTH }}"
        placeholder="e.g. Acme Renewal"
        class="form-control sm-text @error('title') is-invalid @enderror">
    @error('title')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-4">
    <label for="currency" class="form-label text-capitalize sm-text">Currency</label>
    <input type="text" id="currency" name="currency"
        value="{{ old('currency', $deal->currency ?? 'BDT') }}"
        maxlength="{{ \Core\Deal\Domain\DealRules::CURRENCY_LENGTH }}"
        placeholder="e.g. BDT"
        class="form-control sm-text text-uppercase @error('currency') is-invalid @enderror">
    @error('currency')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text sm-text">A three-letter code, such as BDT or USD.</div>
</div>

<div class="col-md-4">
    <label for="amount" class="form-label text-capitalize sm-text">Amount</label>
    <input type="number" id="amount" name="amount"
        value="{{ old('amount', $deal->amount ?? '') }}"
        min="0" step="0.01" placeholder="e.g. 2500.00"
        class="form-control sm-text @error('amount') is-invalid @enderror">
    @error('amount')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-4">
    <label for="pipeline_id" class="form-label text-capitalize sm-text">Pipeline</label>
    <select id="pipeline_id" name="pipeline_id"
        class="form-select sm-text @error('pipeline_id') is-invalid @enderror"
        data-stage-source>
        <option value="">Choose a pipeline</option>
        @foreach ($pipelineOptions as $pipeline)
            <option value="{{ $pipeline->id }}" @selected((string) $selectedPipelineId === (string) $pipeline->id)>
                {{ $pipeline->label }}
            </option>
        @endforeach
    </select>
    @error('pipeline_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-4">
    <label for="deal_stage_id" class="form-label text-capitalize sm-text">Stage</label>
    <select id="deal_stage_id" name="deal_stage_id"
        class="form-select sm-text @error('deal_stage_id') is-invalid @enderror">
        <option value="">Choose a stage</option>
        @foreach ($stageOptions as $stage)
            <option value="{{ $stage->id }}" data-pipeline-id="{{ $stage->group_id }}"
                @selected((string) $selectedStageId === (string) $stage->id)>
                {{ $stage->label }}
            </option>
        @endforeach
    </select>
    @error('deal_stage_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text sm-text">Moving a deal into a closing stage stamps its close date.</div>
</div>

<div class="col-md-6">
    <label for="company_id" class="form-label text-capitalize sm-text">Company</label>
    <select id="company_id" name="company_id"
        class="form-select sm-text @error('company_id') is-invalid @enderror">
        <option value="">No company</option>
        @foreach ($companyOptions as $company)
            <option value="{{ $company->id }}"
                @selected((string) old('company_id', $deal->company_id ?? '') === (string) $company->id)>
                {{ $company->label }}
            </option>
        @endforeach
    </select>
    @error('company_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6">
    <label for="contact_id" class="form-label text-capitalize sm-text">Contact</label>
    <select id="contact_id" name="contact_id"
        class="form-select sm-text @error('contact_id') is-invalid @enderror">
        <option value="">No contact</option>
        @foreach ($contactOptions as $contact)
            <option value="{{ $contact->id }}"
                @selected((string) old('contact_id', $deal->contact_id ?? '') === (string) $contact->id)>
                {{ $contact->label }}
            </option>
        @endforeach
    </select>
    @error('contact_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6">
    <label for="expected_close_date" class="form-label text-capitalize sm-text">Expected Close Date</label>
    <input type="date" id="expected_close_date" name="expected_close_date"
        value="{{ old('expected_close_date', $deal->expected_close_date ?? '') }}"
        class="form-control sm-text @error('expected_close_date') is-invalid @enderror">
    @error('expected_close_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text sm-text">When you expect the deal to close, not when it did.</div>
</div>

{{-- The stage list belongs to one pipeline, so the picker is narrowed as soon
     as a pipeline is chosen. Without this the form would offer stages from
     every pipeline at once. --}}
@once
    @push('scripts')
        <script>
            (function () {
                var pipeline = document.getElementById('pipeline_id');
                var stage = document.getElementById('deal_stage_id');

                if (!pipeline || !stage) {
                    return;
                }

                var allOptions = Array.prototype.slice.call(stage.options);

                function narrowStageList() {
                    var chosen = pipeline.value;

                    stage.innerHTML = '';

                    allOptions.forEach(function (option) {
                        var belongsToChosen = option.dataset.pipelineId === chosen;
                        var isPlaceholder = option.value === '';

                        if (isPlaceholder || chosen === '' || belongsToChosen) {
                            stage.appendChild(option);
                        }
                    });

                    // A stage from another pipeline is no longer on the list, so
                    // the selection has to follow it off.
                    if (stage.selectedIndex === -1) {
                        stage.value = '';
                    }
                }

                pipeline.addEventListener('change', narrowStageList);
                narrowStageList();
            })();
        </script>
    @endpush
@endonce
