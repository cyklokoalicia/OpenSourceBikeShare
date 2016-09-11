<!-- Content Header (Page header) -->
@if($show)
<section class="content-header">
    <h1>
        @yield('content_header_title', 'okkk')
        <small>@yield('content_header_description')</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol>
</section>
@endif
