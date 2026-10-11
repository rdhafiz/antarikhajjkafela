<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingsValidator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function show(string $group): JsonResponse
    {
        abort_unless(array_key_exists($group, $this->settings->schema()), 404);

        return response()->json([
            'data' => $this->settings->group($group),
            'schema' => $this->settings->schema($group),
        ]);
    }

    public function update(Request $request, string $group, SettingsValidator $validator): JsonResponse
    {
        abort_unless(array_key_exists($group, $this->settings->schema()), 404);

        $values = $request->validate($validator->rules($group));

        $this->settings->set($group, $values);

        activity('settings')
            ->causedBy($request->user())
            ->event('updated')
            ->withProperties(['group' => $group, 'keys' => array_keys($values)])
            ->log("Updated {$group} settings");

        return response()->json([
            'data' => $this->settings->group($group),
        ]);
    }
}
