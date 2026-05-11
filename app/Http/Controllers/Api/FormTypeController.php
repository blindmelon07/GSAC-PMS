<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FormType::active();
        $request->whenFilled('search', fn ($v) => $query->where('name', 'like', "%{$v}%"));
        return response()->json($query->orderBy('name')->get());
    }

    public function show(FormType $formType): JsonResponse
    {
        return response()->json($formType);
    }
}
