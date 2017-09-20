@extends('user.layouts.auth')

@section('content')
    <div class="user-login container">
        <div class="row h-75">
            <div class="col-12 col-sm-6 mx-auto my-auto d-block form-box p-3">
                <h4 class="text-center">Login</h4>
                @if (count($errors) > 0)
                    <div class="alert alert-danger">
                        <strong>Whoops!</strong> There were some problems with your input.<br><br>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('app.auth.post.login') }}" method="POST">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="phone-number">Phone number</label>
                        <input type="text" class="form-control" name="phone_number" id="phone-number" placeholder="Enter phone number">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Password">
                    </div>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input type="checkbox" name="remember" class="form-check-input">
                            Remember me
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>

    </div>
@endsection
