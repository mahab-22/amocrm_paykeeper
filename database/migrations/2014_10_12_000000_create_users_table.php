<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->id();
            $table->string('client_id')->nullable();
            $table->string('referer')->unique();
            $table->string('account_id')->unique()->nullable();
            $table->text('access_token')->nullable();
            $table->integer('access_token_expires')->nullable();
            $table->text('refresh_token')->nullable();
            $table->integer('refresh_token_expires')->nullable();
            $table->string('pk_url')->nullable();
            $table->string('pk_login')->nullable();
            $table->string('pk_password')->nullable();
            $table->text('secret_word')->nullable();
            $table->string('email')->nullable();
            $table->tinyInteger('active')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
