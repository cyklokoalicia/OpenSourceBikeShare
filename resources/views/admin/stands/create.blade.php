@extends('admin.layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Create new Stand</h3>
                    </div>

                    <form action="{{ route('admin.stands.store') }}" method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="box-body">
                            @include('admin.stands._form')
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-success btn-flat">Submit</button>

                            {{--<input type="submit" class="btn btn-success btn-flat" value="Submit">--}}
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
        $('#is-service').iCheck({
            checkboxClass: 'icheckbox_square-green',
            radioClass: 'iradio_square-green',
            increaseArea: '20%' // optional
        });
    });
</script>
@endpush
