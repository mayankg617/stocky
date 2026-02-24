<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBatchAndExpiryToPurchasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'batch_no')) {
                $table->string('batch_no')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('purchases', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('batch_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
            if (Schema::hasColumn('purchases', 'batch_no')) {
                $table->dropColumn('batch_no');
            }
        });
    }
}
