<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Image and file library for emails. Shares its folder with the drag-and-drop builder's uploads. */
class AssetController extends Controller
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function folder(): string
    {
        return 'uploads/'.Workspace::current()->uid;
    }

    public function index(Request $request): View
    {
        $disk = Storage::disk('public');
        $assets = collect($disk->files(static::folder()))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'url' => url($disk->url($path)),
                'size' => $disk->size($path),
                'modified' => $disk->lastModified($path),
                'image' => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true),
            ])
            ->when($request->type === 'images', fn ($c) => $c->where('image', true))
            ->when($request->type === 'files', fn ($c) => $c->where('image', false))
            ->sortByDesc('modified')->values();

        return view('assets.index', ['assets' => $assets, 'totalSize' => $assets->sum('size')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['files' => 'required|array|max:20', 'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240']);
        foreach ($request->file('files') as $file) {
            $file->storeAs(static::folder(), Str::random(24).'.'.$file->extension(), 'public');
        }

        return back()->with('success', count($request->file('files')).' '.Str::plural('file', count($request->file('files'))).' uploaded.');
    }

    public function destroy(string $name): RedirectResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]+\.[a-z0-9]{2,5}$/', $name), 404);
        Storage::disk('public')->delete(static::folder().'/'.$name);

        return back()->with('success', 'File deleted. Emails already sent keep working only while the file exists — check none still use it.');
    }
}
