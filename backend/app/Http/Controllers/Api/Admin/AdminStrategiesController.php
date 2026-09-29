<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Strategy;
use App\Services\AdminStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminStrategiesController extends Controller
{
    public function update(Request $request, Strategy $strategy, AdminStrategyService $strategyService): JsonResponse
    {
        $allowedFields = ['name', 'description', 'risk_profile', 'active'];

        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages([
                'strategy' => ['Only supported strategy configuration fields may be changed.'],
            ]);
        }

        $changes = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'risk_profile' => ['sometimes', 'required', 'string', 'max:120'],
            'active' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'strategy' => ['At least one strategy configuration field is required.'],
            ]);
        }

        $strategy = $strategyService->update($strategy, $request->user(), $changes);

        return response()->json(['data' => $this->serializeStrategy($strategy)]);
    }

    private function serializeStrategy(Strategy $strategy): array
    {
        return [
            'id' => $strategy->id,
            'name' => $strategy->name,
            'description' => $strategy->description,
            'risk_profile' => $strategy->risk_profile,
            'active' => $strategy->active,
        ];
    }
}
