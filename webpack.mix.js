let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/assets/js/app.js', 'public/js')
    .sass('resources/assets/sass/app.scss', 'public/css');

mix.js('resources/assets/js/user/base.js', 'public/js')
    .sass('resources/assets/sass/user/base.scss', 'public/css');


// Copy bootstrap and AdminLTE CSS files to public directory
mix.copy('bower_components/AdminLTE/bootstrap/css/bootstrap.css', 'public/css/libs/bootstrap.css');
mix.copy('bower_components/AdminLTE/dist/css/AdminLTE.css', 'public/css/libs/admin-lte.css');
mix.copy('bower_components/AdminLTE/dist/css/skins/_all-skins.css', 'public/css/libs/admin-lte-skin.css');
mix.copy('bower_components/AdminLTE/dist/js/app.js', 'public/js/libs/admin-lte.js');
mix.copy('bower_components/AdminLTE/plugins/jQuery/jquery-2.2.3.min.js', 'public/js/libs/jquery.js');


// Copy fonts from Glypicons
mix.copy('bower_components/AdminLTE/bootstrap/fonts', 'public/css/fonts');

// Font Awesome
mix.copy('bower_components/font-awesome/css/font-awesome.css', 'public/css/libs/font-awesome.css');
mix.copy('bower_components/font-awesome/fonts', 'public/css/fonts');

// iCheck
mix.copy('bower_components/AdminLTE/plugins/iCheck/square/green.css', 'public/css/libs/i-check.css');
mix.copy('bower_components/AdminLTE/plugins/iCheck/square/green.png', 'public/css/libs/green.png');
mix.copy('bower_components/AdminLTE/plugins/iCheck/square/green@2x.png', 'public/css/libs/green@2x.png');
mix.copy('bower_components/AdminLTE/plugins/iCheck/icheck.js', 'public/js/libs/i-check.js');

mix.copy('bower_components/select2/dist/css/select2.css', 'public/css/libs/select2.css');
mix.copy('bower_components/select2/dist/js/select2.full.js', 'public/js/libs/select2.js');

// remodal
mix.copy('bower_components/remodal/dist/remodal.css', 'public/css/libs/remodal.css');
mix.copy('bower_components/remodal/dist/remodal-default-theme.css', 'public/css/libs/remodal-default-theme.css');
mix.copy('bower_components/remodal/dist/remodal.js', 'public/js/libs/remodal.js');


// data table
mix.copy('bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css', 'public/css/libs/dataTables.css');
mix.copy('bower_components/datatables.net/js/jquery.dataTables.min.js', 'public/js/libs/jquery.dataTables.js');
mix.copy('bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js', 'public/js/libs/dataTables.js');

// date range picker
mix.copy('bower_components/bootstrap-daterangepicker/daterangepicker.js', 'public/js/libs/daterangepicker.js');
mix.copy('bower_components/moment/min/moment-with-locales.js', 'public/js/libs/moment.js');
mix.copy('bower_components/bootstrap-daterangepicker/daterangepicker.css', 'public/css/libs/daterangepicker.css');

// Merge all CSS files in one file.
mix.styles([
    '/libs/bootstrap.css',
    '/libs/admin-lte.css',
    '/libs/admin-lte-skin.css',
    '/libs/font-awesome.css',
    '/libs/i-check.css',
    '/libs/app.css'
], './public/css/min.css', './public/css');


// Merge all JS  files in one file.
mix.scripts([
    '/libs/jquery.js',
    '/libs/bootstrap.js',
    '/libs/admin-lte.js',
    '/libs/i-check.js'
], './public/js/min.js', './public/js');

