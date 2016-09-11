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
                        <div class="bikes">
                            <ul class="products-list product-list-in-box">
                                @forelse($bikes as $bike)
                                <li class="item">
                                    <div class="product-img">
                                        <img src="/img/user2-160x160.jpg" alt="Product Image">
                                    </div>
                                    <div class="product-info">
                                        <a href="javascript:void(0)" class="product-title">{{ $bike['bike_num'] }}</a>
                                        <form action="{{ route('app.bikes.rent', ['uuid' =>  $bike['uuid']]) }}" class="pull-right" method="POST">
                                            {{ csrf_field() }}
                                            <input type="hidden" value="{{ $stand['uuid'] }}" name="stand">
                                            <button type="submit" class="btn btn-flat btn-warning"><i class="fa fa-refresh"></i> Rent bike</button>
                                        </form>
                                        <span class="product-description">
                                            {{ $bike['note'] }}
                                        </span>
                                    </div>
                                </li>
                                <!-- /.item -->
                                @empty
                                    <p>no bikes</p>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
