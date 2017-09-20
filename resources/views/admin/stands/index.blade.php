
@extends('admin.layouts.app')

@section('html_header_title')
    Stands
@endsection

@section('main-content')
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">All stands table</h3>
                    <a href="{{ route('admin.stands.create') }}" class="btn btn-flat btn-success btn-sm pull-right"> <i class="fa fa-plus"></i> Add new</a>
                </div>
                <!-- /.box-header -->
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-condensed table-striped table-hover" id="stands-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Bikes count</th>
                            <th>place name</th>
                            <th>Status</th>
                            <th>lat</th>
                            <th>lng</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($stands as $stand)
                            <tr class="item{{ $stand->id }}">
                                <td>{{ $stand->id }}</td>
                                <td>{{ $stand->name }}</td>
                                <td>{{ $stand->description }}</td>
                                <td>{{ $stand->bikes->count() }}</td>
                                <td>{{ $stand->place_name }}</td>
                                <td>{{ $stand->status }}</td>
                                <td>{{ $stand->latitude }}</td>
                                <td>{{ $stand->longitude }}</td>
                                <td>
                                    <a href="{{ route('admin.stands.show', $stand->uuid) }}" data-toggle="tooltip" title="Detail" class="">
                                        <i class="fa fa-eye fa-fw"></i>
                                    </a>

                                    <a href="{{ route('admin.stands.edit', $stand->uuid) }}" class="">
                                        <i class="fa fa-edit fa-fw"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- ./box-body -->

            </div>
            <!-- /.box -->
        </div>
        <!-- /.col -->
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#stands-table').DataTable({
            pageLength: 50
        });
    });
</script>
@endpush


