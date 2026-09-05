<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProcessOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_process::order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProcessOrder $processOrder): bool
    {
        return $user->can('view_process::order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_process::order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProcessOrder $processOrder): bool
    {
        return $user->can('update_process::order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcessOrder $processOrder): bool
    {
        return $user->can('delete_process::order');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_process::order');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ProcessOrder $processOrder): bool
    {
        return $user->can('force_delete_process::order');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_process::order');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ProcessOrder $processOrder): bool
    {
        return $user->can('restore_process::order');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_process::order');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ProcessOrder $processOrder): bool
    {
        return $user->can('replicate_process::order');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_process::order');
    }
}
