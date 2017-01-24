@extends('layouts.app')



@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="box-header with-border">
                                <h3 class="box-title">All Rents</h3>
                            </div>
                        </div>
                    </div>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <div class="row">
                            <form class="filters" action="{{ route('app.rents.index') }}" method="GET">
                                <div class="col-md-12">
                                    <input type="text" id="filter-date" class="form-control"
                                           value="{{ \Carbon\Carbon::parse(app('request')->input('from'))->format('d M, Y H:i') . " - " . \Carbon\Carbon::parse(app('request')->input('to'))->format('d M, Y H:i') }}">
                                    <input type="hidden" id="filter-from" name="from">
                                    <input type="hidden" id="filter-to" name="to">
                                </div>
                                <div class="form-group col-md-4">
                                    <select name="users[]" id="filter-users" class="form-control select2"
                                            multiple="multiple"
                                            data-placeholder="Select a users">
                                        <option></option>
                                        @foreach($users as $item)
                                            <option value="{{ $item->id }}" {{ app('request')->input('users') ? (in_array($item->id, app('request')->input('users')) ? "selected" : "") : '' }}>{{ $item->email }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <select name="stands[]" id="filter-stands" class="form-control select2"
                                            multiple="multiple"
                                            data-placeholder="Select a stands">
                                        <option></option>
                                        @foreach($stands as $item)
                                            <option value="{{ $item->id }}" {{ app('request')->input('stands') ? (in_array($item->id, app('request')->input('stands')) ? "selected" : "") : '' }}>{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <select name="bikes[]" id="filter-bikes" class="form-control select2"
                                            multiple="multiple"
                                            data-placeholder="Select a bikes">
                                        <option></option>
                                        @foreach($bikes as $item)
                                            <option value="{{ $item->id }}" {{ app('request')->input('bikes') ? (in_array($item->id, app('request')->input('bikes')) ? "selected" : "") : '' }}>{{ $item->bike_num }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-flat"><i
                                                class="fa fa-filter fa-fw"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover table-condensed table-striped"
                                           id="rents-table">
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
                                        @forelse($rents as $rent)
                                            <tr>
                                                <td>
                                                    <span class="label {{ getStatusLabel($rent->status) }}">{{ $rent->status }}</span>
                                                </td>
                                                <td>
                                                    <a href="{{ route('app.bikes.show', $rent->bike->uuid) }}">{{ $rent->bike->bike_num }}</a>
                                                </td>
                                                <td>
                                                    <a href="{{ route('app.users.profile.show', $rent->user->uuid) }}">{{ $rent->user->email }}</a>
                                                </td>
                                                <td>
                                                    <a href="{{ route('app.stands.show', $rent->standFrom->uuid) }}">{{ $rent->standFrom->name }}</a>
                                                </td>
                                                <td>
                                                    <a href="{{ route('app.stands.show', $rent->standTo->uuid ?? '') }}">{{ $rent->standTo->name ?? '' }}</a>
                                                </td>
                                                <td>{{ $rent->old_code }}</td>
                                                <td>{{ $rent->new_code }}</td>
                                                <td>{{ $rent->started_at->format('d M, Y H:m') }}</td>
                                                <td>{{ $rent->ended_at ? $rent->ended_at->format('d M, Y H:m') : ''}}</td>
                                                <td>{{ $rent->started_at->diffForHumans() }}</td>
                                                <td>
                                                    <a href="{!! route('app.bikes.return', ['uuid' => $rent->bike->uuid]) !!}"
                                                       data-toggle="tooltip" title="Return"><i
                                                                class="fa fa-refresh fa-fw"></i></a>
                                                    <a href="{!! route('app.rents.show', ['uuid' => $rent->uuid]) !!}"
                                                       data-toggle="tooltip" title="Detail"><i
                                                                class="fa fa-eye fa-fw"></i></a>
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
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/libs/moment.js') }}"></script>
<script src="{{ asset('js/libs/daterangepicker.js') }}"></script>
<script>
    $(function () {
        $('#rents-table').DataTable({
            pageLength: 50
        });

        $('#filter-date').daterangepicker({
            timePicker: true,
            timePickerIncrement: 10,
            timePicker24Hour: true,
            timePickerSeconds: false,
            locale: {
                format: 'DD MMM YYYY HH:mm'
            },
            autoUpdateInput: false,
            autoApply: true,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }).on('apply.daterangepicker', function (ev, picker) {
            $('#filter-from').val(picker.startDate.format('YYYY-MM-DD HH:mm'));
            $('#filter-to').val(picker.endDate.format('YYYY-MM-DD HH:mm'));
        });


        $('#filter-users').select2();
        $('#filter-stands').select2();
        $('#filter-bikes').select2();
    });
</script>
@endpush
