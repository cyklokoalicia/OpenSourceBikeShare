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
                </div>
                <!-- /.box-header -->
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-condensed table-striped table-hover" id="bikes-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>uuid</th>
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
                                <td>{{ $bike->uuid }}</td>
                                <td>{{ $bike->bike_num }}</td>
                                <td>{{ $bike->current_code }}</td>
                                <td><a href="{{ route('app.users.profile.show', $bike->user->uuid ?? '' ) }}">{{ $bike->user ? $bike->user->name : '' }}</a></td>
                                <td><a href="{{ route('app.stands.show', $bike->stand->uuid ?? '' ) }}">{{ $bike->stand ? $bike->stand->name : '' }}</a></td>
                                <td>{{ $bike->stack_position }}</td>
                                <td>{{ $bike->status }}</td>
                                <td>{{ $bike->note }}</td>
                                <td>
                                    <a href="" class="edit-modal btn btn-info btn-sm btn-flat">
                                        <span class="fa fa-edit"></span> Edit
                                    </a>
                                    <a href="" class="delete-modal btn btn-danger btn-sm btn-flat">
                                        <span class="fa fa-trash"></span> Delete
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


