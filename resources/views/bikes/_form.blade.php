<div class="form-group  {{ ($errors->has('bike_num')) ? 'has-error' : '' }}">
    <label for="bike-number">Bike Number</label>
    <input type="text" class="form-control" id="bike-number" name="bike_num" placeholder="Enter bike number" value="{{ $bike->bike_num ?? old('bike_num') }}">
    <span class="help-block">{{ $errors->first('bike_num') }}</span>
</div>

<div class="form-group">
    <label for="current-code">Current code</label>
    <input type="text" class="form-control" id="current-code" name="current_code" placeholder="Enter current code"  value="{{ $bike->current_code ?? old('current_code') }}">
</div>

<div class="form-group">
    <label for="select-stand">Stand</label>
    <select name="stand_id" id="select-stand" class="form-control select2">
        @foreach($stands as $item)
            <option></option>
            <option value="{{ $item->id }}" {{ ((($bike->stand_id ?? old("stand_id")) == $item->id) ? 'selected' : '') }}>{{ $item->name }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="select-user">User</label>
    <select name="user_id" id="select-user" class="form-control">
        @foreach($users as $item)
            <option></option>
            <option value="{{ $item->id }}" {{ ((($bike->user_id ?? old("user_id")) == $item->id) ? 'selected' : '') }}>{{ $item->email }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="status">Status</label>
    <select name="status" id="status" class="form-control">
        @foreach($status as $item)
            <option value="{{ $item }}" {{ ((($bike->status ?? old("status")) == $item) ? 'selected' : '') }}>{{ $item }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="stack-position">Stack position</label>
    <input type="number" class="form-control" id="stack-position" name="stack_position"
           placeholder="Enter stack position"  value="{{ $bike->stack_position ?? old('stack_position') }}">
</div>

<div class="form-group">
    <label for="note">Note</label>
    <textarea name="note" id="note" class="form-control" cols="10" rows="5" placeholder="Enter note">{{ $bike->note ?? old('note') }}</textarea>
</div>
