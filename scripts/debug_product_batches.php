<?php
require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/database.php';
use Illuminate\Database\Capsule\Manager as Capsule;
$capsule = new Capsule;
$capsule->addConnection($config['connections']['mysql']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

function dumpBatches($productId, $warehouseId) {
    $rows = Capsule::table('product_batches')
        ->join('purchases','product_batches.purchase_id','=','purchases.id')
        ->where('product_batches.product_id', $productId)
        ->where('purchases.warehouse_id', $warehouseId)
        ->whereRaw('product_batches.quantity > 0')
        ->whereNull('purchases.deleted_at')
        ->whereNull('product_batches.deleted_at')
        ->select('product_batches.id','product_batches.batch_no','product_batches.expiry_date','product_batches.quantity')
        ->orderByRaw("CASE WHEN product_batches.expiry_date IS NULL THEN 1 ELSE 0 END, product_batches.expiry_date ASC")
        ->get();

    echo json_encode(array_map(function($r){return (array)$r;}, iterator_to_array($rows)), JSON_PRETTY_PRINT);
}

// Accept product and warehouse as CLI args
$productId = $argv[1] ?? 11;
$warehouseId = $argv[2] ?? 1;
dumpBatches($productId, $warehouseId);
