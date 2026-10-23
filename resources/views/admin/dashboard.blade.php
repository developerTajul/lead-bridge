@extends('layouts.master')

@section('title', 'Dashboard')

@php
    // The controller hands a null metrics object over when the read failed, so
    // the page still renders its navigation and an error rather than a blank.
    $money = fn($value) => number_format((float) $value, 2);
@endphp

@section('content')
    <div class="container-fluid px-3">
        <div class="row g-3">
            <div class="col-12">
                <div class="t-shadow t-bg-white p-4">

                    @if (!empty($error))
                        <div class="alert alert-danger d-flex align-items-start gap-2 sm-text" role="alert">
                            <i class="las la-exclamation-triangle xlg-text"></i>
                            <span>{{ $error }}</span>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-primary text-uppercase sm-text">overview</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Dashboard</h3>
                            <p class="mb-0 text-muted sm-text">Where the pipeline stands, and what needs doing next.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.deals.index') }}" class="btn btn-light sm-text text-uppercase">
                                all deals
                            </a>
                            <a href="{{ route('admin.leads.index') }}" class="btn btn-light sm-text text-uppercase">
                                all leads
                            </a>
                        </div>
                    </div>

                    @if ($metrics === null)
                        <div class="text-center py-5">
                            <i class="las la-chart-bar display-6 text-muted d-block mb-2"></i>
                            <p class="mb-0 text-muted">The figures could not be loaded.</p>
                        </div>
                    @else
                        {{-- Headline figures. Each tile states the figure and the panel
                             it came from, so a number is never read without its scope. --}}
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="t-bg-16 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="text-muted text-uppercase xsm-text">Won revenue</span>
                                        <span class="xlg-text text-success"><i class="las la-trophy"></i></span>
                                    </div>
                                    <h4 class="mb-1">{{ $money($metrics->wonRevenue) }}</h4>
                                    {{-- The totals span every pipeline, so they are only
                                         meaningful under the one currency they are summed in. --}}
                                    <p class="mb-0 text-muted xsm-text">
                                        {{ $metrics->currency }} · {{ $metrics->wonDeals }}
                                        {{ Str::plural('deal', $metrics->wonDeals) }} won
                                    </p>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="t-bg-16 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="text-muted text-uppercase xsm-text">Open pipeline</span>
                                        <span class="xlg-text text-primary"><i class="las la-funnel-dollar"></i></span>
                                    </div>
                                    <h4 class="mb-1">{{ $money($metrics->openPipelineValue) }}</h4>
                                    <p class="mb-0 text-muted xsm-text">
                                        {{ $metrics->currency }} · {{ $metrics->openDeals }}
                                        {{ Str::plural('deal', $metrics->openDeals) }} still in play
                                    </p>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="t-bg-16 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="text-muted text-uppercase xsm-text">Win rate</span>
                                        <span class="xlg-text text-info"><i class="las la-percentage"></i></span>
                                    </div>
                                    <h4 class="mb-1">{{ $metrics->winRate }}%</h4>
                                    <p class="mb-0 text-muted xsm-text">
                                        {{ $metrics->wonDeals }} won of
                                        {{ $metrics->wonDeals + $metrics->lostDeals }} closed
                                    </p>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="t-bg-16 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="text-muted text-uppercase xsm-text">Pending activities</span>
                                        <span class="xlg-text text-warning"><i class="las la-tasks"></i></span>
                                    </div>
                                    <h4 class="mb-1">{{ $metrics->pendingActivities }}</h4>
                                    <p class="mb-0 text-muted xsm-text">
                                        {{ $metrics->completedActivities }} completed
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            {{-- Pipeline overview --}}
                            <div class="col-12 col-xl-7">
                                <div class="h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge bg-primary text-uppercase sm-text">pipeline</span>
                                            <h6 class="mt-2 mb-0 text-capitalize">Deals by stage</h6>
                                        </div>
                                        <span class="badge rounded-pill bg-light text-dark sm-text">
                                            {{ count($stages) }} {{ Str::plural('stage', count($stages)) }}
                                        </span>
                                    </div>

                                    @php
                                        // Each bar is drawn against the busiest stage rather
                                        // than against the total, so a quiet stage stays
                                        // visible instead of collapsing to nothing. Guarded
                                        // because an empty board would divide by zero.
                                        $busiestStage = collect($stages)->max('dealsCount') ?? 0;
                                    @endphp

                                    <div class="table-responsive">
                                        <table class="table table-borderless t-text-dark--light-15 sm-text align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Stage</th>
                                                    <th scope="col" class="text-center">Deals</th>
                                                    <th scope="col" class="text-end">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($stages as $stage)
                                                    <tr>
                                                        <td>
                                                            {{-- The same stage name exists under every
                                                                 pipeline, so the pipeline is named
                                                                 beside it to tell the rows apart. --}}
                                                            <div class="fw-semibold">{{ $stage->name }}</div>
                                                            <div class="text-muted xsm-text">
                                                                {{ $stage->pipelineName }} · {{ $stage->probability }}% odds
                                                            </div>
                                                            <div class="progress mt-2" style="height: 4px;">
                                                                <div class="progress-bar bg-primary" role="progressbar"
                                                                    style="width: {{ $busiestStage > 0 ? round(($stage->dealsCount / $busiestStage) * 100) : 0 }}%"
                                                                    aria-valuenow="{{ $stage->dealsCount }}"
                                                                    aria-valuemin="0"
                                                                    aria-valuemax="{{ $busiestStage }}"
                                                                    aria-label="{{ $stage->dealsCount }} deals in {{ $stage->name }}"></div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-{{ $stage->dealsCount > 0 ? 'primary' : 'light' }} {{ $stage->dealsCount > 0 ? '' : 'text-dark' }} sm-text">
                                                                {{ $stage->dealsCount }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end text-nowrap">{{ $money($stage->totalValue) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center py-5">
                                                            <i class="las la-columns display-6 text-muted d-block mb-2"></i>
                                                            <p class="mb-0 text-muted">No stages to report on yet.</p>
                                                            <a href="{{ route('admin.pipelines.index') }}"
                                                                class="btn btn-primary sm-text text-uppercase mt-3">
                                                                set up a pipeline
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- Activity feed and the lead panel --}}
                            <div class="col-12 col-xl-5">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge bg-primary text-uppercase sm-text">next up</span>
                                            <h6 class="mt-2 mb-0 text-capitalize">Upcoming activities</h6>
                                        </div>
                                        <a href="{{ route('admin.activities.index') }}"
                                            class="t-link t-link--alpha sm-text text-capitalize">view all</a>
                                    </div>

                                    @forelse ($upcomingActivities as $activity)
                                        <div class="t-bg-16 p-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <span class="fw-semibold sm-text">{{ $activity->subject }}</span>
                                                <span class="badge rounded-pill bg-light text-dark xsm-text text-nowrap">
                                                    {{ $activity->type }}
                                                </span>
                                            </div>
                                            <div class="text-muted xsm-text mt-1">
                                                <i class="las la-calendar t-mr-5"></i>
                                                {{ $activity->due_date
                                                    ? \Illuminate\Support\Carbon::parse($activity->due_date)->format('d M Y, H:i')
                                                    : 'No due date' }}
                                                @if ($activity->trackable_name)
                                                    · on {{ $activity->trackable_type }}:
                                                    {{ $activity->trackable_name }}
                                                @endif
                                                @if ($activity->user_name)
                                                    · {{ $activity->user_name }}
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-4">
                                            <i class="las la-check-circle display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted xsm-text">Nothing outstanding.</p>
                                        </div>
                                    @endforelse
                                </div>

                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge bg-primary text-uppercase sm-text">leads</span>
                                            <h6 class="mt-2 mb-0 text-capitalize">Lead conversion</h6>
                                        </div>
                                        <span class="badge rounded-pill bg-light text-dark sm-text">
                                            {{ $metrics->totalLeads }} total
                                        </span>
                                    </div>

                                    <div class="t-bg-16 p-3">
                                        <div class="d-flex justify-content-between align-items-end mb-2">
                                            <div>
                                                <h4 class="mb-0">{{ $metrics->conversionRate }}%</h4>
                                                <span class="text-muted xsm-text">
                                                    {{ $metrics->convertedLeads }} of
                                                    {{ $metrics->totalLeads }} converted
                                                </span>
                                            </div>
                                            <span class="xlg-text text-success">
                                                <i class="las la-handshake"></i>
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                style="width: {{ min($metrics->conversionRate, 100) }}%"
                                                aria-valuenow="{{ $metrics->conversionRate }}"
                                                aria-valuemin="0" aria-valuemax="100"
                                                aria-label="Lead conversion rate"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Secondary row: the deal counts by state, and the split of
                             everything captured. Kept quieter than the tiles above. --}}
                        <div class="row g-3 mt-1">
                            <div class="col-12 col-lg-6">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="text-muted text-uppercase xsm-text">Deals</span>
                                    <span class="badge rounded-pill bg-light text-dark sm-text">
                                        {{ $metrics->totalDeals }} total
                                    </span>
                                    <span class="badge rounded-pill bg-success sm-text">
                                        {{ $metrics->wonDeals }} won
                                    </span>
                                    <span class="badge rounded-pill bg-danger sm-text">
                                        {{ $metrics->lostDeals }} lost
                                    </span>
                                    <span class="badge rounded-pill bg-primary sm-text">
                                        {{ $metrics->openDeals }} open
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="text-muted text-uppercase xsm-text">Activities</span>
                                    <span class="badge rounded-pill bg-light text-dark sm-text">
                                        {{ $metrics->pendingActivities + $metrics->completedActivities }} total
                                    </span>
                                    <span class="badge rounded-pill bg-warning sm-text">
                                        {{ $metrics->pendingActivities }} pending
                                    </span>
                                    <span class="badge rounded-pill bg-success sm-text">
                                        {{ $metrics->completedActivities }} completed
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection
