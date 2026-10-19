@extends('layouts.master')

@section('title', 'Add Company')

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
                            <span class="badge bg-primary text-uppercase sm-text">companies table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Add Company</h3>
                            <p class="mb-0 text-muted sm-text">Only the name is required; the rest can wait.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.companies.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <form class="row g-3" action="{{ route('admin.companies.store') }}" method="POST">
                        @csrf

                        <div class="col-md-8">
                            <label for="name" class="form-label text-capitalize sm-text">Company Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}"
                                maxlength="{{ \Core\Company\Domain\CompanyRules::MAX_NAME_LENGTH }}"
                                placeholder="e.g. Acme Ltd"
                                class="form-control sm-text @error('name') is-invalid @enderror">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="industry" class="form-label text-capitalize sm-text">Industry</label>
                            <input type="text" id="industry" name="industry" value="{{ old('industry') }}"
                                maxlength="{{ \Core\Company\Domain\CompanyRules::MAX_INDUSTRY_LENGTH }}"
                                placeholder="e.g. Software"
                                class="form-control sm-text @error('industry') is-invalid @enderror">
                            @error('industry')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="website" class="form-label text-capitalize sm-text">Website</label>
                            <input type="url" id="website" name="website" value="{{ old('website') }}"
                                maxlength="{{ \Core\Company\Domain\CompanyRules::MAX_WEBSITE_LENGTH }}"
                                placeholder="https://example.com"
                                class="form-control sm-text @error('website') is-invalid @enderror">
                            @error('website')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text sm-text">Include the scheme, e.g. https://.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label text-capitalize sm-text">Phone</label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                                maxlength="{{ \Core\Company\Domain\CompanyRules::MAX_PHONE_LENGTH }}"
                                placeholder="e.g. +1 555 0100"
                                class="form-control sm-text @error('phone') is-invalid @enderror">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label text-capitalize sm-text">Address</label>
                            <textarea id="address" name="address" rows="3" placeholder="Street, city, country..."
                                class="form-control sm-text @error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.companies.index') }}"
                                class="btn btn-light sm-text text-uppercase btn-min-width">cancel</a>
                            <button type="submit" class="btn btn-primary sm-text text-uppercase btn-min-width">
                                save company
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
