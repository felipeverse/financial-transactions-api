<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['transfer', 'deposit']);

            $table->unsignedBigInteger('payer_wallet_id');
            $table->foreign('payer_wallet_id')->references('id')->on('wallets');

            $table->unsignedBigInteger('payee_wallet_id');
            $table->foreign('payee_wallet_id')->references('id')->on('wallets');

            $table->bigInteger('amount');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
