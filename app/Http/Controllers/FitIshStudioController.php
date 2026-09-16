<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyFitIshStudioRequest;
use App\Http\Requests\StoreFitIshStudioRequest;
use App\Http\Requests\UpdateFitIshStudioRequest;
use App\Models\FitIshStudio;
use Illuminate\Http\RedirectResponse;

class FitIshStudioController extends Controller
{
    public function store(StoreFitIshStudioRequest $request): RedirectResponse
    {
        $studio = FitIshStudio::query()->create([
            'external_id' => $request->integer('studio_id'),
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'timezone' => $request->validated('timezone'),
            'is_loaner' => $request->boolean('is_loaner'),
        ]);

        $request->user()->fitIshStudios()->syncWithoutDetaching([$studio->id]);

        return redirect()
            ->route('profile.edit', ['tab' => 'fit-ish'])
            ->with('status', $studio->name.' was added to Fit-Ish.');
    }

    public function update(UpdateFitIshStudioRequest $request, FitIshStudio $fitIshStudio): RedirectResponse
    {
        $fitIshStudio->update([
            'external_id' => $request->integer('studio_id'),
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'timezone' => $request->validated('timezone'),
            'is_loaner' => $request->boolean('is_loaner'),
        ]);

        return redirect()
            ->route('profile.edit', ['tab' => 'fit-ish'])
            ->with('status', $fitIshStudio->name.' was updated.');
    }

    public function destroy(DestroyFitIshStudioRequest $request, FitIshStudio $fitIshStudio): RedirectResponse
    {
        $name = $fitIshStudio->name;
        $fitIshStudio->delete();

        return redirect()
            ->route('profile.edit', ['tab' => 'fit-ish'])
            ->with('status', $name.' was removed.');
    }
}
