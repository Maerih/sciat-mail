<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListResource;
use App\Models\MailList;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ListResource::collection(MailList::forWorkspace()->with('fields')->orderBy('name')->paginate(50));
    }

    public function show(MailList $list): ListResource
    {
        return new ListResource($list->load('fields'));
    }

    public function store(Request $request): JsonResponse
    {
        $list = MailList::create($this->validated($request) + ['workspace_id' => Workspace::current()->id]);

        return (new ListResource($list->load('fields')))->response()->setStatusCode(201);
    }

    public function update(Request $request, MailList $list): ListResource
    {
        $list->update($this->validated($request, partial: true));

        return new ListResource($list->load('fields'));
    }

    public function destroy(MailList $list): JsonResponse
    {
        $list->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:190'],
            'from_name' => [$required, 'string', 'max:190'],
            'from_email' => [$required, 'email'],
            'reply_to' => ['nullable', 'email'],
            'double_optin' => ['sometimes', 'boolean'],
            'send_welcome_email' => ['sometimes', 'boolean'],
        ]);
    }
}
