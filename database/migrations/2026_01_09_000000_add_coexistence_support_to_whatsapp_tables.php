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
        // Add coexistence fields to contacts table
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_on_biz_app')->default(false)->after('campaign_opt_out')->comment('Whether contact uses WhatsApp Business App with this number');
            $table->string('platform_type', 50)->nullable()->after('is_on_biz_app')->comment('CLOUD_API or null');
            $table->timestamp('smb_synced_at')->nullable()->after('platform_type')->comment('Last time SMB contacts were synced');
        });

        // Create table for tracking SMB sync operations
        Schema::create('whatsapp_smb_sync_logs', function (Blueprint $table) {
            $table->id('_id');
            $table->char('_uid', 36)->unique();
            $table->unsignedBigInteger('vendors__id');
            $table->unsignedBigInteger('phone_number_id')->nullable()->comment('WhatsApp Business Phone Number ID');
            $table->enum('sync_type', ['contacts', 'history'])->comment('Type of sync: contacts or history');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('request_id')->nullable()->comment('Meta API request ID for tracking');
            $table->integer('phase')->nullable()->comment('History sync phase (0, 1, 2)');
            $table->integer('chunk_order')->nullable()->comment('History sync chunk order');
            $table->integer('progress')->nullable()->comment('Sync progress percentage (0-100)');
            $table->integer('total_items')->default(0)->comment('Total items synced (contacts or messages)');
            $table->json('sync_data')->nullable()->comment('Additional sync metadata');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('vendors__id');
            $table->index('sync_type');
            $table->index('status');
            $table->index(['vendors__id', 'sync_type', 'status']);
        });

        // Add coexistence tracking to vendor settings (stored in __data JSON)
        // This will track: is_whatsapp_business_app_onboarding, smb_sync_status, etc.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['is_on_biz_app', 'platform_type', 'smb_synced_at']);
        });

        Schema::dropIfExists('whatsapp_smb_sync_logs');
    }
};
