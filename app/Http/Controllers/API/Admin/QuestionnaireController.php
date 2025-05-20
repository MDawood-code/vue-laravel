<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionnaireRequest;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Company;
use App\Models\Questionnaire;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup Questionnaire
 *
 * @subgroupDescription APIs for managing Questionnaire
 */
class QuestionnaireController extends Controller
{
    // /**
    //  * Display a listing of the resource.
    //  */
    // public function index(): JsonResponse
    // {
    //     //
    // }

    /**
     * Store or update resource in storage.
     */
    public function store(QuestionnaireRequest $request, Company $company): JsonResponse
    {
        $this->authorize('create', [Questionnaire::class, $company]);

        $questionnaire = Questionnaire::updateOrCreate(
            ['company_id' => $company->id],
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire updated successfully.',
            'data' => new QuestionnaireResource($questionnaire),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function companyQuestionnaire(Company $company): JsonResponse
    {
        $this->authorize('view', [Questionnaire::class, $company]);

        $questionnaire = $company->questionnaire;

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire',
            'data' => $questionnaire ? new QuestionnaireResource($questionnaire) : null,
        ]);
    }
}
