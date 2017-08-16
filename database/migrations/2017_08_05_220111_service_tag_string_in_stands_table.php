<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ServiceTagStringInStandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stands', function (Blueprint $table) {
            $table->dropColumn('service_tag');
            $table->string('status')->nullable()->after('place_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stands', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->boolean('service_tag')->nullable()->after('place_name');
        });
    }
}
