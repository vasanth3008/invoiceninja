<?php

use App\Models\Design;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Utils\Ninja;
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
        
        if (Ninja::isHosted()) {
            $design = new Design();

            $design->name = 'Calm';
            $design->is_custom = false;
            $design->design = '';
            $design->is_active = true;

            $design->save();
        } elseif (Design::count() !== 0) {
            $design = new Design();

            $design->name = 'Calm';
            $design->is_custom = false;
            $design->design = '';
            $design->is_active = true;

            $design->save();
        }

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

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
