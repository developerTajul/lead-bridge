@extends('layouts.master')

@section('title', 'Pipeline')

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
                            <span class="badge bg-primary text-uppercase sm-text">pipeline #{{ $pipeline->id }}</span>
                            <h3 class="mt-2 mb-1 text-capitalize">{{ $pipeline->name }}</h3>
                            <p class="mb-0 text-muted sm-text">The pipeline and the stages a deal passes through.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <a href="{{ route('admin.pipelines.kanban', $pipeline->id) }}"
                                class="btn btn-light sm-text text-uppercase">board</a>
                            <a href="{{ route('admin.pipelines.edit', $pipeline->id) }}"
                                class="btn btn-primary sm-text text-uppercase">edit</a>
                            <a href="{{ route('admin.pipelines.index') }}" class="btn btn-light sm-text text-uppercase">
                                back to list
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <table class="table table-bordered t-text-dark--light-15 sm-text mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th scope="row" class="w-25">Name</th>
                                        <td class="fw-semibold">{{ $pipeline->name }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Default</th>
                                        <td>
                                            @if ($pipeline->is_default)
                                                <span class="badge bg-primary sm-text">yes</span>
                                            @else
                                                <span class="text-muted">no</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Stages</th>
                                        <td>{{ count($pipeline->stages) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Added</th>
                                        <td>{{ $pipeline->created_at ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated</th>
                                        <td>{{ $pipeline->updated_at ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-12 col-md-6">
                            <h5 class="text-capitalize mb-3">How to read this</h5>
                            <ul class="t-list">
                                <li class="mb-2 sm-text">
                                    <span class="fw-semibold">Position</span> orders the stages, and no two may share
                                    one.
                                </li>
                                <li class="mb-2 sm-text">
                                    <span class="fw-semibold">Probability</span> is the odds a deal in this stage is
                                    won, from 0 to 100.
                                </li>
                                <li class="mb-2 sm-text">
                                    Saving the edit form replaces the stage set, so a removed stage is not kept
                                    anywhere.
                                </li>
                            </ul>
                        </div>

                        <div class="col-12">
                            <h5 class="text-capitalize mb-3">Stages</h5>

                            <div class="table-responsive">
                                <table class="table table-bordered t-text-dark--light-15 sm-text align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="text-center">#</th>
                                            <th scope="col">Stage</th>
                                            <th scope="col" class="text-center">Probability</th>
                                            <th scope="col" class="text-center">Added</th>
                                            <th scope="col" class="text-center">Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($pipeline->stages as $stage)
                                            <tr>
                                                <th scope="row" class="text-center font-monospace text-muted">
                                                    {{ $stage->order_position }}
                                                </th>
                                                <td class="fw-semibold">{{ $stage->name }}</td>
                                                <td class="text-center">
                                                    <span class="badge rounded-pill bg-light text-dark sm-text">
                                                        {{ $stage->probability }}%
                                                    </span>
                                                </td>
                                                <td class="text-center text-nowrap">{{ $stage->created_at ?? '—' }}</td>
                                                <td class="text-center text-nowrap">{{ $stage->updated_at ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <i class="las la-stream display-6 text-muted d-block mb-2"></i>
                                                    <p class="mb-0 text-muted">This pipeline has no stages.</p>
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
