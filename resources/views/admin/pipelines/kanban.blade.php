@extends('layouts.master')

@section('title', 'Pipeline Board')

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
                            <span class="badge bg-primary text-uppercase sm-text">board · pipeline #{{ $board->id }}</span>
                            <h3 class="mt-2 mb-1 text-capitalize">{{ $board->name }}</h3>
                            <p class="mb-0 text-muted sm-text">Every stage, and the deals sitting in each.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.pipelines.show', $board->id) }}"
                                class="btn btn-light sm-text text-uppercase">view</a>
                            <a href="{{ route('admin.pipelines.edit', $board->id) }}"
                                class="btn btn-light sm-text text-uppercase">edit</a>
                            <a href="{{ route('admin.pipelines.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    @if ($board->is_default)
                        <div class="mb-3">
                            <span class="badge bg-primary sm-text">default pipeline</span>
                        </div>
                    @endif

                    <div class="row g-3 flex-nowrap overflow-auto pb-2">
                        @forelse ($board->stages as $stage)
                            <div class="col-12 col-md-6 col-xl-3" style="min-width: 17rem;">
                                <div class="t-bg-16 p-3 h-100">

                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="text-muted font-monospace xsm-text">
                                                {{ $stage->order_position }}
                                            </span>
                                            <h6 class="mb-0 text-capitalize">{{ $stage->name }}</h6>
                                        </div>
                                        <span class="badge rounded-pill bg-light text-dark sm-text">
                                            {{ $stage->probability }}%
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary sm-text">
                                            {{ $stage->deals_count }}
                                            {{ Str::plural('deal', $stage->deals_count) }}
                                        </span>
                                        <span class="sm-text fw-semibold">
                                            {{ number_format($stage->total_value, 2) }}
                                        </span>
                                    </div>

                                    @forelse ($stage->deals as $deal)
                                        <div class="t-bg-white t-shadow p-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <span class="fw-semibold sm-text">{{ $deal->title }}</span>
                                                <span class="text-nowrap sm-text">
                                                    {{ $deal->amount }} {{ $deal->currency }}
                                                </span>
                                            </div>

                                            {{-- The related records are narrowed to a name at the
                                                 query, so a deal with neither shows nothing rather
                                                 than an empty row. --}}
                                            @if ($deal->company || $deal->contact)
                                                <div class="mt-2">
                                                    @if ($deal->company)
                                                        <span class="badge rounded-pill bg-light text-dark xsm-text">
                                                            <i class="las la-building t-mr-5"></i>{{ $deal->company['name'] }}
                                                        </span>
                                                    @endif
                                                    @if ($deal->contact)
                                                        <span class="badge rounded-pill bg-light text-dark xsm-text">
                                                            <i class="las la-user t-mr-5"></i>{{ $deal->contact['name'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif

                                            @if ($deal->expected_close_date)
                                                <div class="text-muted xsm-text mt-2">
                                                    <i class="las la-calendar t-mr-5"></i>
                                                    expected {{ $deal->expected_close_date }}
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-center py-4">
                                            <i class="las la-inbox display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted xsm-text">No deals in this stage.</p>
                                        </div>
                                    @endforelse

                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="las la-columns display-6 text-muted d-block mb-2"></i>
                                    <p class="mb-0 text-muted">This pipeline has no stages to show.</p>
                                    <a href="{{ route('admin.pipelines.edit', $board->id) }}"
                                        class="btn btn-primary sm-text text-uppercase mt-3">
                                        add stages
                                    </a>
                                </div>
                            </div>
                        @endforelse
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
