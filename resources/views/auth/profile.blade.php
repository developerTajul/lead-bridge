@extends('layouts.master')

@section('title', 'Profile')

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
                        <div class="d-flex align-items-center gap-3">
                            <span class="mx-user mx-user--sm d-inline-block">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($profile->name) }}&background=random"
                                    alt="{{ $profile->name }}" class="img-fluid w-100" />
                            </span>
                            <div>
                                <span class="badge bg-primary text-uppercase sm-text">user #{{ $profile->id }}</span>
                                <h3 class="mt-2 mb-1 text-capitalize">{{ $profile->name }}</h3>
                                <p class="mb-0 text-muted sm-text">Your account details.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('dashboard') }}" class="btn btn-light sm-text text-uppercase">
                                back to dashboard
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <table class="table table-bordered t-text-dark--light-15 sm-text mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th scope="row" class="w-25">Name</th>
                                        <td class="fw-semibold">{{ $profile->name }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Email</th>
                                        <td>{{ $profile->email }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Role</th>
                                        <td>
                                            <span class="badge bg-info text-uppercase sm-text">
                                                {{ $profile->role }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Status</th>
                                        <td>
                                            {{-- The domain decides which statuses read as active;
                                                 the view only picks the colour for the value. --}}
                                            @if ($profile->status === 'approved')
                                                <span class="badge bg-success text-uppercase sm-text">
                                                    {{ $profile->status }}
                                                </span>
                                            @else
                                                <span class="badge bg-warning text-uppercase sm-text">
                                                    {{ $profile->status }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
