<?php

namespace App\Http\Controllers;

use App\Support\AccountAuthentication;
use App\Support\LiveSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSyncController extends Controller
{
    public function __invoke(Request $request, AccountAuthentication $accounts, LiveSync $sync): JsonResponse
    {
        if (! $request->is('api/*')) {
            $authenticated = session('owner_logged_in')
                || (session('waiter_logged_in') && $accounts->activeStaff((int) session('waiter_id'), 'Waiter') !== null)
                || (session('barista_logged_in') && $accounts->activeStaff((int) session('barista_id'), 'Barista') !== null);

            abort_unless($authenticated, 401, 'Unauthenticated.');
        }

        return response()->json(['data' => ['revision' => $sync->revision()]])
            ->header('Cache-Control', 'no-store, private');
    }
}
