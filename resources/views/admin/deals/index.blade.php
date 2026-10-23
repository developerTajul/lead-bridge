@extends('layouts.master')

@section('title', 'Deals')

@php
    // The controller hands these over on a failed read too, but the list is
    // still worth rendering when the options read is the thing that failed.
    // The list rows are flat summaries, so the labels for the linked records
    // and the stage are resolved from these rows rather than from a relation.
    $pipelineOptions = $options?->pipelines ?? [];
    $stageOptions = $options?->stages ?? [];
    $companyOptions = $options?->companies ?? [];
    $contactOptions = $options?->contacts ?? [];
@endphp

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
                            <span class="badge bg-primary text-uppercase sm-text">deals table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Deal List</h3>
                            <p class="mb-0 text-muted sm-text">Every opportunity, and the stage it currently sits in.
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge rounded-pill bg-light text-dark sm-text">
                                {{ $pagination->total }} total
                            </span>
                            <a href="{{ route('admin.deals.create') }}" class="btn btn-primary sm-text text-uppercase">
                                add deal
                            </a>
                        </div>
                    </div>

                    {{-- The stage picker is deliberately absent: it holds one pipeline's
                         stages, so it only means something once a pipeline is chosen. --}}
                    <form method="GET" action="{{ route('admin.deals.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-md-4">
                            <label for="pipeline_id" class="form-label text-capitalize sm-text">Pipeline</label>
                            <select id="pipeline_id" name="pipeline_id" class="form-select sm-text">
                                <option value="">All pipelines</option>
                                @foreach ($pipelineOptions as $pipeline)
                                    <option value="{{ $pipeline->id }}"
                                        @selected((string) ($filters['pipeline_id'] ?? '') === (string) $pipeline->id)>
                                        {{ $pipeline->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="per_page" class="form-label text-capitalize sm-text">Per Page</label>
                            <input type="number" id="per_page" name="per_page"
                                value="{{ $filters['per_page'] ?? '' }}" min="1"
                                max="{{ \Core\Deal\Domain\DealRules::MAX_PER_PAGE }}" class="form-control sm-text">
                        </div>
                        <div class="col-12 col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary sm-text text-uppercase">filter</button>
                            <a href="{{ route('admin.deals.index') }}"
                                class="btn btn-light sm-text text-uppercase">reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Company</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Pipeline</th>
                                    <th scope="col">Stage</th>
                                    <th scope="col" class="text-end">Amount</th>
                                    <th scope="col">Expected Close</th>
                                    <th scope="col">Actual Close</th>
                                    <th scope="col" class="text-center">Actions</th>

                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($deals as $deal)
                                    <tr>
                                        <th scope="row" class="text-center font-monospace text-muted">{{ $deal->id }}</th>
                                        <td class="fw-semibold">{{ $deal->title }}</td>
                                        {{-- The list row is a flat deal summary: the linked
                                             records are only hydrated on the detail read, so
                                             these columns carry the stored ids and the stage
                                             and pipeline names are read from the filter's own
                                             option rows. --}}
                                        <td>
                                            @php($companyLabel = collect($companyOptions)->firstWhere('id', $deal->company_id)?->label)
                                            @if ($companyLabel)
                                                {{ $companyLabel }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php($contactLabel = collect($contactOptions)->firstWhere('id', $deal->contact_id)?->label)
                                            @if ($contactLabel)
                                                {{ $contactLabel }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php($pipelineLabel = collect($pipelineOptions)->firstWhere('id', $deal->pipeline_id)?->label)
                                            @if ($pipelineLabel)
                                                {{ $pipelineLabel }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php($stageLabel = collect($stageOptions)->firstWhere('id', $deal->deal_stage_id)?->label)
                                            @if ($stageLabel)
                                                <span class="badge bg-light text-dark sm-text">{{ $stageLabel }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap font-monospace">
                                            {{ $deal->amount }} {{ $deal->currency }}
                                        </td>
                                        <td class="text-nowrap">{{ $deal->expected_close_date ?? '—' }}</td>
                                        <td class="text-nowrap">
                                            {{-- A stored close date means the deal is done, one way
                                                 or the other, so it is called out rather than
                                                 shown as another grey dash. --}}
                                            @if ($deal->actual_close_date)
                                                <span class="badge bg-success sm-text">
                                                    {{ $deal->actual_close_date }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <ul class="t-list d-flex justify-content-center mb-0">
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.deals.show', $deal->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-eye"></i></span>
                                                            <span class="text-capitalize">view</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.deals.edit', $deal->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-cog"></i></span>
                                                            <span class="text-capitalize">edit</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    {{-- Deletes sit behind a confirmation so a stray click
                                                         cannot remove a deal mid-negotiation. --}}
                                                    <form action="{{ route('admin.deals.destroy', $deal->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('Delete this deal?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="t-link t-link--alpha border-0 bg-transparent p-0">
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
                                        <td colspan="10" class="text-center py-5">
                                            <i class="las la-handshake display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted">No deals found.</p>
                                            <a href="{{ route('admin.deals.create') }}"
                                                class="btn btn-primary sm-text text-uppercase mt-3">
                                                add your first deal
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
                                ({{ $pagination->total }} {{ Str::plural('deal', $pagination->total) }})
                            </p>
                            <div class="d-flex align-items-center gap-2">
                                @if ($pagination->currentPage > 1)
                                    {{-- The current query string is carried forward so the
                                         active criteria survive a page change. --}}
                                    <a href="{{ route('admin.deals.index', array_merge(request()->query(), ['page' => $pagination->currentPage - 1])) }}"
                                        class="btn btn-transparent btn-primary sm-text text-uppercase">
                                        <i class="las la-arrow-left t-mr-5"></i>prev
                                    </a>
                                @endif
                                @if ($pagination->currentPage < $pagination->lastPage)
                                    <a href="{{ route('admin.deals.index', array_merge(request()->query(), ['page' => $pagination->currentPage + 1])) }}"
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
