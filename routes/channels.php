<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    if ($user->type === 'superadmin' || $user->type === 'super admin') {
        return true;
    }
    return (int) $user->creatorId() === (int) $companyId;
});
