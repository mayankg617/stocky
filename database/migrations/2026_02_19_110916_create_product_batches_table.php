<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
        $table->id();

        $table->integer('product_id')->unsigned();
        $table->unsignedBigInteger('purchase_id')->nullable();

        $table->string('batch_no');
        $table->date('expiry_date')->nullable();

        $table->decimal('quantity', 15, 2)->default(0);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
