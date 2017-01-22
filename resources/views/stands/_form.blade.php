<div class="form-group  {{ ($errors->has('name')) ? 'has-error' : '' }}">
    <label for="name">Name</label>
    <input type="text" class="form-control" id="name" name="name" placeholder="Enter name" value="{{ $stand->name ?? old('name') }}">
    <span class="help-block">{{ $errors->first('name') }}</span>
</div>

<div class="form-group  {{ ($errors->has('place_name')) ? 'has-error' : '' }}">
    <label for="place-name">Place name</label>
    <input type="text" class="form-control" id="place-name" name="place_name" placeholder="Enter place name" value="{{ $stand->place_name ?? old('place_name') }}">
    <span class="help-block">{{ $errors->first('place_name') }}</span>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea class="form-control" id="description" name="description" placeholder="Enter description">{{ $stand->description ?? old('description') }}</textarea>
</div>

<!-- radio -->
<div class="form-group">
    <label>
        <input type="checkbox" name="service_tag" id="is-service" class="minimal" value="1" {{ ((($stand->service_tag ?? old('service_tag')) == 1) ? 'checked' : '') }}>
        Is service stand ?
    </label>
</div>

<div class="form-group">
    <label for="latitude">Latitude</label>
    <input type="number" step="any" class="form-control" id="latitude" name="latitude" placeholder="Enter latitude"  value="{{ $stand->latitude ?? old('latitude') }}">
</div>

<div class="form-group">
    <label for="longitude">Longitude</label>
    <input type="number" step="any" class="form-control" id="longitude" name="longitude" placeholder="Enter longitude"  value="{{ $stand->longitude ?? old('longitude') }}">
</div>
