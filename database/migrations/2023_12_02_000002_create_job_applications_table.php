<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_id')->constrained()->onDelete('cascade');
            $table->text('proposal');
            $table->string('timeline');
            $table->decimal('budget', 10, 2);
            $table->json('portfolio_items')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            // Ensure a developer can only apply once to a job
            $table->unique(['job_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_applications');
    }
}; 