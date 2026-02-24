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

echo "Starting edit/delete smoke test...\n";
$product = Product::whereNull('deleted_at')->first();
$warehouse = \App\Models\Warehouse::whereNull('deleted_at')->first();
$unit = Unit::first();
if (! $product || ! $warehouse) {
    echo "Missing product or warehouse in DB. Aborting.\n";
    exit(1);
}
$batchNo = 'SMOKE2-'.uniqid();
$qty = 6; // purchase 6 units
$opv = 1;
if ($unit) { $opv = $unit->operator_value ?: 1; }
$addedQty = ($unit && $unit->operator == '/') ? ($qty / $opv) : ($qty * $opv);

// Insert purchase
$purchaseId = DB::table('purchases')->insertGetId([
    'date' => Carbon::now()->toDateString(),
    'time' => Carbon::now()->toTimeString(),
    'Ref' => 'SMOKE2_PR_'.time(),
    'provider_id' => (\App\Models\Provider::first() ? \App\Models\Provider::first()->id : 1),
    'GrandTotal' => 0,
    'warehouse_id' => $warehouse->id,
    'tax_rate' => 0,
    'TaxNet' => 0,
    'discount' => 0,
    'shipping' => 0,
    'statut' => 'received',
    'payment_statut' => 'unpaid',
    'notes' => 'smoke test 2',
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
    'expiry_date' => Carbon::now()->addDays(40)->toDateString(),
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
    'expiry_date' => Carbon::now()->addDays(40)->toDateString(),
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

echo "After purchase: product_warehouse.qte= {$pw->qte} | batch.quantity= ".DB::table('product_batches')->where('id', $batchId)->value('quantity')."\n";

// Create completed sale consuming 2 units
$sellQty = 2;
$saleId = DB::table('sales')->insertGetId([
    'date' => Carbon::now()->toDateString(),
    'time' => Carbon::now()->toTimeString(),
    'Ref' => 'SMOKE2_SA_'.time(),
    'client_id' => (\App\Models\Client::first() ? \App\Models\Client::first()->id : 1),
    'GrandTotal' => 0,
    'warehouse_id' => $warehouse->id,
    'tax_rate' => 0,
    'TaxNet' => 0,
    'discount' => 0,
    'shipping' => 0,
    'statut' => 'completed',
    'payment_statut' => 'unpaid',
    'notes' => 'smoke sale 2',
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
    'expiry_date' => Carbon::now()->addDays(40)->toDateString(),
    'created_at' => Carbon::now(),
    'updated_at' => Carbon::now(),
]);

echo "After initial sale: product_warehouse.qte= {$pw->qte} | batch.quantity= ".DB::table('product_batches')->where('id', $batchId)->value('quantity')."\n";

// --- Edit sale: change sale quantity from 2 -> 4
$newQty = 4;
// Step 1: restore old sale detail (2) back to warehouse & batch
$restoreQty = ($unit && $unit->operator == '/') ? (2 / $opv) : (2 * $opv);
$pw->qte += $restoreQty;
$pw->save();
DB::table('product_batches')->where('id', $batchId)->update(['quantity' => DB::raw('GREATEST(quantity + '.(float)$restoreQty.', 0)')]);

// Step 2: apply new sale detail (4)
$newRemoved = ($unit && $unit->operator == '/') ? ($newQty / $opv) : ($newQty * $opv);
$pw->qte -= $newRemoved;
$pw->save();
DB::table('product_batches')->where('id', $batchId)->update(['quantity' => DB::raw('GREATEST(quantity - '.(float)$newRemoved.', 0)')]);

DB::table('sale_details')->where('sale_id', $saleId)->update(['quantity' => $newQty]);

echo "After edit (2->4): product_warehouse.qte= {$pw->qte} | batch.quantity= ".DB::table('product_batches')->where('id', $batchId)->value('quantity')."\n";

// --- Delete sale: restore current sale qty (4)
$curQty = $newQty;
$restoreCur = ($unit && $unit->operator == '/') ? ($curQty / $opv) : ($curQty * $opv);
$pw->qte += $restoreCur;
$pw->save();
DB::table('product_batches')->where('id', $batchId)->update(['quantity' => DB::raw('GREATEST(quantity + '.(float)$restoreCur.', 0)')]);

// Soft-delete
DB::table('sale_details')->where('sale_id', $saleId)->delete();
DB::table('sales')->where('id', $saleId)->update(['deleted_at' => Carbon::now()]);

echo "After delete: product_warehouse.qte= {$pw->qte} | batch.quantity= ".DB::table('product_batches')->where('id', $batchId)->value('quantity')."\n";

echo "Edit/delete smoke test completed.\n";
