<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ActivatePaytracePaymentDriver extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $paytrace = Gateway::find(46);

        if($paytrace)
        {
            $fields = json_decode($paytrace->fields);
            $fields->integratorId = "";

            $paytrace->fields = json_encode($fields);
            $paytrace->provider = 'Paytrace';
            $paytrace->visible = true;
            $paytrace->save();
        }
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
}
