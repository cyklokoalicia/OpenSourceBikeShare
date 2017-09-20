<div class="form-group  {{ ($errors->has('name')) ? 'has-error' : '' }}">
    <label for="name">Name</label>
    <input type="text" class="form-control" id="name" name="name" placeholder="Enter name" value="{{ $user->name ?? old('name') }}">
    <span class="help-block">{{ $errors->first('name') }}</span>
</div>

<div class="form-group">
    <label for="email">Email</label>
    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email"  value="{{ $user->email ?? old('email') }}">
</div>

<div class="form-group">
    <label for="phone-number">Phone number</label>
    <input type="text" class="form-control" id="phone-number" name="phone_number" placeholder="Enter phone number"  value="{{ $user->phone_number ?? old('phone_number') }}">
</div>

<div class="form-group">
    <label for="limit">Limit</label>
    <input type="number" class="form-control" id="limit" name="limit" placeholder="Enter limit"  value="{{ $user->limit ?? old('limit') }}">
</div>

<div class="form-group">
    <label for="credit">Credit</label>
    <input type="number" step="any" class="form-control" id="credit" name="credit" placeholder="Enter credit"  value="{{ $user->credit ?? old('credit') }}">
</div>
