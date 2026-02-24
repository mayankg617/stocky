<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Unit;
use App\Models\product_warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

echo "Starting smoke test...\n";
$product = Product::whereNull('deleted_at')->first();
$warehouse = \App\Models\Warehouse::whereNull('deleted_at')->first();
$unit = Unit::first();
if (! $product || ! $warehouse) {
    echo "Missing product or warehouse in DB. Aborting.\n";
    exit(1);
}
$batchNo = 'SMOKE-'.uniqid();
$qty = 5;
$opv = 1;
if ($unit) { $opv = $unit->operator_value ?: 1; }
$addedQty = ($unit && $unit->operator == '/') ? ($qty / $opv) : ($qty * $opv);

// Insert purchase
$purchaseId = DB::table('purchases')->insertGetId([
    'date' => Carbon::now()->toDateString(),
    'time' => Carbon::now()->toTimeString(),
    'Ref' => 'SMOKE_PR_'.time(),
    'provider_id' => (\App\Models\Provider::first() ? \App\Models\Provider::first()->id : 1),
    'GrandTotal' => 0,
    'warehouse_id' => $warehouse->id,
    'tax_rate' => 0,
    'TaxNet' => 0,
    'discount' => 0,
    'shipping' => 0,
    'statut' => 'received',
    'payment_statut' => 'unpaid',
    'notes' => 'smoke test',
    'user_id' => (\App\Models\User::first() ? \App\Models\User::first()->id : 1),
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

$pw = product_warehouse::firstOrCreate([ 'warehouse_id' => $warehouse->id, 'product_id' => $product->id ], [ 'qte' => 0 ]);
$pw->qte += $addedQty;
$pw->save();

$batchId = DB::table('product_batches')->insertGetId([
    'product_id' => $product->id,
    'purchase_id' => $purchaseId,
    'batch_no' => $batchNo,
    'expiry_date' => Carbon::now()->addDays(20)->toDateString(),
    'quantity' => $addedQty,
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

DB::table('purchase_details')->insert([
    'purchase_id' => $purchaseId,
    'quantity' => $qty,
    'cost' => 0,
    'purchase_unit_id' => $unit ? $unit->id : null,
    'TaxNet' => 0,
    'tax_method' => 1,
    'discount' => 0,
    'discount_method' => 2,
    'product_id' => $product->id,
    'product_variant_id' => null,
    'total' => 0,
    'imei_number' => null,
    'batch_no' => $batchNo,
    'expiry_date' => Carbon::now()->addDays(20)->toDateString(),
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

echo "After purchase: product_warehouse.qte= {$pw->qte} | batch.quantity= ".DB::table('product_batches')->where('id', $batchId)->value('quantity')."\n";

// Create completed sale consuming part of the batch
$sellQty = 2;
$saleId = DB::table('sales')->insertGetId([
    'date' => Carbon::now()->toDateString(),
    'time' => Carbon::now()->toTimeString(),
    'Ref' => 'SMOKE_SA_'.time(),
    'client_id' => (\App\Models\Client::first() ? \App\Models\Client::first()->id : 1),
    'GrandTotal' => 0,
    'warehouse_id' => $warehouse->id,
    'tax_rate' => 0,
    'TaxNet' => 0,
    'discount' => 0,
    'shipping' => 0,
    'statut' => 'completed',
    'payment_statut' => 'unpaid',
    'notes' => 'smoke sale',
    'user_id' => (\App\Models\User::first() ? \App\Models\User::first()->id : 1),
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

$removedQty = ($unit && $unit->operator == '/') ? ($sellQty / $opv) : ($sellQty * $opv);
$pw->qte -= $removedQty;
$pw->save();
DB::table('product_batches')->where('id', $batchId)->update(['quantity' => DB::raw('GREATEST(quantity - '.(float)$removedQty.', 0)')]);

DB::table('sale_details')->insert([
    'sale_id' => $saleId,
    'date' => Carbon::now()->toDateString(),
    'sale_unit_id' => $unit ? $unit->id : null,
    'quantity' => $sellQty,
    'price' => 0,
    'TaxNet' => 0,
    'tax_method' => 1,
    'discount' => 0,
    'discount_method' => 2,
    'product_id' => $product->id,
    'product_variant_id' => null,
    'total' => 0,
    'imei_number' => null,
    'price_type' => 'retail',
    'batch_id' => $batchId,
    'batch_no' => $batchNo,
    'expiry_date' => Carbon::now()->addDays(20)->toDateString(),
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

echo "After sale: product_warehouse.qte= {$pw->qte} | batch.quantity= ".DB::table('product_batches')->where('id', $batchId)->value('quantity')."\n";

echo "Smoke test completed.\n";
