<div class="position-relative d-xl-none">
    <div class="mx-mobile-nav-wrapper"></div>
    <div class="mx-mobile-nav">
        <div class="mx-mobile-nav__close">
            <i class="las la-times"></i>
        </div>
        <div class="mx-mobile-brand mx-mobile-nav__brand ">
            <img src="assets/img/logo-w.png" alt="Adminage" class="img-fluid mx-mobile-brand__is">
        </div>
        <nav class="mx-mobile-nav__menu" data-simplebar>
            <ul class="t-list mx-sm-sidenav">
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
                    {{-- The theme hides the submenu until its parent is clicked, so the
                         branch holding the current page is opened on load. --}}
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
        </nav>
    </div>
</div>
