<div class="col-12 d-xl-none">
    <header class="mx-mobile-header t-bg-primary ">
        <div class="container-fluid px-3">
            <div class="row g-3">
                <div class="col-6 align-self-center">
                    <div class="mx-mobile-brand">
                        <a href="{{ route('dashboard') }}" class="t-link">
                            <img src="assets/img/logo-w.png" alt="Adminage"
                                class="img-fluid mx-mobile-brand__is">
                        </a>
                    </div>
                </div>
                <div class="col-6 align-self-center">
                    <nav class="mx-mobile-option">
                        <ul class="t-list mx-mobile-menu justify-content-end">
                            <li class="mx-mobile-menu__item">
                                <a href="#" class="mx-mobile-menu__link mx-sm-sidenav-toggle">
                                    <i class='bx bx-menu-alt-right d-block'></i>
                                </a>
                            </li>
                            <li class="mx-mobile-menu__item mx-mobile-menu__sub-header">
                                <a href="#" class="mx-mobile-menu__link">
                                    <i class='bx bx-menu d-block'></i>
                                </a>
                            </li>
                            <li class="mx-mobile-menu__item mx-mobile-menu__user">
                                <a href="#" class="mx-mobile-menu__link">
                                    <i class='bx bxs-user-circle d-block'></i>
                                </a>
                            </li>
                        </ul>
                        <div class="mx-sub-header">
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-12">
                                        <ul class="t-list mx-sm-sidenav">
                                            <li class="mx-sm-sidenav__list">
                                                <form action="#" class="t-pl-15 t-pr-15 t-pt-15 t-pb-10">
                                                    <div class="mx-search-form">
                                                        <div class="mx-search-form__icon">
                                                            <i class='bx bx-search'></i>
                                                        </div>
                                                        <input type="text" class="mx-search-form__input"
                                                            placeholder="Search...">
                                                    </div>
                                                </form>
                                            </li>
                                            <li class="mx-sm-sidenav__list">
                                                <a href="{{ route('dashboard') }}"
                                                    class="mx-sm-sidenav__link @if (request()->routeIs('dashboard')) active-page @endif">
                                                    <span class="mx-sm-sidenav__icon t-mr-8">
                                                        <span class='bx bx-category'></span>
                                                    </span>
                                                    <span class="mx-sm-sidenav__text text-capitalize">
                                                        dashboard
                                                    </span>
                                                </a>
                                            </li>

                                            <li class="mx-sm-sidenav__list mx-sm-sidenav__has-sub @if (request()->routeIs('admin.leads.*')) active @endif">
                                                <a href="#" class="mx-sm-sidenav__link">
                                                    <span class="mx-sm-sidenav__icon t-mr-8">
                                                        <i class='bx bxs-folder-open'></i>
                                                    </span>
                                                    <span class="mx-sm-sidenav__text text-capitalize">
                                                        Leads
                                                    </span>
                                                </a>
                                                {{-- The theme hides the submenu until its parent is clicked, so
                                                     the branch holding the current page is opened on load. --}}
                                                <ul class="t-list mx-sm-sidenav__sub @if (! request()->routeIs('admin.leads.*')) mx-sm-sidenav__sub--hide @endif">
                                                    <li class="mx-sm-sidenav__sub-list">
                                                        <a href="{{ route('admin.leads.create') }}"
                                                            class="mx-sm-sidenav__sub-link @if (request()->routeIs('admin.leads.create')) active-page @endif">
                                                            <span class="mx-sm-sidenav__sub-icon t-mr-8">
                                                                <span class='bx bx-pulse'></span>
                                                            </span>
                                                            <span class="mx-sm-sidenav__sub-text text-capitalize">
                                                                Create Lead
                                                            </span>
                                                        </a>
                                                    </li>
                                                    <li class="mx-sm-sidenav__sub-list">
                                                        <a href="{{ route('admin.leads.index') }}"
                                                            class="mx-sm-sidenav__sub-link @if (request()->routeIs('admin.leads.index')) active-page @endif">
                                                            <span class="mx-sm-sidenav__sub-icon t-mr-8">
                                                                <span class='bx bx-pulse'></span>
                                                            </span>
                                                            <span class="mx-sm-sidenav__sub-text text-capitalize">
                                                                Leads list
                                                            </span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </li>

                                            <li class="mx-sm-sidenav__list mx-sm-sidenav__has-sub @if (request()->routeIs('admin.companies.*')) active @endif">
                                                <a href="#" class="mx-sm-sidenav__link">
                                                    <span class="mx-sm-sidenav__icon t-mr-8">
                                                        <i class='bx bxs-buildings'></i>
                                                    </span>
                                                    <span class="mx-sm-sidenav__text text-capitalize">
                                                        Companies
                                                    </span>
                                                </a>
                                                {{-- Opened on load when the current page is in this branch. --}}
                                                <ul class="t-list mx-sm-sidenav__sub @if (! request()->routeIs('admin.companies.*')) mx-sm-sidenav__sub--hide @endif">
                                                    <li class="mx-sm-sidenav__sub-list">
                                                        <a href="{{ route('admin.companies.create') }}"
                                                            class="mx-sm-sidenav__sub-link @if (request()->routeIs('admin.companies.create')) active-page @endif">
                                                            <span class="mx-sm-sidenav__sub-icon t-mr-8">
                                                                <span class='bx bx-plus-circle'></span>
                                                            </span>
                                                            <span class="mx-sm-sidenav__sub-text text-capitalize">
                                                                Create Company
                                                            </span>
                                                        </a>
                                                    </li>
                                                    <li class="mx-sm-sidenav__sub-list">
                                                        <a href="{{ route('admin.companies.index') }}"
                                                            class="mx-sm-sidenav__sub-link @if (request()->routeIs('admin.companies.index')) active-page @endif">
                                                            <span class="mx-sm-sidenav__sub-icon t-mr-8">
                                                                <i class='bx bx-list-ul'></i>
                                                            </span>
                                                            <span class="mx-sm-sidenav__sub-text text-capitalize">
                                                                Companies List
                                                            </span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </li>

                                            <li class="mx-sm-sidenav__list mx-sm-sidenav__has-sub @if (request()->routeIs('admin.activities.*')) active @endif">
                                                <a href="#" class="mx-sm-sidenav__link">
                                                    <span class="mx-sm-sidenav__icon t-mr-8">
                                                        <i class='bx bx-message-detail'></i>
                                                    </span>
                                                    <span class="mx-sm-sidenav__text text-capitalize">
                                                        Activity
                                                    </span>
                                                </a>
                                                {{-- Opened on load when the current page is in this branch. --}}
                                                <ul class="t-list mx-sm-sidenav__sub @if (! request()->routeIs('admin.activities.*')) mx-sm-sidenav__sub--hide @endif">
                                                    <li class="mx-sm-sidenav__sub-list">
                                                        <a href="{{ route('admin.activities.create') }}"
                                                            class="mx-sm-sidenav__sub-link @if (request()->routeIs('admin.activities.create')) active-page @endif">
                                                            <span class="mx-sm-sidenav__sub-icon t-mr-8">
                                                                <i class='bx bx-message-dots'></i>
                                                            </span>
                                                            <span class="mx-sm-sidenav__sub-text text-capitalize">
                                                                Create Activity
                                                            </span>
                                                        </a>
                                                    </li>
                                                    <li class="mx-sm-sidenav__sub-list">
                                                        <a href="{{ route('admin.activities.index') }}"
                                                            class="mx-sm-sidenav__sub-link @if (request()->routeIs('admin.activities.index')) active-page @endif">
                                                            <span class="mx-sm-sidenav__sub-icon t-mr-8">
                                                                <i class='bx bxs-message-dots'></i>
                                                            </span>
                                                            <span class="mx-sm-sidenav__sub-text text-capitalize">
                                                                Activities List
                                                            </span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mx-sm-user">
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-12">
                                        <ul class="t-list mx-sm-sidenav">
                                            <li class="mx-sm-sidenav__list">
                                                <a href="#" class="mx-sm-sidenav__link flex-column align-items-start">
                                                    <span class="text-capitalize fw-bold d-block t-pt-5">
                                                        {{ auth()->user()?->name ?? 'Guest' }}
                                                    </span>
                                                    <span class="text-capitalize xsm-text d-block mt-1">
                                                        {{ auth()->user()?->email }}
                                                    </span>
                                                </a>
                                                <hr class="mx-hr mx-hr--primary">
                                            </li>
                                            {{-- logout is a POST route, so it needs a form rather than
                                                 a plain link. --}}
                                            <li class="mx-sm-sidenav__list">
                                                <form action="{{ route('logout') }}" method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="mx-sm-sidenav__link border-0 bg-transparent w-100 text-start">
                                                        <span class="mx-sm-sidenav__icon t-mr-8">
                                                            <i class='bx bx-log-out-circle'></i>
                                                        </span>
                                                        <span class="mx-sm-sidenav__text text-capitalize">
                                                            log out
                                                        </span>
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </div>

    </header>
</div>
