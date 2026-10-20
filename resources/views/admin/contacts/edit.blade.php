@extends('layouts.master')

@section('title', 'Edit Contact')

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
                            <span class="badge bg-primary text-uppercase sm-text">contacts table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Edit Contact</h3>
                            <p class="mb-0 text-muted sm-text">Updating {{ $contact->name }}.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.contacts.show', $contact->id) }}"
                                class="btn btn-light sm-text text-uppercase">view</a>
                            <a href="{{ route('admin.contacts.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <form class="row g-3" action="{{ route('admin.contacts.update', $contact->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="col-md-6">
                            <label for="name" class="form-label text-capitalize sm-text">Full Name</label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $contact->name) }}"
                                maxlength="{{ \Core\Contact\Domain\ContactRules::MAX_NAME_LENGTH }}"
                                class="form-control sm-text @error('name') is-invalid @enderror">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">A contact cannot be left without a name.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label text-capitalize sm-text">Email</label>
                            <input type="email" id="email" name="email"
                                value="{{ old('email', $contact->email) }}"
                                maxlength="{{ \Core\Contact\Domain\ContactRules::MAX_EMAIL_LENGTH }}"
                                class="form-control sm-text @error('email') is-invalid @enderror">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">A contact cannot be left without an email address.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label text-capitalize sm-text">Phone</label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone', $contact->phone) }}"
                                maxlength="{{ \Core\Contact\Domain\ContactRules::MAX_PHONE_LENGTH }}"
                                class="form-control sm-text @error('phone') is-invalid @enderror">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            {{-- Emptying this field clears it: the mapper reads a blank
                                 submission as an explicit request to drop the value. --}}
                            <div class="form-text sm-text">Clear the field and save to remove the stored number.</div>
                        </div>

                        {{-- The company link is optional as a whole: the title and the
                             primary flag only mean anything once a company is chosen. The
                             company itself is held on the pivot table, so the form shows
                             the currently linked company rather than the contact's own
                             fields. --}}
                        @php($linkedCompany = $contact->companies[0] ?? null)

                        <div class="col-md-6">
                            <label for="company_id" class="form-label text-capitalize sm-text">Company</label>
                            <input type="number" id="company_id" name="company_id"
                                value="{{ old('company_id', $linkedCompany->id ?? '') }}"
                                min="1" placeholder="Optional company ID"
                                class="form-control sm-text @error('company_id') is-invalid @enderror">
                            @error('company_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">
                                Clear the field and save to remove the contact from its company.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="job_title" class="form-label text-capitalize sm-text">Job Title</label>
                            <input type="text" id="job_title" name="job_title"
                                value="{{ old('job_title', $linkedCompany->job_title ?? '') }}"
                                maxlength="{{ \Core\Contact\Domain\ContactRules::MAX_JOB_TITLE_LENGTH }}"
                                placeholder="e.g. CTO"
                                class="form-control sm-text @error('job_title') is-invalid @enderror">
                            @error('job_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_primary" value="0">
                                <input type="checkbox" id="is_primary" name="is_primary" value="1"
                                    @checked(old('is_primary', $linkedCompany->is_primary ?? false))
                                    class="form-check-input @error('is_primary') is-invalid @enderror">
                                <label for="is_primary" class="form-check-label text-capitalize sm-text">
                                    Primary contact for this company
                                </label>
                                @error('is_primary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.contacts.index') }}"
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
@endsection
