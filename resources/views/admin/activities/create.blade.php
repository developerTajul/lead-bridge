@extends('layouts.master')

@section('title', 'Add Activity')

@section('content')
    <div class="container-fluid px-3">
        <div class="row g-3">
            <div class="col-12">
                <div class="t-shadow t-bg-white p-4">

                    @if (session('success'))
                        <div class="alert alert-success d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-check-circle xlg-text"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-exclamation-triangle xlg-text"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-primary text-uppercase sm-text">activities table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Log Activity</h3>
                            <p class="mb-0 text-muted sm-text">Log a call, meeting, email, or task against a record.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.activities.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <form class="row g-3" action="{{ route('admin.activities.store') }}" method="POST">
                        @csrf

                        <div class="col-md-8">
                            <label for="subject" class="form-label text-capitalize sm-text">Subject</label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject') }}"
                                placeholder="e.g. Discovery call with the prospect"
                                class="form-control sm-text @error('subject') is-invalid @enderror">
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="type" class="form-label text-capitalize sm-text">Activity Type</label>
                            <select id="type" name="type"
                                class="form-select sm-text @error('type') is-invalid @enderror">
                                <option value="" disabled {{ old('type') === null ? 'selected' : '' }}>Choose...
                                </option>
                                @foreach (\Core\Activity\Domain\Enums\ActivityType::cases() as $activityType)
                                    <option value="{{ $activityType->value }}"
                                        {{ old('type') === $activityType->value ? 'selected' : '' }}>
                                        {{ $activityType->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label text-capitalize sm-text">Description</label>
                            <textarea id="description" name="description" rows="4" placeholder="Notes, agenda, or outcome..."
                                class="form-control sm-text @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="trackable_type" class="form-label text-capitalize sm-text">Attach To</label>
                            <select id="trackable_type" name="trackable_type"
                                class="form-select sm-text @error('trackable_type') is-invalid @enderror">
                                <option value="" disabled {{ old('trackable_type') === null ? 'selected' : '' }}>
                                    Choose...</option>
                                @foreach ($trackableTypes ?? [] as $trackableType)
                                    <option value="{{ $trackableType['value'] }}"
                                        {{ old('trackable_type') === $trackableType['value'] ? 'selected' : '' }}>
                                        {{ $trackableType['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('trackable_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">The kind of record this activity belongs to.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="trackable_id" class="form-label text-capitalize sm-text">Record ID</label>
                            <input type="number" id="trackable_id" name="trackable_id" value="{{ old('trackable_id') }}"
                                min="1" placeholder="e.g. 42"
                                class="form-control sm-text @error('trackable_id') is-invalid @enderror">
                            @error('trackable_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">The ID of the record selected above.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="due_date" class="form-label text-capitalize sm-text">Due Date</label>
                            <input type="datetime-local" id="due_date" name="due_date" value="{{ old('due_date') }}"
                                class="form-control sm-text @error('due_date') is-invalid @enderror">
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="user_id" class="form-label text-capitalize sm-text">Assigned User</label>
                            <input type="number" id="user_id" name="user_id" value="{{ old('user_id') }}" min="1"
                                placeholder="e.g. 7"
                                class="form-control sm-text @error('user_id') is-invalid @enderror">
                            @error('user_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">Leave blank to leave the activity unassigned.</div>
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.activities.index') }}"
                                class="btn btn-light sm-text text-uppercase btn-min-width">cancel</a>
                            <button type="submit" class="btn btn-primary sm-text text-uppercase btn-min-width">
                                save activity
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
