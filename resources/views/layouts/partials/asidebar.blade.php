<div class="col-3 col-xxl-2 mx-sidenav d-none d-xl-block">
    <!-- Side Nav  -->
    <div class="mx-sidenav__content t-bg-primary">
        <!-- Side Nav Brand  -->
        <div class="mx-sidenav__brand">
            <a href="{{ route('dashboard') }}" class="mx-sidenav__logo t-link">
                <img src="assets/img/logo-w.png" alt="Adminage" class="img-fluid mx-sidenav__logo-is">
            </a>
            <a href="index.html" class="t-link mx-sidenav__logo-sm">
                <img src="assets/img/logo-w-sm.png" alt="Adminage" class="img-fluid mx-sidenav__logo-sm-is">
            </a>
        </div>
        <!-- Side Nav Brand  End-->
        <div class="mx-sidenav__menu-container">
            <nav class="mx-sidenav__menu">
                <ul class="t-list mx-sidenav__menu-is">
                    <li class="mx-sidenav__menu-item active-page">
                        <a href="index.html" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <span class='bx bx-category'></span>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                dashboard
                            </span>
                        </a>
                    </li>

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
                                <a href="user.html" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bxs-user-plus'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        user
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="user-list.html" class="mx-sidenav__sub-link ">
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
                        <a href="profile.html" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bxs-user-account'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                profile
                            </span>
                        </a>
                    </li>

                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bxs-folder-open'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Leads
                            </span>
                        </a>
                        <ul class="t-list mx-sidenav__sub mx-sidenav__sub--hide">
                            <li class="mx-sidenav__sub-item">
                                <a href="create-project.html" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <span class='bx bx-pulse'></span>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Lead
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="project-list.html" class="mx-sidenav__sub-link ">
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

                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bx-message-detail'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Activity
                            </span>
                        </a>
                        <ul class="t-list mx-sidenav__sub mx-sidenav__sub--hide">
                            <li class="mx-sidenav__sub-item">
                                <a href="chat.html" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-message-dots'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Activity
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="group-chat.html" class="mx-sidenav__sub-link ">
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
                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bxs-detail'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Company
                            </span>
                        </a>
                        <ul class="t-list mx-sidenav__sub mx-sidenav__sub--hide">
                            <li class="mx-sidenav__sub-item">
                                <a href="form.html" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-news'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Companies
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="form-validation.html" class="mx-sidenav__sub-link ">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-task'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Companies
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>



                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub">
                        <a href="price-table.html" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bx-receipt'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Contacts
                            </span>
                        </a>
                        <ul class="t-list mx-sidenav__sub mx-sidenav__sub--hide">
                            <li class="mx-sidenav__sub-item">
                                <a href="form.html" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-news'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Contacts
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="form-validation.html" class="mx-sidenav__sub-link ">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-task'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Companies
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <li class="mx-sidenav__menu-item mx-sidenav__has-sub">
                        <a href="#" class="mx-sidenav__menu-link">
                            <span class="mx-sidenav__menu-icon t-mr-15">
                                <i class='bx bx-library'></i>
                            </span>
                            <span class="mx-sidenav__menu-text text-capitalize">
                                Deals
                            </span>
                        </a>
                        <ul class="t-list mx-sidenav__sub mx-sidenav__sub--hide">
                            <li class="mx-sidenav__sub-item">
                                <a href="button.html" class="mx-sidenav__sub-link">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-mobile-alt bx-rotate-90'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        Create Deal
                                    </span>
                                </a>
                            </li>
                            <li class="mx-sidenav__sub-item">
                                <a href="dropdown.html" class="mx-sidenav__sub-link ">
                                    <span class="mx-sidenav__sub-icon t-mr-15">
                                        <i class='bx bx-upload bx-rotate-180'></i>
                                    </span>
                                    <span class="mx-sidenav__sub-text text-capitalize">
                                        View Deals
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