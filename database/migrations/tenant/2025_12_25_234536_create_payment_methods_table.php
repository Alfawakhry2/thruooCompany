<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // Payment Method Type
            $table->enum('type', ['bank_transfer', 'cash', 'credit_card', 'paypal', 'other'])->default('bank_transfer');

            // Bank Details (for bank_transfer)
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();

            // General Details
            $table->string('name'); // Display name
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
};
