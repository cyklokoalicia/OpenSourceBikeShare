@extends('layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">All Rents</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-hover table-condensed table-striped" id="rents-table">
                            <thead>
                            <tr>
                                <th>Status</th>
                                <th>Bike</th>
                                <th>User</th>
                                <th>From stand</th>
                                <th>To stand</th>
                                <th>Old code</th>
                                <th>New code</th>
                                <th>Started at</th>
                                <th>Ended at</th>
                                <th>Duration</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rents->data as $rent)
                                <tr>
                                    <td><span class="@include('layouts.partials.class', ['label' => 'label', 'status' => $rent->status])">{{ $rent->status }}</span></td>
                                    <td><a href="{{ route('app.bikes.show', $rent->bike->data->uuid) }}">{{ $rent->bike->data->bike_num }}</a></td>
                                    <td><a href="{{ route('app.users.profile.show', $rent->user->data->uuid) }}">{{ $rent->user->data->name }}</a></td>
                                    <td><a href="{{ route('app.stands.show', $rent->standFrom->data->uuid) }}">{{ $rent->standFrom->data->name }}</a></td>
                                    <td>@if(isset($rent->standTo))<a href="{{ route('app.stands.show', $rent->standTo->data->uuid) }}">{{ $rent->standTo->data->name }}</a>@endif</td>
                                    <td>{{ $rent->old_code }}</td>
                                    <td>{{ $rent->new_code }}</td>
                                    <td>{{ Carbon\Carbon::parse($rent->started_at)->format('d M, Y H:m') }}</td>
                                    <td>@if(! empty($rent->ended_at)){{ Carbon\Carbon::parse($rent->ended_at)->format('d M, Y H:m') }}@endif</td>
                                    <td>{{ Carbon\Carbon::parse($rent->started_at)->diffForHumans() }}</td>
                                    <td>
                                        <a href="{!! route('app.bikes.return', ['uuid' => $rent->bike->data->uuid]) !!}" class="btn btn-flat btn-sm btn-warning" title="return bike"><i class="fa fa-refresh fa-fw"></i></a>
                                        <a href="{!! route('app.rents.show', ['uuid' => $rent->uuid]) !!}" class="btn btn-flat btn-sm btn-primary" title="view rent"><i class="fa fa-eye fa-fw"></i></a>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function() {
    $('#rents-table').DataTable({
        pageLength: 50
    })
});
</script>
@endpush
