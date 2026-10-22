<?php

namespace App\Http\Controllers;

use App\Models\MailList;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Sms\PhoneNumber;
use App\Sms\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Two-way SMS: conversations with contacts who reply. */
class SmsInboxController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = Workspace::current()->id;
        // Threads = phone numbers that have replied at least once, newest activity first
        $threads = SmsMessage::where('workspace_id', $workspaceId)
            ->whereIn('phone', SmsMessage::where('workspace_id', $workspaceId)->where('direction', 'in')->select('phone'))
            ->select('phone', DB::raw('max(id) as last_id'), DB::raw("sum(direction = 'in' and read_at is null) as unread"))
            ->groupBy('phone')->orderByDesc('last_id')->limit(200)->get();
        $last = SmsMessage::whereIn('id', $threads->pluck('last_id'))->get()->keyBy('id');

        $phone = $request->query('phone') ? PhoneNumber::normalize($request->query('phone')) : $threads->first()?->phone;
        $conversation = collect();
        $contact = null;
        if ($phone) {
            $conversation = SmsMessage::where('workspace_id', $workspaceId)->where('phone', $phone)->oldest('id')->limit(300)->get();
            SmsMessage::where('workspace_id', $workspaceId)->where('phone', $phone)->where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);
            $contact = Subscriber::with('list:id,uid,name')->whereIn('mail_list_id', MailList::forWorkspace()->select('id'))->where('phone', $phone)->first();
        }

        return view('sms.inbox', compact('threads', 'last', 'phone', 'conversation', 'contact'));
    }

    public function reply(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['phone' => 'required|string', 'message' => 'required|string|max:1600']);
        $workspace = Workspace::current();
        $phone = PhoneNumber::normalize($data['phone']) ?? abort(422);
        $lastProvider = SmsMessage::where('workspace_id', $workspace->id)->where('phone', $phone)->whereNotNull('sms_provider_id')->latest('id')->value('sms_provider_id');
        $provider = SmsProvider::availableTo($workspace)->where('status', 'active')->find($lastProvider) ?? SmsProvider::defaultFor($workspace);
        if (! $provider) {
            return back()->with('error', 'Add an SMS provider first.');
        }
        $subscriber = Subscriber::whereIn('mail_list_id', MailList::forWorkspace()->select('id'))->where('phone', $phone)->first();
        $sms->queue($workspace, $phone, $data['message'], 'reply', $subscriber, $provider);

        return redirect()->route('sms.inbox', ['phone' => $phone]);
    }
}
