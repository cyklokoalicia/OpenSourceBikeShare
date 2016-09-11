<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMorphToNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('notable_type')->after('uuid');
            $table->integer('notable_id')->unsigned()->after('notable_type');
            $table->text('note')->nullable()->after('user_id');
            $table->dropForeign('notes_bike_id_foreign');
            $table->dropColumn('bike_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('note');
            $table->dropColumn('notable_id');
            $table->dropColumn('notable_type');
        });
    }
}
