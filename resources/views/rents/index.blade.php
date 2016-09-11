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
                        <table class="table table-bordered table-hover table-condensed" id="rents-table">
                            <thead>
                            <tr>
                                <th>Status</th>
                                <th>Bike</th>
                                <th>From stand</th>
                                <th>To stand</th>
                                <th>Started at</th>
                                <th>Ended at</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rents['data'] as $rent)
                                <tr>
                                    <td><span class="@include('layouts.partials.class', ['label' => 'label', 'status' => $rent['status']])">{{ $rent['status'] }}</span></td>
                                    <td>{{ $rent['bike']['data']['bike_num'] }}</td>
                                    <td>{{ $rent['standFrom']['data']['name'] }}</td>
                                    <td>{{ isset($rent['standTo']) ? $rent['standTo']['data']['name'] : '' }}</td>
                                    <td>{{ $rent['started_at'] }}</td>
                                    <td>{{ $rent['ended_at'] }}</td>
                                    <td>
                                        <a href="{!! route('app.bikes.return', ['uuid' => $rent['bike']['data']['uuid']]) !!}" class="btn btn-flat btn-sm btn-warning" title="return bike"><i class="fa fa-refresh fa-fw"></i></a>
                                        <a href="{!! route('app.rents.show', ['uuid' => $rent['uuid']]) !!}" class="btn btn-flat btn-sm btn-primary" title="view rent"><i class="fa fa-eye fa-fw"></i></a>
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

@push('in-scripts')
<script src="{{ asset('js/libs/jquery.dataTables.js') }}"></script>
<script src="{{ asset('js/libs/dataTables.js') }}"></script>
<script>
$(function() {
    $('#rents-table').DataTable()
});
</script>
@endpush
