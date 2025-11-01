<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_telegram_id');

            $table->string('university', 500);
            $table->string('direction', 500);
            $table->string('group_name', 100);

            $table->enum('info_placement', ['first', 'last']);

            $table->string('topic', 1000);
            $table->integer('pages_count');

            $table->enum('format', ['pptx', 'pdf', 'docx', 'doc']);

            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])
                ->default('pending');

            $table->text('file_path')->nullable();      // Fayl qayerda?
            $table->integer('file_size')->nullable();   // Hajmi (bytes)

            $table->text('error_message')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('user_telegram_id')
                ->references('telegram_id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('user_telegram_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
