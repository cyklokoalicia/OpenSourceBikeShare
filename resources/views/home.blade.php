@extends('layouts.app')

@section('html_header_title')
    map
@endsection

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div id="map" style="height: 500px;"></div>
                {{--@javascript('stands', $stands)--}}
                {{--{!! $map !!}--}}
                {{--{!! $apiMap !!}--}}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.map_api_key') }}&callback=initMap"
            async defer></script>


    <script type="application/javascript">
        var map;

        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                center: {lat: 48.148778, lng: 17.105267},
                zoom: 13
            });

            CustomMarker.prototype = new google.maps.OverlayView();

            axios.get('/app.json/stands').then(function(response) {
                $.each(response.data.stands, function (key, value) {
                    let myLatlng = new google.maps.LatLng(value.latitude, value.longitude);
                    overlay = new CustomMarker(myLatlng, value.name, value.bikes.length);
                    overlay.setMap(map);
                });
            });


            CustomMarker.prototype.draw = function () {
                var me = this;
                var div = this.div_;
                if (!div) {
                    div = this.div_ = document.createElement('DIV');

                    // Maybe Custom Param 1 is a class name
                    div.className = 'stand-detail';
                    div.setAttribute('data-toggle', 'stand-detail');
                    // And Param 2 is some content for the marker
                    div.contentText = this.title;
                    div.contentText2 = this.bikeCount;

                    if (this.bikeCount) {
                        div.innerHTML = '<a href="" data-slug="' + this.title + '"><img src="{{ asset('img/icon.png') }}" style="width:60px; height: 60px;"><strong><p class="bike-count">' + this.bikeCount + '</p></strong><p class="stand-name">' + this.title + '</p></a>';
                    } else {
                        div.innerHTML = '<a href="" data-slug="' + this.title + '"><img src="{{ asset('img/icon-none.png') }}" style="width:60px; height: 60px;"><strong><p class="bike-count">' + this.bikeCount + '</p></strong><p class="stand-name">' + this.title + '</p></a>';
                    }

                    div.style.border = 'none';
                    div.style.position = 'absolute';
                    div.style.cursor = 'pointer';
                    div.style.width = '60px';
                    div.style.height = '60px';

                    var panes = this.getPanes();
                    panes.overlayImage.appendChild(div);
                }
                var point = this.getProjection().fromLatLngToDivPixel(this.latlng_);
                if (point) {
                    div.style.left = point.x + 'px';
                    div.style.top = point.y + 'px';
                }


                var _this = this;
                //Update options
                var o = $.AdminLTE.options.controlSidebarOptions;
                //The toggle button
                var btn = $(o.toggleBtnSelector);

                $("[data-toggle='stand-detail']").on('click', 'a', function (e) {
                    e.preventDefault();
                    var slug = $(this).data('slug');
                    axios.get('/app.json/stands/' + slug).then(function (response) {
                        console.log(response);
                    }).catch(function (error) {
                        console.log(error);
                    });
                });


            };


        }

        function CustomMarker(latlng, title, bikeCount) {
            this.latlng_ = latlng;
            this.title = title;
            this.bikeCount = bikeCount;
        }


    </script>
@endpush
