<?php

namespace App\Http\Controllers;

use App\Models\Meditation;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeditationController extends Controller
{
    use ApiResponser;


    /**
     * Stores completed meditations
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $attr = $request->validate([
            'started_at' => 'required|numeric',
            'ended_at' => 'required|numeric|gte:started_at',
        ]);

        $user = Auth::user();
        $duration = $attr['ended_at'] - $attr['started_at'];

        $start = Carbon::createFromTimestampUTC($attr['started_at']);
        $end = Carbon::createFromTimestampUTC($attr['ended_at']);

        Meditation::create([
            'user_id' => $user->id,
            'duration' => $duration,
            'started_at' => $start,
            'ended_at' => $end
        ]);

        return $this->success([]);
    }

    /**
     * Returns meditation reports based on user preference: monthly or annually
     * @return JsonResponse
     */
    public function insights(): JsonResponse
    {
        $user = Auth()->user();
        $meditationReport = $user->getInsights();

        return $this->success($meditationReport);
    }

}
