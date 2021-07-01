<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('groups', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('IdUser')->default(0);
            $table->string('Name')->default('');
            $table->boolean('IsOrganization')->default(false);
            $table->string('Email')->default('');
            $table->string('Company')->default('');
            $table->string('Street')->default('');
            $table->string('City')->default('');
            $table->string('State')->default('');
            $table->string('Zip')->default('');
            $table->string('Country')->default('');
            $table->string('Phone')->default('');
            $table->string('Fax')->default('');
            $table->string('Web')->default('');
            $table->string('Events')->default('');
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
        Capsule::schema()->dropIfExists('groups');
    }
}
