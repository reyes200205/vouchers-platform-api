<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->boolean('unread_only'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success($notifications);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($notification->notifiable_id !== $user->id || $notification->notifiable_type !== $user->getMorphClass()) {
            return $this->forbidden();
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $this->success($notification->fresh());
    }
}
