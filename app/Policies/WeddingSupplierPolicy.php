<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeddingSupplier;
use Illuminate\Auth\Access\HandlesAuthorization;

class WeddingSupplierPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // For company owners and their staff, allow viewing if feature is enabled
        $company = $user->type === 'company' ? $user : $user->creator;
        if ($company && $company->type === 'company') {
            return $company->hasFeature('wedding_suppliers_module');
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WeddingSupplier $weddingSupplier): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WeddingSupplier $weddingSupplier): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WeddingSupplier $weddingSupplier): bool
    {
        return $user->isSuperAdmin();
    }
}
