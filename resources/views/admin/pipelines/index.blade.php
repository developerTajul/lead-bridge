@extends('layouts.master')

@section('title', 'Pipelines')

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

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-primary text-uppercase sm-text">pipelines table</span>
                            <h3 class="mt-2 mb-1 text-capitalize">Pipeline List</h3>
                            <p class="mb-0 text-muted sm-text">The stages your deals move through, in order.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-3 mt-sm-0">
                            <span class="badge rounded-pill bg-light text-dark sm-text">
                                {{ count($pipelines) }} total
                            </span>
                            <a href="{{ route('admin.pipelines.create') }}" class="btn btn-primary sm-text text-uppercase">
                                add pipeline
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered t-text-dark--light-15 sm-text align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col" class="text-center">Default</th>
                                    <th scope="col">Stages</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pipelines as $pipeline)
                                    <tr>
                                        <th scope="row" class="text-center font-monospace text-muted">{{ $pipeline->id }}</th>
                                        <td class="fw-semibold">{{ $pipeline->name }}</td>
                                        <td class="text-center">
                                            @if ($pipeline->is_default)
                                                <span class="badge bg-primary sm-text">yes</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{-- The stages read in the order they are worked
                                                 through, each showing the odds it carries. --}}
                                            @forelse ($pipeline->stages as $stage)
                                                <span class="badge rounded-pill bg-light text-dark sm-text">
                                                    {{ $stage->order_position }}. {{ $stage->name }}
                                                    · {{ $stage->probability }}%
                                                </span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            <ul class="t-list d-flex justify-content-center mb-0">
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.pipelines.kanban', $pipeline->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-columns"></i></span>
                                                            <span class="text-capitalize">board</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.pipelines.show', $pipeline->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-eye"></i></span>
                                                            <span class="text-capitalize">view</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    <a href="{{ route('admin.pipelines.edit', $pipeline->id) }}"
                                                        class="t-link t-link--alpha">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-cog"></i></span>
                                                            <span class="text-capitalize">edit</span>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="t-list__item">
                                                    {{-- There is no delete path on the write side yet, so
                                                         the row offers no destructive action rather
                                                         than a button wired to nothing. Stages and
                                                         deals both hang off a pipeline, so removing
                                                         one is a decision that needs its own rules. --}}
                                                    <span class="t-link text-muted" title="Deleting a pipeline is not supported yet.">
                                                        <span class="d-flex align-items-center">
                                                            <span class="xlg-text t-mr-5"><i class="las la-trash"></i></span>
                                                            <span class="text-capitalize">delete</span>
                                                        </span>
                                                    </span>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <i class="las la-columns display-6 text-muted d-block mb-2"></i>
                                            <p class="mb-0 text-muted">No pipelines found.</p>
                                            <a href="{{ route('admin.pipelines.create') }}"
                                                class="btn btn-primary sm-text text-uppercase mt-3">
                                                add your first pipeline
                                            </a>
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
@endsection
