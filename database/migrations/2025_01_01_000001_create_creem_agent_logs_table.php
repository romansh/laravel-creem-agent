<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creem_agent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('store');
            $table->string('type'); // heartbeat, workflow, chat, error
            $table->json('data')->nullable();
            $table->integer('changes_count')->default(0);
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();

            $table->index(['store', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creem_agent_logs');
    }
};
