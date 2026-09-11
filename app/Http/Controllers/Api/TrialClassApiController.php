<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrialClassResource;
use App\Models\TrialClass;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrialClassApiController extends Controller
{
    /**
     * Display a listing of available trial classes.
     */
    public function index(): AnonymousResourceCollection
    {
        $classes = TrialClass::where('is_active', true)
            ->orderBy('schedule_date')
            ->orderBy('schedule_time')
            ->get();

        return TrialClassResource::collection($classes);
    }

    /**
     * Display details of a specific trial class.
     */
    public function show(TrialClass $trialClass): TrialClassResource
    {
        return new TrialClassResource($trialClass);
    }

    /**
     * Display confirmed roster of a trial class by eager loading bookings.
     */
    public function roster(TrialClass $trialClass): TrialClassResource
    {
        $trialClass->load(['bookings.student', 'bookings.parent']);

        return new TrialClassResource($trialClass);
    }
}
