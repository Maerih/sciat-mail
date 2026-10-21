<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AutomationResource;
use App\Models\Automation;
use App\Services\Automation\Engine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AutomationResource::collection(Automation::forWorkspace()->with('list')->latest()->paginate(50));
    }

    /** Starts an active automation for a contact of its list, e.g. after a purchase in your shop. */
    public function enroll(Request $request, Automation $automation, Engine $engine): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        abort_unless($automation->isActive(), 409, 'The automation is not active.');

        $subscriber = $automation->list->subscribers()->where('email', strtolower($data['email']))->first();
        abort_unless($subscriber, 404, 'No contact with that email on the automation\'s list. Add the contact first.');

        $run = $engine->enroll($automation, $subscriber, $automation->allow_reentry ? '' : 'api');

        return response()->json(['enrolled' => (bool) $run, 'message' => $run ? 'Contact enrolled.' : 'Contact is already on this journey.'], $run ? 201 : 200);
    }
}
