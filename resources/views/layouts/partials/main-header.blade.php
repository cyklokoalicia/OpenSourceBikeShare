<!-- Main Header -->
<header class="main-header">

    <!-- Logo -->
    <a href="{{ route('app.home') }}" class="logo">
        <!-- mini logo for sidebar mini 50x50 pixels -->
        <span class="logo-mini"><b>B</b>S</span>
        <!-- logo for regular state and mobile devices -->
        <span class="logo-lg"><b>Bike</b>Share </span>
    </a>

    <!-- Header Navbar -->
    <nav class="navbar navbar-static-top" role="navigation">
        <!-- Sidebar toggle button-->
        @hasrole('admin')
        <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
            <span class="sr-only">Toggle navigation</span>
        </a>
        @endhasrole

        <!-- Navbar Right Menu -->
        <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
                <!-- Messages: style can be found in dropdown.less-->
                @if(! auth()->user()->hasRole('admin'))
                    <li class="navbar-select-stand-li">
                        <select class="navbar-select-stand">
                            @foreach($stands as $stand)
                                <option value="{{ $stand->uuid }}"> {{ $stand->name }}</option>
                            @endforeach
                        </select>
                    </li>
                @endif
                <li class="dropdown messages-menu">
                    <!-- Menu toggle button -->
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="rents">
                        <i class="fa fa-retweet"></i>
                        <span class="label label-warning">{{ count($activeRents) }}</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="header">You have {{ count($activeRents) }} active rents</li>
                        <li>
                            <!-- inner menu: contains the messages -->
                            <ul class="menu">
                                @forelse($activeRents as $rent)
                                <li><!-- start message -->
                                    <a href="{{ route('app.rents.show', ['uuid' => $rent->uuid]) }}">
                                        <!-- Message title and timestamp -->
                                        <h4>
                                            Bike no. {{ $rent->bike->bike_num }}
                                            <small><i class="fa fa-clock-o"></i> {{ $rent->started_at->diffForHumans() }}</small>
                                        </h4>
                                        <!-- The message -->
                                        <p>old code: {{ $rent->old_code }} new code: {{  $rent->new_code }}</p>
                                    </a>
                                </li><!-- end message -->
                                @empty

                                @endforelse
                            </ul><!-- /.menu -->
                        </li>
                        <li class="footer"><a href="{{ route('app.rents.index') }}">See All Rents</a></li>
                    </ul>
                </li><!-- /.messages-menu -->

                @hasrole('admin')
                <!-- Notifications Menu -->
                <li class="dropdown notifications-menu">
                    <!-- Menu toggle button -->
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-bell-o"></i>
                        <span class="label label-warning">10</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="header">You have 10 notifications</li>
                        <li>
                            <!-- Inner Menu: contains the notifications -->
                            <ul class="menu">
                                <li><!-- start notification -->
                                    <a href="#">
                                        <i class="fa fa-users text-aqua"></i> 5 new members joined today
                                    </a>
                                </li><!-- end notification -->
                            </ul>
                        </li>
                        <li class="footer"><a href="#">View all</a></li>
                    </ul>
                </li>
                <!-- Tasks Menu -->
                <li class="dropdown tasks-menu">
                    <!-- Menu Toggle Button -->
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-flag-o"></i>
                        <span class="label label-danger">9</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="header">You have 9 tasks</li>
                        <li>
                            <!-- Inner menu: contains the tasks -->
                            <ul class="menu">
                                <li><!-- Task item -->
                                    <a href="#">
                                        <!-- Task title and progress text -->
                                        <h3>
                                            Design some buttons
                                            <small class="pull-right">20%</small>
                                        </h3>
                                        <!-- The progress bar -->
                                        <div class="progress xs">
                                            <!-- Change the css width attribute to simulate progress -->
                                            <div class="progress-bar progress-bar-aqua" style="width: 20%" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">
                                                <span class="sr-only">20% Complete</span>
                                            </div>
                                        </div>
                                    </a>
                                </li><!-- end task item -->
                            </ul>
                        </li>
                        <li class="footer">
                            <a href="#">View all tasks</a>
                        </li>
                    </ul>
                </li>
                @endhasrole
                @if (Auth::guest())
                    <li><a href="{{ route('auth.login') }}">Login</a></li>
                    <li><a href="{{ route('auth.register') }}">Register</a></li>
                @else
                <!-- User Account Menu -->
                    <li class="dropdown user user-menu">
                        <!-- Menu Toggle Button -->
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <!-- The user image in the navbar-->
                            <img src="/img/user2-160x160.jpg" class="user-image" alt="User Image"/>
                            <!-- hidden-xs hides the username on small devices so only the image appears. -->
                            <span class="hidden-xs">{{ Auth::user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <!-- The user image in the menu -->
                            <li class="user-header">
                                <img src="/img/user2-160x160.jpg" class="img-circle" alt="User Image" />
                                <p>
                                    {{ Auth::user()->name }}
                                    <small>Member since Nov. 2012</small>
                                </p>

                                <p>
                                    {{ Auth::user()->credit }}
                                    <small>Member since Nov. 2012</small>
                                </p>
                            </li>
                            <!-- Menu Body -->
                            <li class="user-body">
                                <div class="col-xs-4 text-center">
                                    <a href="#">Rents</a>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <a href="#">Reports</a>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <a href="https://docs.google.com/document/d/1yEHbLEAU9waMiaxTqXFzZP0bLyRg7NMtN2dQUazro9o/edit">Help</a>
                                </div>
                            </li>
                            <!-- Menu Footer-->
                            <li class="user-footer">
                                <div class="pull-left">
                                    <a href="#" class="btn btn-default btn-flat">Profile</a>
                                </div>
                                <div class="pull-right">
                                    <a href="{{ route('auth.logout') }}" class="btn btn-default btn-flat">Sign out</a>
                                </div>
                            </li>
                        </ul>
                    </li>
            @endif

            <!-- Control Sidebar Toggle Button -->
                <li>
                    <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
                </li>
            </ul>
        </div>
    </nav>
</header>

@push('in-scripts')
<script type="text/javascript">
    $(document).ready(function() {
        $(".navbar-select-stand").select2({
            placeholder: {
                id: -1,
                text: "Select a stand"
            },
            allowClear: true,
            val: ''
        });
    });
</script>
@endpush
