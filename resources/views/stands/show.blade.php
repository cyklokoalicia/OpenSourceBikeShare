@extends('layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Stand {{ $stand['name'] }}</h3>
                    </div>

                    <div class="box-body">
                        <div class="detail-map"></div>
                        <div class="photo"></div>
                        <div class="bikes table-responsive">
                            <table class="table no-margin">
                                <thead>
                                <tr>
                                    <th>Bike no.</th>
                                    <th>Position</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Last user</th>
                                </tr>
                                </thead>
                                <tbody>

                                @forelse($bikes as $bike)
                                    <tr>
                                        <td><a href="{{ route('app.bikes.edit', $bike->uuid) }}">{{ $bike->bike_num }}</a></td>
                                        <td>{{ $bike->stack_position }}</td>
                                        <td>{{ $bike->current_code }}</td>
                                        <td><span class="label label-success">{{ $bike->status }}</span></td>
                                        <td><a href="{{ route('app.users.edit', $bike->uuid) }}">{{ $bike->bike_num }}</a></td>
                                    </tr>
                                @empty
                                    <p>no bikes</p>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
