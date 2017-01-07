<div class="form-group">
    <label for="bike-number">Bike Number</label>
    <input type="text" class="form-control" id="bike-number" name="bike_num" placeholder="Enter bike number">
</div>

<div class="form-group">
    <label for="current-code">Current code</label>
    <input type="text" class="form-control" id="current-code" name="current_code" placeholder="Enter current code">
</div>

<div class="form-group">
    <label for="select-stand">Stand</label>
    <select name="stand" id="select-stand" class="form-control select2">
        @foreach($stands as $item)
            <option></option>
            <option value="{{ $item->id }}">{{ $item->name }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="select-user">User</label>
    <select name="user" id="select-user" class="form-control">
        @foreach($users as $item)
            <option></option>
            <option value="{{ $item->id }}">{{ $item->email }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="status">Status</label>
    <select name="status" id="status" class="form-control">
        @foreach($status as $item)
            <option value="{{ $item }}">{{ $item }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="stack-position">Stack position</label>
    <input type="number" class="form-control" id="stack-position" name="stack_position"
           placeholder="Enter stack position">
</div>

<div class="form-group">
    <label for="note">Note</label>
    <textarea name="note" id="note" class="form-control" cols="10" rows="5" placeholder="Enter note"></textarea>
</div>
