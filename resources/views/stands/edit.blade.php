@extends('layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Edit Stand</h3>
                    </div>

                    <form action="{{ route('app.stands.update', $stand->uuid) }}" method="POST">
                        {{ csrf_field() }}
                        {{ method_field('PUT') }}
                        <div class="box-body">
                            @include('stands._form')

                            @if($media->isEmpty())
                                <div class="panel-body">
                                    No media added yet.
                                </div>
                            @endif

                            <div class="row">
                                @foreach($media as $file)
                                    <div class="item">
                                        <div class="col-md-8">
                                            @if(starts_with($file->mime_type, 'image'))
                                                <a href="{{ $file->getUrl() }}" target="_blank">
                                                    <img class="media-object" src="{{ $file->getUrl() }}" alt="{{ $file->name }}" width="100%">
                                                </a>
                                            @else
                                                <span class="glyphicon glyphicon-file large-icon"></span>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <div class="btn-group pull-right">
                                                <a href="{{ route("app.stand.media.destroy", [$stand->uuid, $file->id]) }}"
                                                   data-method="DELETE"
                                                   data-token="{{ csrf_token() }}"
                                                   class="close">
                                                    <span class="glyphicon glyphicon-remove"></span>
                                                </a>
                                            </div>
                                            <h4 class="media-heading">{{ $file->name }}</h4>
                                            <p>
                                                <code>
                                                    {{ $file->getPath() }}<br/>
                                                </code>
                                                <small>
                                                    {{ $file->human_readable_size }} |
                                                    {{ $file->mime_type }}
                                                </small>
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
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
        $('#is-service').iCheck({
            checkboxClass: 'icheckbox_square-green',
            radioClass: 'iradio_square-green',
            increaseArea: '20%' // optional
        });
    });
</script>
@endpush
