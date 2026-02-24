<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBatchAndExpiryToPurchaseDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_details', 'batch_no')) {
                $table->string('batch_no')->nullable()->after('imei_number');
            }
            if (! Schema::hasColumn('purchase_details', 'expiry_date')) {
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
        Schema::table('purchase_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_details', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
            if (Schema::hasColumn('purchase_details', 'batch_no')) {
                $table->dropColumn('batch_no');
            }
        });
    }
}
