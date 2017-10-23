@extends('admin.layouts.app')

@section('main-content')
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Edit Stand</h3>
                    </div>

                    <form action="{{ route('admin.stands.update', $stand->uuid) }}" method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        {{ method_field('PUT') }}
                        <div class="box-body">
                            @include('admin.stands._form')

                            @if($media->isEmpty())
                                <div class="panel-body">
                                    No media added yet.
                                </div>
                            @endif

                            <div class="row">

                                <div class="col-md-12">

                                        <ul class="mailbox-attachments clearfix">
                                            @foreach($media as $file)
                                                <li>
                                                    <span class="mailbox-attachment-icon has-img"><img
                                                                src="{{ $file->getUrl('thumb') }}" alt="{{ $file->name }}"></span>

                                                    <div class="mailbox-attachment-info">
                                                        <a href="#" class="mailbox-attachment-name">{{ str_limit($file->name, 22) }}</a>
                                                        <span class="mailbox-attachment-size">
                              {{ $file->human_readable_size }} <br />
                                                            {{ $file->mime_type }}
                              <a href="{{ route("admin.stand.media.destroy", [$stand->uuid, $file->id]) }}" class="btn btn-default btn-xs pull-right"><i class="fa fa-trash-o"></i></a>
                            </span>
                                                    </div>
                                                </li>
                                            @endforeach

                                        </ul>
                                </div>
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
