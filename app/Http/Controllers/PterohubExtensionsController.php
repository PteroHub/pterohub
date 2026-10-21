<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PterohubExtensionsController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403);
    }

    public function index()
    {
        $this->guard();
        $extensions = DB::table('pterohub_extensions')->orderBy('type')->orderBy('name')->get();
        return view('pterohub.extensions.index', compact('extensions'));
    }

    private function checkedManifest(string $raw): array
    {
        $value = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($value) || array_is_list($value)) {
            throw ValidationException::withMessages(['manifest' => 'Manifest must be a JSON object.']);
        }
        $allowed = ['slug', 'name', 'version', 'type', 'provider', 'description'];
        $unknown = array_diff(array_keys($value), $allowed);
        if ($unknown) {
            throw ValidationException::withMessages(['manifest' => 'Unsupported fields: '.implode(', ', $unknown).'. Never include credentials in manifests.']);
        }
        $validator = Validator::make($value, [
            'slug' => ['required','string','regex:/^[a-z0-9][a-z0-9-]{1,63}$/D'],
            'name' => ['required','string','max:150'],
            'version' => ['required','string','max:40','regex:/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.-]+)?$/D'],
            'type' => ['required','in:payment,theme,integration,notification,report'],
            'provider' => ['nullable','string','max:150'],
            'description' => ['nullable','string','max:500'],
        ]);
        if ($validator->fails()) throw new ValidationException($validator);
        return $validator->validated();
    }

    public function register(Request $request)
    {
        $this->guard();
        $data = $request->validate(['manifest' => 'required|string|max:8192']);
        $manifest = $this->checkedManifest($data['manifest']);
        if (DB::table('pterohub_extensions')->where('slug', $manifest['slug'])->exists()) {
            throw ValidationException::withMessages(['manifest' => 'Extension slug already registered.']);
        }
        DB::table('pterohub_extensions')->insert([
            'slug' => $manifest['slug'], 'name' => $manifest['name'],
            'version' => $manifest['version'], 'type' => $manifest['type'],
            'provider' => $manifest['provider'] ?? null,
            'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'enabled' => false, 'status' => 'registered', 'created_at' => now(), 'updated_at' => now()
        ]);
        Log::info('PteroHub extension manifest registered', ['slug' => $manifest['slug'], 'actor' => auth()->id()]);
        return redirect()->route('pterohub.extensions.index')->with('success', 'Manifest registered. Execution remains disabled.');
    }

    public function update(Request $request, int $id)
    {
        $this->guard();
        $data = $request->validate(['manifest' => 'required|string|max:8192']);
        $manifest = $this->checkedManifest($data['manifest']);
        $existing = DB::table('pterohub_extensions')->where('id', $id)->first();
        abort_unless($existing, 404);
        if ($manifest['slug'] !== $existing->slug || $manifest['type'] !== $existing->type) {
            throw ValidationException::withMessages(['manifest' => 'Slug and extension type cannot change. Remove and register a new entry if necessary.']);
        }
        DB::table('pterohub_extensions')->where('id', $id)->update([
            'name' => $manifest['name'], 'version' => $manifest['version'],
            'provider' => $manifest['provider'] ?? null, 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'enabled' => false, 'status' => 'registered', 'updated_at' => now()
        ]);
        Log::info('PteroHub extension manifest updated', ['slug' => $existing->slug, 'actor' => auth()->id()]);
        return redirect()->route('pterohub.extensions.index')->with('success', 'Manifest updated. Execution remains disabled.');
    }

    public function remove(int $id)
    {
        $this->guard();
        $existing = DB::table('pterohub_extensions')->where('id', $id)->first();
        abort_unless($existing, 404);
        // This module only removes registry metadata. It never uninstalls executable code.
        abort_if((bool) $existing->enabled, 409, 'An enabled extension cannot be removed.');
        DB::table('pterohub_extensions')->where('id', $id)->delete();
        Log::info('PteroHub extension manifest removed', ['slug' => $existing->slug, 'actor' => auth()->id()]);
        return redirect()->route('pterohub.extensions.index')->with('success', 'Registration removed. No plugin code was executed.');
    }
}
