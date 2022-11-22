<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table)
        {
            $table->boolean('invoice_lock')->default(false);
        });

        Schema::table('companies', function (Blueprint $table)
        {
            $table->boolean('invoice_task_lock')->default(false);
        });

        Schema::table('bank_transactions', function (Blueprint $table)
        {
            $table->bigInteger('bank_rule_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
