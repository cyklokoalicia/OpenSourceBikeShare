@extends('layouts.app')

@section('html_header_title')
    dashboard
@endsection

@section('main-content')
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">All users table</h3>
                    <a href="{{ route('app.users.create') }}" class="btn btn-flat btn-success btn-sm pull-right"> <i class="fa fa-plus"></i> Add new</a>
                </div>
                <!-- /.box-header -->
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-condensed table-striped table-hover" id="users-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone number</th>
                            <th>Limit</th>
                            <th>Credit</th>
                            <th>Active rents</th>
                            <th>Last login</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($users as $user)
                            <tr class="item{{ $user->id }}">
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone_number }}</td>
                                <td>{{ $user->limit }}</td>
                                <td>{{ $user->credit }}</td>
                                <td><a href="{{ route('app.rents.index') }}">{{ $user->activeRents->count() }}</a></td>
                                <td>{{ $user->last_login ? $user->last_login->diffForHumans() : 'Not login yet' }}</td>
                                <td>
                                    <a href="{{ route('app.users.profile.show', $user->uuid) }}" data-toggle="tooltip" title="Profile" class="">
                                        <i class="fa fa-user fa-fw"></i>
                                    </a>
                                    <a href="{{ route('app.users.edit', $user->uuid) }}" data-toggle="tooltip" title="Edit">
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
        $('#users-table').DataTable({
            pageLength: 50
        });
    });
</script>
@endpush

