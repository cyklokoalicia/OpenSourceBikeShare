<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uuid');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone_number')->unique();
            $table->decimal('credit', 12, 2)->nullable();
            $table->integer('limit')->default(0);
            $table->text('note')->nullable();
            $table->text('recommendation')->nullable();
            $table->rememberToken();
            $table->boolean('locked')->default(0);
            $table->string('confirmation_token')->nullable();
            $table->timestamps();
            $table->timestamp('last_login')->nullable();
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
        Schema::drop('users');
    }
}
