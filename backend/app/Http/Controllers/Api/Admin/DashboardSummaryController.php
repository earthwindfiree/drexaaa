<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardSummaryService;
use Illuminate\Http\JsonResponse;

class DashboardSummaryController extends Controller
{
    public function __invoke(AdminDashboardSummaryService $summaryService): JsonResponse
    {
        return response()->json([
            'data' => $summaryService->summarize(),
        ]);
    }
}
