<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les preferences de canal par type d'alerte (README section 5, ecran 25).
 */
class NotificationPreferenceController extends Controller
{
    /**
     * Show the channel of each alert type, the default one where the member never chose.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'preferences' => array_map(fn (NotificationType $type) => [
                'type' => $type->value,
                'label' => $type->label(),
                'channel' => $user->notificationChannelFor($type)->value,
            ], NotificationType::cases()),
            'channels' => array_map(fn (NotificationChannel $channel) => [
                'value' => $channel->value,
                'label' => $channel->label(),
            ], NotificationChannel::cases()),
        ]);
    }

    /**
     * Save the channel chosen for each alert type.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        foreach ($request->validated('preferences') as $type => $channel) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'type' => $type],
                ['channel' => $channel],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.preferences.saved')]);

        return to_route('notification-preferences.edit');
    }
}
