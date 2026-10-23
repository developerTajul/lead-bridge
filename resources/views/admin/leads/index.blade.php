@extends('layouts.master')

@section('title', 'Leads')

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

                    @if (session('warning'))
                        <div class="alert alert-warning d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-exclamation-circle xlg-text"></i>
                            <span>{{ session('warning') }}</span>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h3 class="mt-2 mb-1 text-capitalize">Lead List</h3>
                            <p class="mb-0 text-muted sm-text">All leads come from different sources</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge rounded-pill bg-light text-dark sm-text">
                                {{ $pagination->total }} total
                            </span>
                            <a href="#" class="btn btn-primary sm-text text-uppercase">new lead</a>
                        </div>
                    </div>

                    {{-- Filters post through GET so the criteria stay in the query
                         string, which keeps them shareable and bookmarkable. --}}
                    <form method="GET" action="{{ route('admin.leads.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-sm-4 col-lg-3">
                            <label for="search" class="form-label sm-text text-uppercase mb-1">Search</label>
                            <input type="text"
                                   id="search"
                                   name="search"
                                   value="{{ $filters['search'] ?? '' }}"
                                   class="form-control form-control-sm"
                                   placeholder="Name, email, phone or company">
                        </div>

                        <div class="col-6 col-sm-3 col-lg-2">
                            <label for="status" class="form-label sm-text text-uppercase mb-1">Status</label>
                            <select id="status" name="status" class="form-select form-select-sm">
                                <option value="">All statuses</option>
                                @foreach ($statusOptions as $option)
                                    <option value="{{ $option['value'] }}"
                                        @selected(($filters['status'] ?? '') === $option['value'])>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-sm-2 col-lg-2">
                            <label for="per_page" class="form-label sm-text text-uppercase mb-1">Per page</label>
                            <select id="per_page" name="per_page" class="form-select form-select-sm">
                                @foreach ([10, 15, 25, 50] as $size)
                                    <option value="{{ $size }}"
                                        @selected((int) ($filters['per_page'] ?? 0) === $size)>
                                        {{ $size }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary sm-text text-uppercase">filter</button>
                            <a href="{{ route('admin.leads.index') }}" class="btn btn-transparent btn-primary sm-text text-uppercase">reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email / Phone</th>
                                    <th scope="col">Company</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Source</th>
                                    <th scope="col">Created</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($leads as $lead)
                                    <tr>
                                        <th scope="row" class="text-center font-monospace text-muted">{{ $lead->id }}</th>
                                        <td class="text-capitalize fw-semibold">{{ $lead->name }}</td>
                                        <td>
                                            @if ($lead->email)
                                                <div class="text-truncate" style="max-width: 220px;">{{ $lead->email }}</div>
                                            @endif
                                            @if ($lead->phone)
                                                <div class="text-muted">{{ $lead->phone }}</div>
                                            @endif
                                            @if (! $lead->email && ! $lead->phone)
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-capitalize">{{ $lead->company_name ?? '—' }}</td>
                                        <td class="text-center">
                                            {{-- Bootstrap 5.0 has no text-bg-* helpers, so the
                                                 badge pairs bg-* with a readable text colour. --}}
                                            @php
                                                $statusStyles = [
                                                    'new' => 'bg-primary text-white',
                                                    'contacted' => 'bg-info text-dark',
                                                    'qualified' => 'bg-warning text-dark',
                                                    'converted' => 'bg-success text-white',
                                                    'lost' => 'bg-danger text-white',
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusStyles[$lead->status] ?? 'bg-secondary text-white' }}">
                                                {{ $lead->status_label }}
                                            </span>
                                        </td>
                                        <td class="text-center text-capitalize">{{ $lead->source }}</td>
                                        {{-- Stored as an ISO-8601 string; reformatted here so the
                                             table shows a readable date instead of the wire format. --}}
                                        <td class="text-nowrap">
                                            {{ $lead->created_at ? \Illuminate\Support\Carbon::parse($lead->created_at)->format('M d, Y h:i A') : '—' }}
                                        </td>
                                        <td>
                                            <ul class="t-list d-flex justify-content-center mb-0">
                                                <li class="t-list__item">
                                                    <a href="#" class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-eye"></i></span>
                                                            <span class="text-capitalize">view</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    <a href="#" class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-cog"></i></span>
                                                            <span class="text-capitalize">edit</span>
                                                        </span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="las la-user-plus display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted">No leads found.</p>
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
                                ({{ $pagination->total }} {{ Str::plural('lead', $pagination->total) }})
                            </p>
                            <div class="d-flex align-items-center gap-2">
                                @if ($pagination->currentPage > 1)
                                    {{-- $filters is merged so the active criteria survive
                                         every page change. --}}
                                    <a href="{{ route('admin.leads.index', array_merge($filters, ['page' => $pagination->currentPage - 1])) }}"
                                       class="btn btn-transparent btn-primary sm-text text-uppercase">
                                        <i class="las la-arrow-left t-mr-5"></i>prev
                                    </a>
                                @endif
                                @if ($pagination->currentPage < $pagination->lastPage)
                                    <a href="{{ route('admin.leads.index', array_merge($filters, ['page' => $pagination->currentPage + 1])) }}"
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
