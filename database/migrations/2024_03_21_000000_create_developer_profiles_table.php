<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('bio');
            $table->decimal('hourly_rate', 8, 2);
            $table->string('github_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('email_visible')->default(false);
            $table->boolean('phone_visible')->default(false);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('developer_profile_skill', function (Blueprint $table) {
            $table->foreignId('developer_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_id')->constrained()->onDelete('cascade');
            $table->primary(['developer_profile_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_profile_skill');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('developer_profiles');
    }
}; 