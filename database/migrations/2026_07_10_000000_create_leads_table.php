<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone');
            $table->unsignedInteger('monthly_bill_rm');   // whole ringgit
            $table->string('property_type');               // cast to PropertyType enum
            $table->string('roof_type');                   // cast to RoofType enum
            $table->string('state');
            $table->string('status')->index();             // cast to LeadStatus enum; filtered in Filament
            $table->timestamps();

            // Duplicate detection (requirement 3). A lead's identity =
            // who (email + phone) + what kind of property (type + roof) + where (state).
            // This DB constraint is the ONLY atomic dedup guarantee: because ingestion
            // is async, any read-then-write pre-check is a TOCTOU race. ProcessLeadJob
            // attempts the insert and catches the violation (Postgres SQLSTATE 23505).
            $table->unique(
                ['email', 'phone', 'property_type', 'roof_type', 'state'],
                'leads_identity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
