<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ApprovalRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovalRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_approval::request');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('view_approval::request');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_approval::request');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('update_approval::request');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('delete_approval::request');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_approval::request');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('force_delete_approval::request');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_approval::request');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('restore_approval::request');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_approval::request');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('replicate_approval::request');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_approval::request');
    }
}
