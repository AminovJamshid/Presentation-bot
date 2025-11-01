<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_telegram_id')->unique();

            $table->string('current_state', 50);

            $table->json('data')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->foreign('user_telegram_id')
                ->references('telegram_id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('user_telegram_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
