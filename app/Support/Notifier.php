<?php

namespace App\Support;

use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Sends an in-app (database) notification to the company members who hold any of
 * the given roles — e.g. approvers for a pending document, HR for a leave request.
 */
final class Notifier
{
    /**
     * @param  list<string>  $roles
     * @return int number of recipients notified
     */
    public static function toCompanyRoles(int $companyId, array $roles, string $title, ?string $body = null, ?string $url = null): int
    {
        $users = User::query()
            ->whereHas('companies', fn ($q) => $q->whereKey($companyId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->get();

        if ($users->isEmpty()) {
            return 0;
        }

        $notification = Notification::make()->title($title)->info();

        if ($body !== null) {
            $notification->body($body);
        }

        if ($url !== null) {
            $notification->actions([Action::make('view')->url($url)->button()]);
        }

        $notification->sendToDatabase($users);

        return $users->count();
    }
}
