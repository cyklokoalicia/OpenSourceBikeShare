const elixir = require('laravel-elixir');

require('laravel-elixir-vue');
var concat = require('gulp-concat');

gulp.task('docs', function () {
    return gulp.src(['./storage/app/api-header.apib', './app/Http/Controllers/Api/**/*.apib', './storage/app/api-data-sources.apib'])
        .pipe(concat('api-docs.apib'))
        .pipe(gulp.dest('./storage/app/'));
});

/*
 |--------------------------------------------------------------------------
 | Elixir Asset Management
 |--------------------------------------------------------------------------
 |
 | Elixir provides a clean, fluent API for defining some basic Gulp tasks
 | for your Laravel application. By default, we are compiling the Sass
 | file for our application, as well as publishing vendor resources.
 |
 */

elixir(function(mix) {

    mix.sass('app.scss', 'public/css/libs/');

    mix.browserify('bootstrap.js', 'public/js/libs/');

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

    // data table
    mix.copy('bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css', 'public/css/libs/dataTables.css');
    mix.copy('bower_components/datatables.net/js/jquery.dataTables.min.js', 'public/js/libs/jquery.dataTables.js');
    mix.copy('bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js', 'public/js/libs/dataTables.js');

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
});


// elixir(mix => {
//     mix.sass('app.scss')
//        .webpack('app.js');
// });
