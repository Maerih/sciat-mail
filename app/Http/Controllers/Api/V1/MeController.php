<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $workspace = Workspace::current();

        return response()->json(['data' => [
            'client' => ['uid' => $workspace->uid, 'name' => $workspace->name, 'timezone' => $workspace->timezone],
            'api_version' => 'v1',
        ]]);
    }
}
