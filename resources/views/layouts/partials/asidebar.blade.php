<div class="col-3 col-xxl-2 mx-sidenav d-none d-xl-block">
    <!-- Side Nav  -->
    <div class="mx-sidenav__content t-bg-primary">
        <!-- Side Nav Brand  -->
        <div class="mx-sidenav__brand">
            <a href="{{ route('dashboard') }}" class="mx-sidenav__logo t-link">
                <img src="assets/img/logo-w.png" alt="Adminage" class="img-fluid mx-sidenav__logo-is">
            </a>
            <a href="{{ url('#') }}" class="t-link mx-sidenav__logo-sm">
                <img src="assets/img/logo-w-sm.png" alt="Adminage" class="img-fluid mx-sidenav__logo-sm-is">
            </a>
        </div>
        <!-- Side Nav Brand  End-->
        <div class="mx-sidenav__menu-container">
            <nav class="mx-sidenav__menu">
                <ul class="t-list mx-sidenav__menu-is">
                    <li class="mx-sidenav__menu-item @if (request()->routeIs('dashboard')) active-page @endif">
                        <a href="{{ route('dashboard') }}" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <span class='bx bx-category'></span>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                dashboard
                            </span>
                        </a>
                    </li>

                    {{-- No admin user screen is wired up: the admin.users resource is
                         bound to the lead controller, so only its index resolves and
                         that one renders the lead list. Both entries stay inert. --}}
                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <span class='bx bx-user-circle'></span>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                user
                            </span>
                        </a>
                        <ul class="t-list mx-sidenav__sub mx-sidenav__sub--hide">
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ url('#') }}" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bxs-user-plus'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        user
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ url('#') }}" class="mx-sidenav__sub-link ">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bxs-user-detail'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        user list
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="mx-sidenav__menu-item">
                        <a href="{{ url('#') }}" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bxs-user-account'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                profile
                            </span>
                        </a>
                    </li>

                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub @if (request()->routeIs('admin.leads.*')) active @endif">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bxs-folder-open'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Leads
                            </span>
                        </a>
                        {{-- The theme hides the submenu until its parent is clicked, so
                             the branch holding the current page is opened on load. --}}
                        <ul class="t-list mx-sidenav__sub @if (! request()->routeIs('admin.leads.*')) mx-sidenav__sub--hide @endif">
                            <li class="mx-sidenav__sub-item">
                                {{-- There is no lead-create route or form yet, so this
                                     points at the one create form that exists. --}}
                                <a href="{{ route('admin.activities.create') }}" class="mx-sidenav__sub-link @if (request()->routeIs('admin.activities.create')) active-page @endif">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <span class='bx bx-pulse'></span>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Lead
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ route('admin.leads.index') }}" class="mx-sidenav__sub-link @if (request()->routeIs('admin.leads.index')) active-page @endif">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <span class='bx bx-pulse'></span>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Leads list
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub @if (request()->routeIs('admin.companies.*')) active @endif">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bxs-buildings'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Companies
                            </span>
                        </a>
                        {{-- Opened on load when the current page is in this branch. --}}
                        <ul class="t-list mx-sidenav__sub @if (! request()->routeIs('admin.companies.*')) mx-sidenav__sub--hide @endif">
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ route('admin.companies.create') }}" class="mx-sidenav__sub-link @if (request()->routeIs('admin.companies.create')) active-page @endif">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <span class='bx bx-plus-circle'></span>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Company
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ route('admin.companies.index') }}" class="mx-sidenav__sub-link @if (request()->routeIs('admin.companies.index')) active-page @endif">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-list-ul'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Companies List
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub @if (request()->routeIs('admin.activities.*')) active @endif">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bx-message-detail'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Activity
                            </span>
                        </a>
                        {{-- Opened on load when the current page is in this branch. --}}
                        <ul class="t-list mx-sidenav__sub @if (! request()->routeIs('admin.activities.*')) mx-sidenav__sub--hide @endif">
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ route('admin.activities.create') }}" class="mx-sidenav__sub-link @if (request()->routeIs('admin.activities.create')) active-page @endif">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-message-dots'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Activity
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="{{ route('admin.activities.index') }}" class="mx-sidenav__sub-link @if (request()->routeIs('admin.activities.index')) active-page @endif">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bxs-message-dots'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
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
    <!-- Side Nav End -->
</div>