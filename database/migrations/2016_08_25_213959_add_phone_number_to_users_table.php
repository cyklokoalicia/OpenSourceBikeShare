<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPhoneNumberToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('uuid')->after('id');
            $table->string('phone_number')->after('password')->unique();
            $table->text('note')->after('phone_number')->nullable();
            $table->text('recommendation')->after('note')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
            $table->dropColumn('phone_number');
            $table->dropColumn('note');
            $table->dropColumn('recommendation');
            $table->dropSoftDeletes();
        });
    }
}
