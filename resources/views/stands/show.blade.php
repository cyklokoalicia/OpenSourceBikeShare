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
                        <div class="row">
                            <div class="col-md-6">
                                <div id="detail-map" style="height: 300px;"></div>
                            </div>

                            <div class="col-md-6">
                                <div class="photo">
                                    <h1>photo</h1>
                                    @foreach($stand->getMedia('stand') as $image)
                                        <img src="{{ $image->getUrl() }}" alt="">
                                    @endforeach
                                </div>
                            </div>
                        </div>

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
                                        <td>
                                            <a href="{{ route('app.bikes.edit', $bike->uuid) }}">{{ $bike->bike_num }}</a>
                                        </td>
                                        <td>{{ $bike->stack_position }}</td>
                                        <td>{{ $bike->current_code }}</td>
                                        <td>
                                            <span class="label {{ getStatusLabel($bike->status) }}">{{ $bike->status }}</span>
                                        </td>
                                        <td>@if(count($bike->rents) > 0)<a
                                                    href="{{ route('app.users.profile.show', $bike->rents[0]->user->uuid) }}">{{ $bike->rents[0]->user->email }}</a>@endif
                                        </td>
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

@push('scripts')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.map_api_key') }}&callback=initMap"
            async defer></script>
    <script type="application/javascript">
        var map;

        var stand = {!! json_encode($stand->toArray()) !!};
        function initMap() {
            var myLatLng = {lat: stand.latitude, lng: stand.longitude};
            map = new google.maps.Map(document.getElementById('detail-map'), {
                center: myLatLng,
                zoom: 17
            });

            var marker = new google.maps.Marker({
                position: myLatLng,
                map: map,
                title: stand.name
            });
        }
    </script>
@endpush
