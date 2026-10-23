@extends('layouts.master')

@section('title', 'Add Deal')

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
                            <span class="badge bg-primary text-uppercase sm-text">deals table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Add Deal</h3>
                            <p class="mb-0 text-muted sm-text">A title, an amount and the stage it sits in are enough
                                to start.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.deals.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <form class="row g-3" action="{{ route('admin.deals.store') }}" method="POST">
                        @csrf

                        @include('admin.deals.partials.form')

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.deals.index') }}"
                                class="btn btn-light sm-text text-uppercase btn-min-width">cancel</a>
                            <button type="submit" class="btn btn-primary sm-text text-uppercase btn-min-width">
                                save deal
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
