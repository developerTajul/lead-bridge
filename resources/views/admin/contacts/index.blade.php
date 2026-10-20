@extends('layouts.master')

@section('title', 'Contacts')

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
                            <span class="badge bg-primary text-uppercase sm-text">contacts table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Contact List</h3>
                            <p class="mb-0 text-muted sm-text">The people your deals and activities run through.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge rounded-pill bg-light text-dark sm-text">
                                {{ $pagination->total }} total
                            </span>
                            <a href="{{ route('admin.contacts.create') }}" class="btn btn-primary sm-text text-uppercase">
                                add contact
                            </a>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.contacts.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-md-6">
                            <label for="search" class="form-label text-capitalize sm-text">Search</label>
                            <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                                placeholder="Name, email or phone" class="form-control sm-text">
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="per_page" class="form-label text-capitalize sm-text">Per Page</label>
                            <input type="number" id="per_page" name="per_page"
                                value="{{ $filters['per_page'] ?? '' }}" min="1"
                                max="{{ \Core\Contact\Domain\ContactRules::MAX_PER_PAGE }}" class="form-control sm-text">
                        </div>
                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary sm-text text-uppercase">filter</button>
                            <a href="{{ route('admin.contacts.index') }}"
                                class="btn btn-light sm-text text-uppercase">reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Phone</th>
                                    <th scope="col">Companies</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($contacts as $contact)
                                    <tr>
                                        <th scope="row" class="text-center font-monospace text-muted">{{ $contact->id }}</th>
                                        <td class="fw-semibold">{{ $contact->name }}</td>
                                        <td>
                                            <a href="mailto:{{ $contact->email }}" class="t-link t-link--alpha">
                                                {{ $contact->email }}
                                            </a>
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($contact->phone)
                                                {{ $contact->phone }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @forelse ($contact->companies as $company)
                                                <span class="badge rounded-pill bg-light text-dark sm-text">
                                                    {{ $company->name }}
                                                    @if ($company->job_title)
                                                        · {{ $company->job_title }}
                                                    @endif
                                                </span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            <ul class="t-list d-flex justify-content-center mb-0">
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.contacts.show', $contact->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-eye"></i></span>
                                                            <span class="text-capitalize">view</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.contacts.edit', $contact->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-cog"></i></span>
                                                            <span class="text-capitalize">edit</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    {{-- Deletes sit behind a confirmation so a stray click
                                                         cannot remove a person and their history. --}}
                                                    <form action="{{ route('admin.contacts.destroy', $contact->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('Delete this contact?');">
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
                                            <i class="las la-address-book display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted">No contacts found.</p>
                                            <a href="{{ route('admin.contacts.create') }}"
                                                class="btn btn-primary sm-text text-uppercase mt-3">
                                                add your first contact
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
                                ({{ $pagination->total }} {{ Str::plural('contact', $pagination->total) }})
                            </p>
                            <div class="d-flex align-items-center gap-2">
                                @if ($pagination->currentPage > 1)
                                    {{-- The current query string is carried forward so the
                                         active criteria survive a page change. --}}
                                    <a href="{{ route('admin.contacts.index', array_merge(request()->query(), ['page' => $pagination->currentPage - 1])) }}"
                                        class="btn btn-transparent btn-primary sm-text text-uppercase">
                                        <i class="las la-arrow-left t-mr-5"></i>prev
                                    </a>
                                @endif
                                @if ($pagination->currentPage < $pagination->lastPage)
                                    <a href="{{ route('admin.contacts.index', array_merge(request()->query(), ['page' => $pagination->currentPage + 1])) }}"
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
