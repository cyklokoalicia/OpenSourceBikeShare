<!DOCTYPE html>
<!--
This is a starter template page. Use this page to start your new project from
scratch. This page gets rid of all links and provides the needed markup only.
-->
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>@yield('html_header_title', 'Page title') - BikeShare </title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
@include('admin.layouts.partials.html-header')
</head>

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

@include('admin.layouts.partials.main-header')

@hasrole('admin')
    @include('admin.layouts.partials.sidebar')
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

    {{--@include('admin.layouts.partials.control-sidebar')--}}
    <control-sidebar></control-sidebar>
    @include('admin.layouts.partials.footer')

</div><!-- ./wrapper -->

@include('admin.layouts.partials.scripts')
@stack('scripts')

</body>
</html>
