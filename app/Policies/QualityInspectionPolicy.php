<?php

namespace App\Policies;

use App\Models\User;
use App\Models\QualityInspection;
use Illuminate\Auth\Access\HandlesAuthorization;

class QualityInspectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_quality::inspection');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, QualityInspection $qualityInspection): bool
    {
        return $user->can('view_quality::inspection');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_quality::inspection');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QualityInspection $qualityInspection): bool
    {
        return $user->can('update_quality::inspection');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QualityInspection $qualityInspection): bool
    {
        return $user->can('delete_quality::inspection');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_quality::inspection');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, QualityInspection $qualityInspection): bool
    {
        return $user->can('force_delete_quality::inspection');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_quality::inspection');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, QualityInspection $qualityInspection): bool
    {
        return $user->can('restore_quality::inspection');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_quality::inspection');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, QualityInspection $qualityInspection): bool
    {
        return $user->can('replicate_quality::inspection');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_quality::inspection');
    }
}
