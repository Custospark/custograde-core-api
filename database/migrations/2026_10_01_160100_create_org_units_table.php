<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic hierarchy (ACD-02).
 *
 * One typed adjacency list serves both institution shapes rather than separate
 * school and university tables:
 *
 *   Secondary school: faculty > class
 *   University:       faculty > department > programme > class
 *
 * The requirement names faculty, department, programme, course unit and class
 * or stream. Course units are a separate table because they carry marks,
 * grading and teacher assignments and are referenced by examinations, so they
 * are not org units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Self-referencing adjacency list. NULL is a root.
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();

            $table->string('type');
            $table->string('name');
            // Short label used in compact selectors: "S4B", "F01".
            $table->string('code')->nullable();

            // Denormalised depth, kept for cheap ordering and cycle checks.
            $table->unsignedSmallInteger('depth')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'parent_id']);
            $table->index(['institution_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
