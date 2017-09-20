<template>
    <div>
        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Create the tabs -->
            <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
                <li class="active"><a href="#control-sidebar-home-tab" data-toggle="tab"><i class="fa fa-home"></i></a>
                </li>
                <li><a href="#control-sidebar-settings-tab" data-toggle="tab"><i class="fa fa-gears"></i></a></li>
            </ul>
            <!-- Tab panes -->
            <div class="tab-content">
                <!-- Home tab content -->
                <div class="tab-pane active" id="control-sidebar-home-tab">
                    <div class="form-group">
                        <v-select
                                :debounce="550"
                                :on-search="getOptions"
                                :on-change="getStandDetail"
                                :options="options"
                                placeholder="Search stand..."
                                label="name"
                        >
                        </v-select>
                    </div>

                    <h3 class="control-sidebar-heading">Stand detail</h3>
                    <p>{{ stand.description }}</p>
                    <a :href="'stands/' + stand.uuid" >look stand detail screen</a>

                    <h3 class="control-sidebar-heading">Bikes <span v-bind:text="bikes.length"></span></h3>
                    <ul id="bikes-list">
                        <li v-for="(bike, index) in bikes">
                            <button class="btn btn-flat" v-bind:class="classObject(bike)">{{ bike.bike_num }}</button>
                        </li>
                    </ul>

                </div><!-- /.tab-pane -->
                <!-- Stats tab content -->
                <div class="tab-pane" id="control-sidebar-stats-tab">Stats Tab Content</div><!-- /.tab-pane -->
                <!-- Settings tab content -->
                <div class="tab-pane" id="control-sidebar-settings-tab">

                </div><!-- /.tab-pane -->
            </div>
        </aside><!-- /.control-sidebar -->

        <!-- Add the sidebar's background. This div must be placed
               immediately after the control sidebar -->
        <div class='control-sidebar-bg'></div>
    </div>
</template>

<script>
    import {Stand} from '../classes/Stand';

    export default {
        data() {
            return {
                options: [],
                description: "",
                bikes: [],
                stand: {}
            }
        },
        methods: {
            getOptions(q, loading) {
                loading(true);
                axios.get('/app.json/stands', {
                    params: {
                        search: q
                    }
                }).then(resp => {
                    this.options = resp.data.stands;
                    loading(false)
                })
            },

            getStandDetail(obj) {
                axios.get('/app.json/stands/' + obj.name).then(resp => {
                    console.log(resp.data);
                    this.stand = resp.data.stand;
                    this.bikes = resp.data.bikes;
                });
            },

            classObject: function (bike) {
                if (bike.note) {
                    return 'btn-warning';
                } else {
                    return 'btn-success';
                }
            },

            handler: function (data) {
               console.log(data);
               console.log('oo');
            }
        },
        events: {
            'stand_bind' : function(data){
                console.log('ouu');
            },
        },
        mounted() {
            console.log('Component mounted.')
        },
        computed: {

        }
    }


</script>
