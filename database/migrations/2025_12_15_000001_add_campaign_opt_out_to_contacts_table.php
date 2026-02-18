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
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'campaign_opt_out')) {
                $table->tinyInteger('campaign_opt_out')
                    ->unsigned()
                    ->default(0)
                    ->after('whatsapp_opt_out');
                $table->index('campaign_opt_out', 'contacts_campaign_opt_out_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'campaign_opt_out')) {
                $table->dropIndex('contacts_campaign_opt_out_index');
                $table->dropColumn('campaign_opt_out');
            }
        });
    }
};



