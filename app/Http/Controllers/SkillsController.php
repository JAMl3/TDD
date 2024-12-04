<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\JsonResponse;

class SkillsController extends Controller
{
    public function index(): JsonResponse
    {
        $skills = Skill::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'skills' => $skills
        ]);
    }
} 