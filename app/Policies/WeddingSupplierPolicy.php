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

        if ($user->type === 'company' || $user->type === 'company_user') { // Assuming 'company_user' exists or similar logic
            // For company users, check if their company (creator) has the feature
            $company = $user->type === 'company' ? $user : $user->creator;
            return $company && $company->hasFeature('wedding_suppliers_module');
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
