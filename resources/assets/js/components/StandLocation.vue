<template>
    <div>
        <div class="form-group">
            <label for="latitude">Latitude</label>
            <input type="number" step="any" class="form-control" id="latitude" name="latitude" placeholder="Enter latitude"
                   v-model.number.lazy="standPosition.lat"
                   @change="inputChanged">
        </div>

        <div class="form-group">
            <label for="longitude">Longitude</label>
            <input type="number" step="any" class="form-control" id="longitude" name="longitude" placeholder="Enter longitude"
                   v-model.number.lazy="standPosition.lng"
                   @change="inputChanged">
        </div>

        <gmap-map
                :center="center"
                :zoom="14"
                style="width: 500px; height: 300px">

            <gmap-marker
                    :position="standPosition"
                    :draggable="draggable"
                    @position_changed="standPositionChanged">
            </gmap-marker>

        </gmap-map>
    </div>
</template>

<script>
    export default {
        mounted() {
            console.log('Google-map mounted.')
        },
        props: {
            initlat: {
                type: Number,
                required: true
            },
            initlng: {
                type: Number,
                required: true
            },
        },
        data () {
            return {
//                standPosition: {
//                    lat: 48.8538302,
//                    lng: 2.2982161
//                },
//                center: {
//                    lat: 48.8538302,
//                    lng: 2.2982161
//                },
                standPosition:{
                    lat: this.initlat,
                    lng: this.initlng
                },
                center:{
                    lat: this.initlat,
                    lng: this.initlng
                },
                draggable: true
            }
        },
        methods: {
            standPositionChanged(newPos){
                this.standPosition = {
                    lat: newPos.lat(),
                    lng: newPos.lng(),
                }
            },
            inputChanged(event){
                let context = this;
                Vue.nextTick(function () {
                    context.center = {
                        lat: context.standPosition.lat,
                        lng: context.standPosition.lng
                    };
                });
            }
        }
    }
</script>