<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Admin Panel')</title>


    @include('layouts.partials.styles')
    @yield('styles')
</head>

<body class="t-bg-primary mx-sidenav-not-collapsed">


    <!-- Side Nav / Display Only Small Screen  -->
    @include('layouts.partials.asidebar-mobile')
    <!-- Side Nav / Display Only Small Screen  End-->

    <!-- Page Wrapper -->
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!--SideNav / Display Only Small Screen  -->
            @include('layouts.partials.asidebar-small')
            <!--HSideNav/ Display Only Small Screen End -->
            @include('layouts.partials.asidebar')
            <!--SideNav / Display Only Large Screen  -->

            <!--SideNav / Display Only Large Screen End -->

            <div class="col-9 col-xxl-10 flex-grow-1">
                <div class="container-fluid px-2 px-xxl-0">
                    <div class="row g-2 g-xxl-0">
                        <div class="col-12">
                            <div class="mx-body">

                                @include('layouts.partials.header')    

                                <div class="mx-body__content" data-simplebar>

                                    <main class="main-content t-mt-15 t-mb-15">
                                        <div class="container-fluid px-3">
                                            <div class="row g-3">
                                                @yield('content')
                                            </div>
                                        </div>
                                    </main>

                                    @include('layouts.partials.footer')


                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Wrapper End -->
    @include('layouts.partials.scripts')
    @yield('scripts')
</body>

</html>
