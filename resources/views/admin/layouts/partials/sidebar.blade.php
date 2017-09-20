<!-- Left side column. contains the logo and sidebar -->
<aside class="main-sidebar">

    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">

        <!-- Sidebar user panel (optional) -->
        @if (! Auth::guest())
            <div class="user-panel">
                <div class="pull-left image">
                    <img src="{{asset('/img/user2-160x160.jpg')}}" class="img-circle" alt="User Image"/>
                </div>
                <div class="pull-left info">
                    <p>{{ Auth::user()->name }}</p>
                    <!-- Status -->
                    <a href="#">
                        <i class="fa fa-circle text-success"></i>
                        Online
                    </a>
                </div>
            </div>
    @endif

    <!-- search form (Optional) -->
        <form action="#" method="get" class="sidebar-form">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Search..."/>
                <span class="input-group-btn">
                <button type='submit' name='search' id='search-btn' class="btn btn-flat"><i class="fa fa-search"></i></button>
              </span>
            </div>
        </form>
        <!-- /.search form -->

        <!-- Sidebar Menu -->
        <ul class="sidebar-menu">
            <li class="header">HEADER</li>
            <!-- Optionally, you can add icons to the links -->
            <li class="{{ set_active(['admin/dashboard']) }}">
                <a href="{{ route('admin.dashboard') }}">
                    <i class='fa fa-dashboard'></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="{{ set_active(['admin/home']) }}">
                <a href="{{ route('admin.home') }}">
                    <i class='fa fa-map'></i>
                    <span>Map</span>
                </a>
            </li>
            <li class="{{ set_active(['admin/rents*']) }}">
                <a href="{{ route('admin.rents.index') }}">
                    <i class='fa fa-retweet'></i>
                    <span>Rents</span>
                </a>
            </li>
            <li class="{{ set_active(['admin/users*']) }}">
                <a href="{{ route('admin.users.index') }}">
                    <i class='fa fa-users'></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="{{ set_active(['admin/bikes*']) }}">
                <a href="{{ route('admin.bikes.index') }}">
                    <i class='fa fa-bicycle'></i>
                    <span>Bikes</span>
                </a>
            </li>
            <li class="{{ set_active(['admin/stands*']) }}">
                <a href="{{ route('admin.stands.index') }}">
                    <i class='fa fa-map-pin'></i>
                    <span>Stands</span>
                </a>
            </li>
            <li class="treeview">
                <a href="#">
                    <i class='fa fa-link'></i>
                    <span>Multilevel</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li>
                        <a href="#">Link in level 2</a>
                    </li>
                    <li>
                        <a href="#">Link in level 2</a>
                    </li>
                </ul>
            </li>
            <li class="{{ set_active(['admin/logs']) }}">
                <a href="{{ route('admin.logs.index') }}">
                    <i class='fa fa-file-text'></i>
                    <span>Logs</span>
                </a>
            </li>
            <li class="{{ set_active(['admin/enveditor']) }}">
                <a href="{{ url('admin/enveditor') }}">
                    <i class='fa fa-gears'></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul><!-- /.sidebar-menu -->
    </section>
    <!-- /.sidebar -->
</aside>
