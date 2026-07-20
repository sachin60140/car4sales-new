<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_section_id')->constrained('inspection_sections')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('inspection_checklist_items')->nullOnDelete();
            $table->string('label');
            $table->string('value', 12)->default('na'); // ok|attention|fail|na
            $table->string('severity', 12)->nullable(); // minor|major|critical
            $table->text('remarks')->nullable();
            $table->decimal('repair_estimate', 14, 2)->default(0);
            $table->timestamps();

            $table->index('inspection_section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
