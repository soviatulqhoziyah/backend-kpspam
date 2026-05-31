<?php
$replacements = [
  'January' => 'Januari',
  'February' => 'Februari',
  'March' => 'Maret',
  'April' => 'April',
  'May' => 'Mei',
  'June' => 'Juni',
  'July' => 'Juli',
  'August' => 'Agustus',
  'September' => 'September',
  'October' => 'Oktober',
  'November' => 'November',
  'December' => 'Desember'
];
foreach(\App\Models\Billing::all() as $b) {
    $original = $b->periode;
    $new = strtr($original, $replacements);
    if ($original !== $new) {
        $b->periode = $new;
        $b->save();
        echo "Updated $original to $new\n";
    }
}
echo "All updated!\n";
