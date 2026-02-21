<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeddingSupplierCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class WeddingSupplierCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Same logic as Supplier: Admin or Enabled Company
        if ($user->isSuperAdmin()) {
            return true;
        }

        $company = $user->type === 'company' ? $user : $user->creator;
        if ($company && $company->type === 'company') {
            return $company->hasFeature('wedding_suppliers_module');
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WeddingSupplierCategory $weddingSupplierCategory): bool
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
    public function update(User $user, WeddingSupplierCategory $weddingSupplierCategory): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WeddingSupplierCategory $weddingSupplierCategory): bool
    {
        return $user->isSuperAdmin();
    }
}
