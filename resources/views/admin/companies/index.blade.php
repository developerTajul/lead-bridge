@extends('layouts.master')

@section('title', 'Companies')

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

                    @if (session('error') || !empty($error))
                        <div class="alert alert-danger d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-exclamation-triangle xlg-text"></i>
                            <span>{{ session('error') ?? $error }}</span>
                        </div>
                    @endif

                    {{-- The controller flashes this when a bookmark or hand-edited
                         URL carried a filter value it had to drop. --}}
                    @if (session('warning'))
                        <div class="alert alert-warning d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-exclamation-circle xlg-text"></i>
                            <span>{{ session('warning') }}</span>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-primary text-uppercase sm-text">companies table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Company List</h3>
                            <p class="mb-0 text-muted sm-text">Accounts your contacts, deals, and activities hang off.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge rounded-pill bg-light text-dark sm-text">
                                {{ $pagination->total }} total
                            </span>
                            <a href="{{ route('admin.companies.create') }}" class="btn btn-primary sm-text text-uppercase">
                                add company
                            </a>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.companies.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-md-6">
                            <label for="search" class="form-label text-capitalize sm-text">Search</label>
                            <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                                placeholder="Company name or industry" class="form-control sm-text">
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="per_page" class="form-label text-capitalize sm-text">Per Page</label>
                            <input type="number" id="per_page" name="per_page"
                                value="{{ $filters['per_page'] ?? '' }}" min="1"
                                max="{{ \Core\Company\Domain\CompanyRules::MAX_PER_PAGE }}" class="form-control sm-text">
                        </div>
                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary sm-text text-uppercase">filter</button>
                            <a href="{{ route('admin.companies.index') }}"
                                class="btn btn-light sm-text text-uppercase">reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Company</th>
                                    <th scope="col">Industry</th>
                                    <th scope="col">Phone</th>
                                    <th scope="col">Website</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($companies as $company)
                                    <tr>
                                        <th scope="row" class="text-center font-monospace text-muted">{{ $company->id }}</th>
                                        <td class="fw-semibold">{{ $company->name }}</td>
                                        <td>
                                            @if ($company->industry)
                                                {{ $company->industry }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($company->phone)
                                                {{ $company->phone }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($company->website)
                                                <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer"
                                                    class="t-link t-link--alpha">{{ $company->website }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <ul class="t-list d-flex justify-content-center mb-0">
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.companies.show', $company->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-eye"></i></span>
                                                            <span class="text-capitalize">view</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.companies.edit', $company->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-cog"></i></span>
                                                            <span class="text-capitalize">edit</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    {{-- Deletes sit behind a confirmation so a stray click
                                                         cannot remove an account and its deals. --}}
                                                    <form action="{{ route('admin.companies.destroy', $company->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('Delete this company?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="t-link t-link--alpha border-0 bg-transparent p-0">
                                                            <span class="d-flex align-items-center">
                                                                <span class="xlg-text t-mr-5"><i class="las la-trash"></i></span>
                                                                <span class="text-capitalize">delete</span>
                                                            </span>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="las la-building display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted">No companies found.</p>
                                            <a href="{{ route('admin.companies.create') }}"
                                                class="btn btn-primary sm-text text-uppercase mt-3">
                                                add your first company
                                            </a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($pagination->total > 0)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                            <p class="mb-0 text-muted sm-text">
                                Showing page {{ $pagination->currentPage }} of {{ $pagination->lastPage }}
                                ({{ $pagination->total }} {{ Str::plural('company', $pagination->total) }})
                            </p>
                            <div class="d-flex align-items-center gap-2">
                                @if ($pagination->currentPage > 1)
                                    {{-- The current query string is carried forward so the
                                         active criteria survive a page change. --}}
                                    <a href="{{ route('admin.companies.index', array_merge(request()->query(), ['page' => $pagination->currentPage - 1])) }}"
                                        class="btn btn-transparent btn-primary sm-text text-uppercase">
                                        <i class="las la-arrow-left t-mr-5"></i>prev
                                    </a>
                                @endif
                                @if ($pagination->currentPage < $pagination->lastPage)
                                    <a href="{{ route('admin.companies.index', array_merge(request()->query(), ['page' => $pagination->currentPage + 1])) }}"
                                        class="btn btn-primary sm-text text-uppercase">
                                        next<i class="las la-arrow-right t-ml-5"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection
