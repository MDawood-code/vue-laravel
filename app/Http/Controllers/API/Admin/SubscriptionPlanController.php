<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup SubscriptionPlan
 *
 * @subgroupDescription APIs for managing SubscriptionPlan
 */
class SubscriptionPlanController extends Controller
{
    /**
     * Display a list of available subscription plans
     */
    public function getSingleInstance(): JsonResponse
    {
        // Initially we had two types of plans: basic and pro
        // But now we only have pro plan for this instance
        // $subscriptions_plans = SubscriptionPlan::where('is_trial', BOOLEAN_FALSE)
        //     ->orderBy('type')->get();

        // Now we have daily plan as well. If daily scheme is enabled, then only return daily subscription plan
        // otherwise, return monthly or annually plans
        $type = PLAN_TYPE_PRO;
        if (SystemSetting::first()->subscription_scheme === 'daily') {
            $type = PLAN_TYPE_DAILY;
        }

        $subscriptions_plans = SubscriptionPlan::where('is_trial', BOOLEAN_FALSE)
            ->where('type', $type)->get();

        return response()->json([
            'success' => true,
            'message' => 'Subscription Plans Object',
            'data' => [
                'subscription_plans' => SubscriptionPlanResource::collection($subscriptions_plans->keyBy('id')),
            ],
        ], 200);
    }

    /**
     * Update the specified subscription palan.
     */
    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $plan): JsonResponse
    {
        if ($plan->is_trial == true) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription Plan must be a non-trial plan.',
                'data' => [
                ],
            ], 400);
        }

        $plan->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Subscription Plans updated and new pricing will apply to future subscriptions.',
            'data' => [
                'subscription_plan' => new SubscriptionPlanResource($plan),
            ],
        ], 200);
    }
}
