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
                                :debounce="250"
                                :on-search="getOptions"
                                :on-change="getStandDetail"
                                :options="options"
                                placeholder="Search stand..."
                                label="name"
                        >
                        </v-select>
                    </div>

                    <h3 class="control-sidebar-heading">Stand detail</h3>
                    <a href="">look detail screen</a>

                </div><!-- /.tab-pane -->
                <!-- Stats tab content -->
                <div class="tab-pane" id="control-sidebar-stats-tab">Stats Tab Content</div><!-- /.tab-pane -->
                <!-- Settings tab content -->
                <div class="tab-pane" id="control-sidebar-settings-tab">
                    <form method="post">
                        <h3 class="control-sidebar-heading">General Settings</h3>
                        <div class="form-group">
                            <label class="control-sidebar-subheading">
                                Report panel usage
                                <input type="checkbox" class="pull-right" checked/>
                            </label>
                            <p>
                                Some information about this general settings option
                            </p>
                        </div><!-- /.form-group -->
                    </form>
                </div><!-- /.tab-pane -->
            </div>
        </aside><!-- /.control-sidebar -->

        <!-- Add the sidebar's background. This div must be placed
               immediately after the control sidebar -->
        <div class='control-sidebar-bg'></div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                options: []
            }
        },
        methods: {
            getOptions(q, loading) {
                loading(true)
                axios.get('/app.json/stands', {
                    params: {
                        search: q
                    }
                }).then(resp => {
                    this.options = resp.data.stands
                    loading(false)
                })
            },

            getStandDetail(obj) {
                axios.get('/app.json/stands/' + obj.name).then(resp => {
                    console.log(resp.data);
                });
            }
        },
        mounted() {
            console.log('Component mounted.')
        }
    }


</script>
