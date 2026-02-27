<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\Product;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Models\Account;
use App\Models\Contact;

echo "=== Testing Invoice Stock Deduction ===\n";

DB::beginTransaction();

try {
    // 1. Get a user
    $user = User::where('type', 'company')->first();

    // 2. Create a test product
    $product = Product::create([
        'name' => 'Stock Test Product ' . uniqid(),
        'sku' => 'TEST-' . uniqid(),
        'price' => 100.00,
        'stock_quantity' => 50, // Starting with 50
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    echo "Created Product: {$product->id} | Initial Stock: {$product->stock_quantity}\n";

    // 3. Create an invoice
    $invoice = Invoice::create([
        'name' => 'Test Invoice ' . uniqid(),
        'invoice_number' => 'INV-TEST-' . uniqid(),
        'created_by' => $user->id,
        'status' => 'draft',
        'subtotal' => 300.00,
        'total_amount' => 300.00,
        'invoice_date' => now(),
        'due_date' => now()->addDays(7),
    ]);

    echo "Created Invoice: {$invoice->id} | Initial Status: {$invoice->status} | Stock Deducted Flag: " . ($invoice->stock_deducted ? 'true' : 'false') . "\n";

    // Attach product (quantity 3)
    $invoice->products()->attach($product->id, [
        'quantity' => 3,
        'unit_price' => 100.00,
        'total_price' => 300.00,
    ]);

    // 4. Make a partial payment (should NOT deduct)
    echo "\nApplying PARTIAL payment of 100...\n";
    InvoicePayment::storePayment([
        'invoice_id' => $invoice->id,
        'amount' => 100.00,
        'payment_type' => 'partial',
        'payment_method' => 'cash',
        'payment_id' => 'test_1'
    ]);

    $invoice->refresh();
    $product->refresh();

    echo "  Invoice Status after partial: {$invoice->status}\n";
    echo "  Invoice stock_deducted flag: " . ($invoice->stock_deducted ? 'true' : 'false') . "\n";
    echo "  Product Stock (should still be 50): {$product->stock_quantity}\n";

    if ($product->stock_quantity != 50) {
        throw new Exception("Stock was deducted early on partial payment!");
    }

    // 5. Make full payment (SHOULD deduct)
    echo "\nApplying REMAINING payment of 200...\n";
    InvoicePayment::storePayment([
        'invoice_id' => $invoice->id,
        'amount' => 200.00,
        'payment_type' => 'full',
        'payment_method' => 'cash',
        'payment_id' => 'test_2'
    ]);

    $invoice->refresh();
    $product->refresh();

    echo "  Invoice Status after full payment: {$invoice->status}\n";
    echo "  Invoice stock_deducted flag: " . ($invoice->stock_deducted ? 'true' : 'false') . "\n";
    echo "  Product Stock (should be 47): {$product->stock_quantity}\n";

    if ($product->stock_quantity != 47) {
        throw new Exception("Stock was NOT deducted correctly! Expected 47, got {$product->stock_quantity}");
    }

    // 6. Idempotency test (apply another payment, should NOT deduct again)
    echo "\nApplying AN EXTRA payment to test double-deduction prevention...\n";
    $invoice->updatePaymentStatus(); // Manual trigger

    $product->refresh();
    echo "  Product Stock (should remain 47): {$product->stock_quantity}\n";

    if ($product->stock_quantity != 47) {
        throw new Exception("Stock was double deducted! Got {$product->stock_quantity}");
    }

    echo "\n✅ ALL TESTS PASSED SUCCESSFULLY!\n";

} catch (\Exception $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
} finally {
    // Rollback so we don't leave test data
    DB::rollBack();
    echo "Rolled back test data.\n";
}
