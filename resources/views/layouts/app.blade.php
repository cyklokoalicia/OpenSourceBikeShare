<!DOCTYPE html>
<!--
This is a starter template page. Use this page to start your new project from
scratch. This page gets rid of all links and provides the needed markup only.
-->
<html lang="{{ app()->getLocale() }}">

@include('layouts.partials.html-header')

<!--
BODY TAG OPTIONS:
=================
Apply one or more of the following classes to get the
desired effect
|---------------------------------------------------------|
| SKINS         | skin-blue                               |
|               | skin-black                              |
|               | skin-purple                             |
|               | skin-yellow                             |
|               | skin-red                                |
|               | skin-green                              |
|---------------------------------------------------------|
|LAYOUT OPTIONS | fixed                                   |
|               | layout-boxed                            |
|               | layout-top-nav                          |
|               | sidebar-collapse                        |
|               | sidebar-mini                            |
|---------------------------------------------------------|
-->
<body class="skin-green sidebar-mini">
<div class="wrapper" id="app">

@include('layouts.partials.main-header')

@hasrole('admin')
    @include('layouts.partials.sidebar')
@endhasrole

<!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper @hasrole('admin') sidebar-in @endhasrole">

    @yield('content-header')

    <!-- Main content -->
        <section class="content">
            <!-- Your Page Content Here -->
            @yield('main-content')
        </section><!-- /.content -->
    </div><!-- /.content-wrapper -->

    @include('layouts.partials.control-sidebar')

    @include('layouts.partials.footer')

</div><!-- ./wrapper -->

@include('layouts.partials.scripts')
@stack('scripts')

</body>
</html>
