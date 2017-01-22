@extends('layouts.app')

@section('html_header_title')
    Log
@endsection

@section('main-content')
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Logs</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body table-responsive">

                    {!! $logItems->render() !!}

                    <table class="table table-bordered table-condensed table-striped table-hover" id="logs-table">
                        <thead>
                        <tr>
                            <th>Log time</th>
                            <th>Log description</th>
                            <th>User</th>
                        </tr>
                        </thead>
                        <tbody>

                        @foreach($logItems as $logItem)
                            <tr>
                                <td>{{ diff_date_for_humans($logItem->created_at) }}</td>
                                <td>{!! $logItem->description !!} ->  {!! array_last(explode('\\', $logItem->subject_type)) !!}</td>
                                <td>
                                    @if($logItem->causer)
                                        <a href="{{ route('app.users.edit', $logItem->causer->id) }}">
                                            {{ $logItem->causer->email }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach


                        </tbody>
                    </table>

                    {!! $logItems->render() !!}
                </div>
            </div>
        </div>
    </div>
@stop

@push('scripts')
<script>
    $(document).ready(function () {
        $('#logs-table').DataTable({
            pageLength: 50,
            paginate: false
        });
    });
</script>
@endpush
