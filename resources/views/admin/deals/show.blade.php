@extends('layouts.master')

@section('title', 'Deal')

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
                            <span class="badge bg-primary text-uppercase sm-text">deal #{{ $deal->id }}</span>
                            <h3 class="mt-2 mb-1 text-capitalize">{{ $deal->title }}</h3>
                            <p class="mb-0 text-muted sm-text">The opportunity, where it sits, and what has happened
                                on it.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.deals.edit', $deal->id) }}"
                                class="btn btn-primary sm-text text-uppercase">edit</a>
                            <a href="{{ route('admin.deals.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <table class="table table-bordered t-text-dark--light-15 sm-text mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th scope="row" class="w-25">Amount</th>
                                        <td class="font-monospace">{{ $deal->amount }} {{ $deal->currency }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Pipeline</th>
                                        <td>
                                            @if ($deal->pipeline)
                                                {{ $deal->pipeline->name }}
                                                @if ($deal->pipeline->is_default)
                                                    <span class="badge bg-light text-dark sm-text">default</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Stage</th>
                                        <td>
                                            @if ($deal->stage)
                                                {{ $deal->stage->name }}
                                                <span class="badge bg-light text-dark sm-text">
                                                    {{ $deal->stage->probability }}%
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Expected Close</th>
                                        <td>{{ $deal->expected_close_date ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Actual Close</th>
                                        <td>
                                            @if ($deal->actual_close_date)
                                                <span class="badge bg-success sm-text">{{ $deal->actual_close_date }}</span>
                                            @else
                                                {{-- No stored close date means the deal is still open,
                                                     whatever stage it sits in. --}}
                                                <span class="text-muted">Still open</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Added</th>
                                        <td>{{ $deal->created_at ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated</th>
                                        <td>{{ $deal->updated_at ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-12 col-md-6">
                            <h5 class="text-capitalize mb-3">Linked To</h5>

                            <table class="table table-bordered t-text-dark--light-15 sm-text mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th scope="row" class="w-25">Company</th>
                                        <td>
                                            @if ($deal->company)
                                                <a href="{{ route('admin.companies.show', $deal->company->id) }}"
                                                    class="t-link t-link--alpha">
                                                    {{ $deal->company->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Contact</th>
                                        <td>
                                            @if ($deal->contact)
                                                <a href="{{ route('admin.contacts.show', $deal->contact->id) }}"
                                                    class="t-link t-link--alpha">
                                                    {{ $deal->contact->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-12">
                            <h5 class="text-capitalize mb-3">Activities</h5>

                            <div class="table-responsive">
                                <table class="table table-bordered t-text-dark--light-15 sm-text align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="text-center">#</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Subject</th>
                                            <th scope="col">Due</th>
                                            <th scope="col">Completed</th>
                                            <th scope="col">Logged By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($deal->activities as $activity)
                                            <tr>
                                                <th scope="row" class="text-center font-monospace text-muted">
                                                    {{ $activity->id }}
                                                </th>
                                                <td class="text-capitalize">{{ $activity->type }}</td>
                                                <td class="fw-semibold">{{ $activity->subject }}</td>
                                                <td class="text-nowrap">{{ $activity->due_date ?? '—' }}</td>
                                                <td class="text-nowrap">{{ $activity->completed_at ?? '—' }}</td>
                                                <td>{{ $activity->user_name ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="las la-message-detail display-6 text-muted d-block mb-2"></i>
                                                    <p class="mb-0 text-muted">No activities logged for this deal.</p>
                                                </td>
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
