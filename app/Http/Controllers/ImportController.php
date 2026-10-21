<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\MailList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use League\Csv\Reader;

class ImportController extends Controller
{
    public function create(MailList $list): View
    {
        return view('imports.create', [
            'list' => $list,
            'recent' => $list->imports()->latest()->limit(10)->get(),
        ]);
    }

    public function upload(Request $request, MailList $list): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:102400']);
        $file = $request->file('file');
        $path = $file->storeAs('imports', Str::random(32).'.csv');

        $import = $list->imports()->create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'total' => ProcessImport::countRows(Storage::path($path)),
        ]);

        return redirect()->route('lists.import.map', [$list, $import]);
    }

    public function map(MailList $list, Import $import): View|RedirectResponse
    {
        if ($import->status !== 'pending') {
            return redirect()->route('lists.import.show', [$list, $import]);
        }

        [$headers, $preview] = $this->peek($import);

        return view('imports.map', [
            'list' => $list,
            'import' => $import,
            'headers' => $headers,
            'preview' => $preview,
            'guesses' => $this->guess($headers, $list),
        ]);
    }

    public function start(Request $request, MailList $list, Import $import): RedirectResponse
    {
        abort_unless($import->status === 'pending', 409);
        $mapping = array_map(fn ($v) => (string) $v, $request->input('mapping', []));

        if (count(array_keys($mapping, 'EMAIL', true)) !== 1) {
            throw ValidationException::withMessages(['mapping' => 'Map exactly one column to Email.']);
        }

        $import->forceFill(['mapping' => $mapping, 'status' => 'queued'])->save();
        ProcessImport::dispatch($import->id);

        return redirect()->route('lists.import.show', [$list, $import]);
    }

    public function show(MailList $list, Import $import): View
    {
        return view('imports.show', compact('list', 'import'));
    }

    public function status(MailList $list, Import $import): JsonResponse
    {
        return response()->json($import->only(['status', 'total', 'processed', 'created_count', 'updated_count', 'failed_count'])
            + ['progress' => $import->progress(), 'errors' => $import->status === 'done' ? ($import->errors ?? []) : []]);
    }

    /** @return array{0: string[], 1: array<int, array<int, string>>} */
    protected function peek(Import $import): array
    {
        $path = Storage::path($import->path);
        $csv = Reader::createFromPath($path);
        $csv->setDelimiter(ProcessImport::detectDelimiter($path));

        $rows = [];
        foreach ($csv->getRecords() as $i => $row) {
            $rows[] = $row;
            if ($i >= 5) {
                break;
            }
        }
        $headers = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $rows[0] ?? []);

        return [$headers, array_slice($rows, 1)];
    }

    /** Auto-map columns whose header resembles a field label or tag. @return array<int, string> */
    protected function guess(array $headers, MailList $list): array
    {
        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower($s));
        $known = ['email' => 'EMAIL', 'emailaddress' => 'EMAIL', 'mail' => 'EMAIL', 'tags' => 'TAGS', 'tag' => 'TAGS',
            'firstname' => 'FIRST_NAME', 'fname' => 'FIRST_NAME', 'lastname' => 'LAST_NAME', 'lname' => 'LAST_NAME', 'surname' => 'LAST_NAME'];
        foreach ($list->fields as $field) {
            $known[$norm($field->label)] = $field->tag;
            $known[$norm($field->tag)] = $field->tag;
        }

        return array_map(fn ($h) => $known[$norm($h)] ?? '', $headers);
    }
}
