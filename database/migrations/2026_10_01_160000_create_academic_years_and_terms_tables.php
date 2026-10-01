<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic years and terms (ACD-03).
 *
 * Every examination belongs to one term, and term status drives examination,
 * archive and billing logic, so this has to exist before examinations do.
 *
 * Tenant columns on both tables follow ADR-002: a personal teacher has their
 * own calendar, an institution shares one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // A label the teacher recognises: "2026" or "Academic Year 2026".
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One label per tenant. The two nullable columns cannot both be
            // constrained in a single index, so uniqueness is enforced in the
            // service for the personal branch and here for the institutional
            // one.
            $table->unique(['institution_id', 'name']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            // Ordering within the year. Term 1 before Term 2 before Term 3.
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('planned');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};
