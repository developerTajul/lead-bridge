@extends('layouts.master')

@section('title', 'Contact')

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
                            <span class="badge bg-primary text-uppercase sm-text">contact #{{ $contact->id }}</span>
                            <h3 class="mt-2 mb-1 text-capitalize">{{ $contact->name }}</h3>
                            <p class="mb-0 text-muted sm-text">The person and everything attached to them.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.contacts.edit', $contact->id) }}"
                                class="btn btn-primary sm-text text-uppercase">edit</a>
                            <a href="{{ route('admin.contacts.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <table class="table table-bordered t-text-dark--light-15 sm-text mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th scope="row" class="w-25">Email</th>
                                        <td>
                                            <a href="mailto:{{ $contact->email }}" class="t-link t-link--alpha">
                                                {{ $contact->email }}
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Phone</th>
                                        <td>{{ $contact->phone ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Added</th>
                                        <td>{{ $contact->created_at ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated</th>
                                        <td>{{ $contact->updated_at ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-12 col-md-6">
                            <h5 class="text-capitalize mb-3">Companies</h5>

                            <div class="table-responsive">
                                <table class="table table-bordered t-text-dark--light-15 sm-text align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Company</th>
                                            <th scope="col">Job Title</th>
                                            <th scope="col" class="text-center">Primary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($contact->companies as $company)
                                            <tr>
                                                <td class="fw-semibold">{{ $company->name }}</td>
                                                <td>
                                                    @if ($company->job_title)
                                                        {{ $company->job_title }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($company->is_primary)
                                                        <span class="badge bg-primary sm-text">yes</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4">
                                                    <i class="las la-building display-6 text-muted d-block mb-2"></i>
                                                    <p class="mb-0 text-muted">Not linked to a company yet.</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
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
                                        @forelse ($contact->activities as $activity)
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
                                                    <p class="mb-0 text-muted">No activities logged for this contact.</p>
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
