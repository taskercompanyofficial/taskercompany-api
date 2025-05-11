<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappWebhookEventsTable extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable(); // Type of event, e.g., "messages", "statuses"
            $table->json('event_data');              // Store raw JSON data
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
}
