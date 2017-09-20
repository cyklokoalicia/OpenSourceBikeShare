<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Create the tabs -->
    <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
        <li class="active"><a href="#control-sidebar-home-tab" data-toggle="tab"><i class="fa fa-home"></i></a></li>
        <li><a href="#control-sidebar-settings-tab" data-toggle="tab"><i class="fa fa-gears"></i></a></li>
    </ul>
    <!-- Tab panes -->
    <div class="tab-content">
        <!-- Home tab content -->
        <div class="tab-pane active" id="control-sidebar-home-tab">
            <div class="form-group">
                <label for="select-stand">Choose stand</label>
                <select name="stand_id" id="select-stand" class="form-control select2">
                    <option></option>
                    @foreach($stands as $item)
                        <option value="{{ $item->id }}" {{ ((($bike->stand_id ?? old("stand_id")) == $item->id) ? 'selected' : '') }}>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>

            <h3 class="control-sidebar-heading">Stand detail</h3>
            <a href="#">look detail screen</a>

        </div><!-- /.tab-pane -->
        <!-- Stats tab content -->
        <div class="tab-pane" id="control-sidebar-stats-tab">Stats Tab Content</div><!-- /.tab-pane -->
        <!-- Settings tab content -->
        <div class="tab-pane" id="control-sidebar-settings-tab">
            <form method="post">
                <h3 class="control-sidebar-heading">General Settings</h3>
                <div class="form-group">
                    <label class="control-sidebar-subheading">
                        Report panel usage
                        <input type="checkbox" class="pull-right" checked/>
                    </label>
                    <p>
                        Some information about this general settings option
                    </p>
                </div><!-- /.form-group -->
            </form>
        </div><!-- /.tab-pane -->
    </div>
</aside><!-- /.control-sidebar -->

<!-- Add the sidebar's background. This div must be placed
       immediately after the control sidebar -->
<div class='control-sidebar-bg'></div>

@push('scripts')
    <script>
        $(document).ready(function () {
            $('#select-stand').select2({
                allowClear: true,
                placeholder: 'Select an option'
            });
        });
    </script>
@endpush
