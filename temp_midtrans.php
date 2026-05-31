<?php
$orderId = 'INV-1779877661-1';
\Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
\Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
try {
    $statusResponse = \Midtrans\Transaction::status($orderId);
    echo json_encode($statusResponse);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
