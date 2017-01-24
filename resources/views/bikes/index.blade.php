@extends('layouts.app')

@section('html_header_title')
    Bikes
@endsection

@section('main-content')
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">All bikes table</h3>
                    <a href="{{ route('app.bikes.create') }}" class="btn btn-flat btn-success btn-sm pull-right"> <i class="fa fa-plus"></i> Add new</a>
                </div>
                <!-- /.box-header -->
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-condensed table-striped table-hover" id="bikes-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Bike num</th>
                            <th>Current code</th>
                            <th>Used by</th>
                            <th>Current stand</th>
                            <th>Stack position</th>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($bikes as $bike)
                            <tr class="item{{ $bike->id }}">
                                <td>{{ $bike->id }}</td>
                                <td>{{ $bike->bike_num }}</td>
                                <td>{{ $bike->current_code }}</td>
                                <td><a href="{{ route('app.users.profile.show', $bike->user->uuid ?? '' ) }}">{{ $bike->user ? $bike->user->name : '' }}</a></td>
                                <td><a href="{{ route('app.stands.show', $bike->stand->uuid ?? '' ) }}">{{ $bike->stand ? $bike->stand->name : '' }}</a></td>
                                <td>{{ $bike->stack_position }}</td>
                                <td><span class="label {{ getStatusLabel($bike->status) }}">{{ $bike->status }}</span></td>
                                <td>{{ $bike->note }}</td>
                                <td>
                                    <a href="{{ route('app.bikes.edit', $bike->uuid) }}" class="edit-modal" data-toggle="tooltip" title="Edit">
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
        $('#bikes-table').DataTable({
            pageLength: 50
        });
    });
</script>
@endpush


