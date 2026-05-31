<?php
$duplicates = \App\Models\Payment::select('billing_id')
    ->groupBy('billing_id')
    ->havingRaw('COUNT(id) > 1')
    ->get();

foreach($duplicates as $dup) {
    $payments = \App\Models\Payment::where('billing_id', $dup->billing_id)
        ->orderBy('id', 'asc')
        ->get();
    
    // Skip the first one, delete the rest
    for($i = 1; $i < $payments->count(); $i++) {
        $payments[$i]->delete();
    }
}
echo "Duplicates removed!\n";
