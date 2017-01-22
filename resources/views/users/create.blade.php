@extends('layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Create new User</h3>
                    </div>

                    <form action="{{ route('app.users.store') }}" method="POST">
                        {{ csrf_field() }}
                        <div class="box-body">
                            @include('users._form')
                        </div>
                        <div class="box-footer">
                            <input type="submit" class="btn btn-success btn-flat" value="Submit">
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
