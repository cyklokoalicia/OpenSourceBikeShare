@extends('admin.layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Rent bike no. {{ $rent->bike->bike_num }}</h3>
                    </div>

                    <div class="box-body">

                        <!-- The timeline -->
                        <ul class="timeline timeline-inverse">
                            <!-- timeline time label -->
                            @if($rent->ended_at)
                                <li class="time-label">
                                    <span class="bg-red">
                                      {{ $rent->ended_at->format('d.M Y H:i:s')}}
                                    </span>
                                </li>
                                <li class="time-label">
                                    <span class="bg-aqua">
                                      {{ secToHours($rent->duration) }}
                                    </span>
                                </li>
                            @endif
                            <!-- /.timeline-label -->
                            <!-- timeline item -->
                            <li>
                                <i class="fa fa-comments bg-yellow"></i>

                                <div class="timeline-item">
                                    <span class="time"><i class="fa fa-clock-o"></i> 27 mins ago</span>

                                    <h3 class="timeline-header"><a href="#">Jay White</a> commented on your post</h3>

                                    <div class="timeline-body">
                                        Take me to your leader!
                                        Switzerland is small and neutral!
                                        We are more like Germany, ambitious and misunderstood!
                                    </div>
                                    <div class="timeline-footer">
                                        <a class="btn btn-warning btn-flat btn-xs">View comment</a>
                                    </div>
                                </div>
                            </li>
                            <!-- END timeline item -->
                            <!-- timeline time label -->
                            <li class="time-label">
                                <span class="bg-green">
                                  {{ $rent->started_at->format('d.M Y H:i:s')  }}
                                </span>
                            </li>
                            <!-- /.timeline-label -->
                            <li>
                                <i class="fa fa-clock-o bg-gray"></i>
                            </li>
                        </ul>

                        <!-- /.tab-pane -->
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
