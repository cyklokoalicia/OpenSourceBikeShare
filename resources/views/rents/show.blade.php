@extends('layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Rent bike no. {{ $rent['data']['bike']['data']['bike_num'] }}</h3>
                    </div>

                    <div class="box-body">
                        You are logged in!
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
