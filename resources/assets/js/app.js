/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

// import 'helpers/httpMethodLinks';

const VueGoogleMaps = require('vue2-google-maps');
Vue.use(VueGoogleMaps, {
    load: {
        key: 'AIzaSyAn66z5qd-sCV6dafQ_yFr5ffPDIJmSRMg',
        v: '3.29',
        libraries: 'places'
    }
});


import vSelect from 'vue-select'
Vue.component('example', require('./components/Example.vue'));
Vue.component('control-sidebar', require('./components/Control-sidebar.vue'));
Vue.component('stand-location', require('./components/StandLocation.vue'));
Vue.component('v-select', vSelect);

const app = new Vue({
    el: '#app'
});
