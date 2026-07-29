<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_access_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');        // who receives the grant (typically a supervisor)
            $table->unsignedBigInteger('target_user_id'); // whose Task Manager tasks they can view/edit
            $table->unsignedBigInteger('granted_by');
            $table->timestamps();

            $table->unique(['user_id', 'target_user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_access_grants');
    }
};
