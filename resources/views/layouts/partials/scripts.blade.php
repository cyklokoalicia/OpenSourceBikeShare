<!-- REQUIRED JS SCRIPTS -->

{{--<script src="{{ asset('/js/min.js') }}" type="text/javascript"></script>--}}
<!-- jQuery 2.1.4 -->
<script src="{{ asset('/js/libs/jquery.js') }}"></script>
<script>
    var AdminLTEOptions = {
        //Enable sidebar expand on hover effect for sidebar mini
        //This option is forced to true if both the fixed layout and sidebar mini
        //are used together
        sidebarExpandOnHover: true,
        //BoxRefresh Plugin
        enableBoxRefresh: true,
        //Bootstrap.js tooltip
        enableBSToppltip: true,
        controlSidebarOptions: {
            //Which button should trigger the open/close event
            toggleBtnSelector: "[data-toggle='stand-detail']",
            //The sidebar selector
            selector: ".control-sidebar",
            //Enable slide over content
            slide: true
        }
    };
</script>


<script src="{{ asset('/js/app.js') }}"></script>
{{--<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>--}}
<!-- Bootstrap 3.3.2 JS -->
{{--<script src="{{ asset('/js/libs/bootstrap.js') }}" type="text/javascript"></script>--}}
<!-- DataTables -->
{{--<script src="//cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js"></script>--}}
<!-- AdminLTE App -->
<script src="{{ asset('/js/libs/admin-lte.js') }}" type="text/javascript"></script>
<script src="{{ asset('/js/libs/i-check.js') }}" type="text/javascript"></script>
<script src="{{ asset('/js/libs/select2.js') }}" type="text/javascript"></script>
<script src="{{ asset('/js/libs/jquery.dataTables.js') }}" type="text/javascript"></script>
<script src="{{ asset('/js/libs/dataTables.js') }}" type="text/javascript"></script>
<script src="{{ asset('/js/libs/remodal.js') }}"></script>

<!-- Optionally, you can add Slimscroll and FastClick plugins.
      Both of these plugins are recommended to enhance the
      user experience. Slimscroll is required when using the
      fixed layout. -->
<script src="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
{!! Toastr::render() !!}
