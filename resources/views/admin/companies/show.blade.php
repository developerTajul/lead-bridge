@extends('layouts.master')

@section('title', 'Company')

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
                            <span class="badge bg-primary text-uppercase sm-text">company #{{ $company->id }}</span>
                            <h3 class="mt-2 mb-1 text-capitalize">{{ $company->name }}</h3>
                            <p class="mb-0 text-muted sm-text">The account and everything attached to it.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.companies.edit', $company->id) }}"
                                class="btn btn-primary sm-text text-uppercase">edit</a>
                            <a href="{{ route('admin.companies.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <table class="table table-bordered t-text-dark--light-15 sm-text mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th scope="row" class="w-25">Industry</th>
                                        <td>{{ $company->industry ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Phone</th>
                                        <td>{{ $company->phone ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Website</th>
                                        <td>
                                            @if ($company->website)
                                                <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer"
                                                    class="t-link t-link--alpha">{{ $company->website }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Address</th>
                                        <td>{{ $company->address ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0 text-capitalize">Contacts</h5>
                                <span class="badge rounded-pill bg-light text-dark sm-text">
                                    {{ count($company->contacts) }}
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                                    <thead>
                                        <tr>
                                            <th scope="col">Name</th>
                                            <th scope="col">Email</th>
                                            <th scope="col" class="text-center">Primary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($company->contacts as $contact)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $contact->name }}</div>
                                                    @if ($contact->job_title)
                                                        <div class="text-muted">{{ $contact->job_title }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $contact->email ?? '—' }}</td>
                                                <td class="text-center">
                                                    @if ($contact->is_primary)
                                                        <span class="badge bg-success text-white">Yes</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-4">No contacts yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0 text-capitalize">Deals</h5>
                                <span class="badge rounded-pill bg-light text-dark sm-text">
                                    {{ count($company->deals) }}
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                                    <thead>
                                        <tr>
                                            <th scope="col">Title</th>
                                            <th scope="col">Stage</th>
                                            <th scope="col" class="text-end">Amount</th>
                                            <th scope="col">Expected Close</th>
                                            <th scope="col" class="text-center">Activities</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($company->deals as $deal)
                                            <tr>
                                                <td class="fw-semibold">{{ $deal->title }}</td>
                                                <td>
                                                    @if ($deal->stage)
                                                        {{ $deal->stage->name }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    {{ $deal->currency }} {{ $deal->amount }}
                                                </td>
                                                <td class="text-nowrap">
                                                    @if ($deal->expected_close_date)
                                                        <time datetime="{{ $deal->expected_close_date }}">
                                                            {{ \Illuminate\Support\Carbon::parse($deal->expected_close_date)->format('M d, Y') }}
                                                        </time>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ count($deal->activities) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No deals yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
