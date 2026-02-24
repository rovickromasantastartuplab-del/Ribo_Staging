<?php
$company = \App\Models\User::where('type', 'company')->first();
if ($company) {
    echo "Company ID: " . $company->id . "\n";
    echo "Company Created By: " . $company->created_by . "\n";
    $users = \App\Models\User::where('created_by', $company->id)->get(['id', 'type', 'name']);
    echo "Users created by company (ID {$company->id}):\n";
    foreach ($users as $u) {
        echo "- ID: {$u->id}, Type: {$u->type}, Name: {$u->name}\n";
    }
} else {
    echo "No company found.\n";
}
