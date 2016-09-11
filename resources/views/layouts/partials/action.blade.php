<a href="{!! route($route . 'show', [$object->id, $role]) !!}" class="btn btn-flat btn-sm btn-info"><i class="fa fa-eye fa-fw"></i></a>
<a href="{!! route($route . 'edit', [$object->id, $role]) !!}" class="btn btn-flat btn-sm btn-warning"><i class="fa fa-edit fa-fw"></i></a>
<form action="{!! route($route . 'destroy', [$object->id, $role]) !!}" style="display: inline-block">
    {!! method_field('DETETE') !!}
    {!! csrf_field() !!}
    <button type="submit" class="btn btn-flat btn-sm btn-danger"><i class="fa fa-trash fa-fw"></i></button>
</form>


{{--<a href="{!! route('admin.examination-types.destroy', [$object->id]) !!}" data-method="delete" data-token="{{ csrf_token() }}" data-confirm="Are you sure?"><i class="fa fa-trash fa-fw"></i></a>--}}
