<!-- Left side column. contains the logo and sidebar -->
<aside class="main-sidebar">

    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">

        <!-- Sidebar user panel (optional) -->
        @if (! Auth::guest())
            <div class="user-panel">
                <div class="pull-left image">
                    <img src="{{asset('/img/user2-160x160.jpg')}}" class="img-circle" alt="User Image" />
                </div>
                <div class="pull-left info">
                    <p>{{ Auth::user()->name }}</p>
                    <!-- Status -->
                    <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
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
            <li class="{{ set_active(['app/dashboard']) }}"><a href="{{ route('app.dashboard') }}"><i class='fa fa-dashboard'></i> <span>Dashboard</span></a></li>
            <li class="{{ set_active(['app/home']) }}"><a href="{{ route('app.home') }}"><i class='fa fa-map'></i> <span>Map</span></a></li>
            <li class="{{ set_active(['app/rents*']) }}"><a href="{{ route('app.rents.index') }}"><i class='fa fa-retweet'></i> <span>Rents</span></a></li>
            <li class="{{ set_active(['app/users*']) }}"><a href="{{ route('app.users.index') }}"><i class='fa fa-users'></i> <span>Users</span></a></li>
            <li class="{{ set_active(['app/bikes*']) }}"><a href="{{ route('app.bikes.index') }}"><i class='fa fa-bicycle'></i> <span>Bikes</span></a></li>
            <li class="{{ set_active(['app/stands*']) }}"><a href="{{ route('app.stands.index') }}"><i class='fa fa-map-pin'></i> <span>Stands</span></a></li>
            <li class="treeview">
                <a href="#"><i class='fa fa-link'></i> <span>Multilevel</span> <i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                    <li><a href="#">Link in level 2</a></li>
                    <li><a href="#">Link in level 2</a></li>
                </ul>
            </li>
        </ul><!-- /.sidebar-menu -->
    </section>
    <!-- /.sidebar -->
</aside>
