@extends('layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Edit Bike</h3>
                    </div>

                    <form action="{{ route('app.bikes.update', $bike->uuid) }}" method="POST">
                        {{ csrf_field() }}
                        {{ method_field('PUT') }}
                        <div class="box-body">
                            @include('bikes._form')
                        </div>
                        <div class="box-footer">
                            <input type="submit" class="btn btn-success btn-flat" value="Update">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#select-user').select2({
            allowClear: true,
            placeholder: 'Select an option'
        });
        $('#select-stand').select2({
            allowClear: true,
            placeholder: 'Select an option'
        });
    });
</script>
@endpush
