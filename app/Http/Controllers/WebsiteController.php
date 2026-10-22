<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\MailList;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function index(): View
    {
        $websites = Website::forWorkspace()->withCount(['forms', 'visits' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])
            ->latest()->paginate(20);

        return view('websites.index', compact('websites'));
    }

    public function create(): View
    {
        return view('websites.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $website = new Website(['name' => $data['name'] ?: Website::hostOf($data['url'])] + $data);
        $website->workspace_id = Workspace::current()->id;
        $website->save();

        return redirect()->route('websites.show', $website)->with('success', 'Website added. Install the tracking code below, then click Connect.');
    }

    public function show(Website $website): View
    {
        $since = now()->subDays(29)->startOfDay();
        $daily = $website->visits()->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as views, count(distinct visitor) as visitors')
            ->groupBy('day')->pluck('views', 'day');
        $chart = collect(range(29, 0))->mapWithKeys(fn ($d) => [now()->subDays($d)->toDateString() => (int) ($daily[now()->subDays($d)->toDateString()] ?? 0)]);

        $workspaceLists = MailList::forWorkspace()->select('id');

        return view('websites.show', [
            'website' => $website,
            'chart' => $chart,
            'stats' => [
                'views' => $chart->sum(),
                'visitors' => $website->visits()->where('created_at', '>=', $since)->distinct()->count('visitor'),
                'known' => $website->visits()->where('created_at', '>=', $since)->whereNotNull('subscriber_id')->distinct()->count('subscriber_id'),
                'submissions' => (int) $website->forms()->sum('submissions'),
            ],
            'topPages' => $website->visits()->where('created_at', '>=', $since)->select('path', DB::raw('count(*) as views'))
                ->groupBy('path')->orderByDesc('views')->limit(8)->get(),
            'contacts' => $website->visits()->with('subscriber.list:id,uid,name')->whereNotNull('subscriber_id')
                ->select('subscriber_id', DB::raw('count(*) as views'), DB::raw('max(created_at) as last_visit'))
                ->groupBy('subscriber_id')->orderByDesc('last_visit')->limit(10)->get(),
            'forms' => $website->forms()->with('list:id,uid,name')->get(),
            'availableForms' => Form::with('list:id,name')->whereIn('mail_list_id', $workspaceLists)
                ->where(fn ($q) => $q->whereNull('website_id')->orWhere('website_id', '!=', $website->id))->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        $data = $this->validated($request);
        $hostChanged = Website::hostOf($data['url']) !== $website->host;
        $website->update(['name' => $data['name'] ?: Website::hostOf($data['url']), 'track_visits' => $request->boolean('track_visits')] + $data);
        if ($hostChanged) {
            $website->forceFill(['status' => 'inactive', 'connected_at' => null])->save();
        }

        return back()->with('success', 'Website saved.');
    }

    public function destroy(Website $website): RedirectResponse
    {
        $website->delete();

        return redirect()->route('websites.index')->with('success', 'Website removed. Its forms still work as standalone embeds.');
    }

    /** Fetches the site's home page and looks for the tracking script. */
    public function verify(Website $website): RedirectResponse
    {
        $error = null;
        try {
            $response = Http::timeout(10)->withUserAgent('Mozilla/5.0 (compatible; SiteConnect/1.0)')->get($website->url);
            if (! $response->successful()) {
                $error = 'The site answered with HTTP '.$response->status().'.';
            } elseif (! str_contains($response->body(), '/site/'.$website->uid.'/connect.js')) {
                $error = 'The tracking code was not found on '.$website->url.'. Paste it before </body> and publish the change.';
            }
        } catch (\Throwable $e) {
            $error = 'Could not reach '.$website->url.': '.$e->getMessage();
        }

        $website->forceFill(['checked_at' => now(), 'check_error' => $error ? mb_substr($error, 0, 250) : null])->save();
        if ($error) {
            return back()->with('error', $error);
        }
        $website->forceFill(['status' => 'connected', 'connected_at' => $website->connected_at ?? now()])->save();

        return back()->with('success', 'Connected! The tracking code is live on '.$website->host.'.');
    }

    public function connectForm(Request $request, Website $website): RedirectResponse
    {
        $request->validate(['form' => 'required|string']);
        $form = Form::where('uid', $request->form)->whereIn('mail_list_id', MailList::forWorkspace()->select('id'))->firstOrFail();
        $form->website()->associate($website)->save();

        return back()->with('success', '“'.$form->name.'” is connected to '.$website->name.'.');
    }

    public function disconnectForm(Website $website, string $form): RedirectResponse
    {
        $website->forms()->where('uid', $form)->update(['website_id' => null]);

        return back()->with('success', 'Form disconnected.');
    }

    /** @return array{name: ?string, url: string, track_visits?: bool} */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:255'],
            'name' => 'nullable|string|max:120',
        ]);

        return ['url' => rtrim($data['url'], '/'), 'name' => $data['name'] ?? null];
    }
}
