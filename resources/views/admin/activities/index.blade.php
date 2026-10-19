@extends('layouts.master')

@section('title', 'Activities')

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
                            <span class="badge bg-primary text-uppercase sm-text">activities table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Activity List</h3>
                            <p class="mb-0 text-muted sm-text">Calls, meetings, emails, and tasks logged against your records.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge rounded-pill bg-light text-dark sm-text">
                                {{ $pagination->total }} total
                            </span>
                            <a href="{{ route('admin.activities.create') }}" class="btn btn-primary sm-text text-uppercase">
                                log activity
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col" class="text-center">Type</th>
                                    <th scope="col">Attached To</th>
                                    <th scope="col">Assigned</th>
                                    <th scope="col">Due Date</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activities as $activity)
                                    @php
                                        // Bootstrap 5.0 has no text-bg-* helpers (they
                                        // arrived in 5.1), so each badge pairs a bg-*
                                        // with a text colour that stays readable on it.
                                        $typeStyles = [
                                            'call' => 'bg-primary text-white',
                                            'meeting' => 'bg-info text-dark',
                                            'email' => 'bg-warning text-dark',
                                            'task' => 'bg-secondary text-white',
                                        ];

                                        // A row may hold either the morph alias or a
                                        // legacy class name, so the alias is resolved
                                        // to its model and reduced to a short label.
                                        $trackableLabel = class_basename(
                                            \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($activity->trackable_type)
                                                ?? $activity->trackable_type
                                        );

                                        // Both relations are only present when eager
                                        // loaded, so the name is read defensively and
                                        // the type/id stands in when it is missing.
                                        $trackableName = $activity->trackable['name'] ?? null;
                                        $assignedName = $activity->user['name'] ?? null;
                                    @endphp
                                    <tr>
                                        <th scope="row" class="text-center font-monospace text-muted">{{ $activity->id }}</th>
                                        <td>
                                            <div class="fw-semibold">{{ $activity->subject }}</div>
                                            @if ($activity->description)
                                                <div class="text-muted">{{ Str::limit($activity->description, 60) }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $typeStyles[$activity->type] ?? 'bg-secondary text-white' }}">
                                                {{ ucfirst($activity->type) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($trackableName)
                                                <div class="text-capitalize fw-semibold">{{ $trackableName }}</div>
                                                <div class="text-muted">{{ $trackableLabel }} #{{ $activity->trackable_id }}</div>
                                            @else
                                                <span class="text-capitalize">{{ $trackableLabel }} #{{ $activity->trackable_id }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($assignedName)
                                                {{ $assignedName }}
                                            @elseif ($activity->user_id)
                                                <span class="text-muted">User #{{ $activity->user_id }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($activity->due_date)
                                                {{-- due_date arrives as an ISO-8601 string. The
                                                     datetime attribute keeps the exact instant
                                                     machine-readable while the label shows a
                                                     date a person can read. --}}
                                                <time datetime="{{ $activity->due_date }}">
                                                    {{ \Illuminate\Support\Carbon::parse($activity->due_date)->format('M d, Y h:i A') }}
                                                </time>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($activity->completed_at)
                                                <span class="badge bg-success text-white">Completed</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Open</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{-- GUARD: the admin ActivityController only
                                                 implements index, create and store, so show,
                                                 edit and destroy have no controller method yet.
                                                 These stay inert rather than pointing at a route
                                                 that would fail. --}}
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
                                            <i class="las la-tasks display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted">No activities found.</p>
                                            <a href="{{ route('admin.activities.create') }}"
                                               class="btn btn-primary sm-text text-uppercase mt-3">
                                                log your first activity
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
                                ({{ $pagination->total }} {{ Str::plural('activity', $pagination->total) }})
                            </p>
                            <div class="d-flex align-items-center gap-2">
                                @if ($pagination->currentPage > 1)
                                    {{-- The controller filters the list but never hands the
                                         criteria to the view, so the current query string is
                                         carried forward to keep the active criteria intact
                                         across a page change. --}}
                                    <a href="{{ route('admin.activities.index', array_merge(request()->query(), ['page' => $pagination->currentPage - 1])) }}"
                                       class="btn btn-transparent btn-primary sm-text text-uppercase">
                                        <i class="las la-arrow-left t-mr-5"></i>prev
                                    </a>
                                @endif
                                @if ($pagination->currentPage < $pagination->lastPage)
                                    <a href="{{ route('admin.activities.index', array_merge(request()->query(), ['page' => $pagination->currentPage + 1])) }}"
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