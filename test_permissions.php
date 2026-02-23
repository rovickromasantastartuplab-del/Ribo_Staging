<?php
$user = \App\Models\User::where('type', 'superadmin')->first();
$canCreateCategory = $user ? $user->can('create', \App\Models\WeddingSupplierCategory::class) : false;
echo "Is SuperAdmin: " . ($user && $user->isSuperAdmin() ? 'true' : 'false') . "\n";
echo "Can Create Category: " . ($canCreateCategory ? 'true' : 'false') . "\n";
