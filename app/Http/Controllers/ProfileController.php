<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGeoguessrSettingsRequest;
use App\Models\Geoguesser;
use App\Services\Geoguessr\GeoguessrClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'geoguesser' => request()->user()->geoguesser,
        ]);
    }

    public function updateGeoguessr(UpdateGeoguessrSettingsRequest $request, GeoguessrClient $client): RedirectResponse
    {
        $ncfa = GeoguessrClient::normalizeNcfa((string) $request->validated('ncfa'));

        $geoguesser = Geoguesser::query()->firstOrNew(
            ['user_id' => $request->user()->id],
            ['username' => $request->user()->name],
        );

        $geoguesser->ncfa = $ncfa;

        try {
            $geoguesser->applyFromProfile($client->profile($ncfa));
            $geoguesser->is_active = true;
            $geoguesser->save();
        } catch (RequestException $exception) {
            $geoguesser->is_active = false;
            $geoguesser->save();

            Log::warning('GeoGuessr cookie test was rejected', [
                'user_id' => $request->user()->id,
                'status' => $exception->response?->status(),
            ]);

            return redirect()
                ->route('profile.edit')
                ->withInput()
                ->withErrors(['ncfa' => 'GeoGuessr rejected this _ncfa cookie. Copy the Value again from DevTools and try Test.']);
        } catch (ConnectionException $exception) {
            $geoguesser->save();

            Log::warning('GeoGuessr cookie test could not connect', [
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('profile.edit')
                ->withInput()
                ->withErrors(['ncfa' => 'This app server could not reach GeoGuessr. Run `php artisan serve` in your own terminal (not Cursor) and try Test again, or run `php artisan geoguessr:sync`.']);
        } catch (\Throwable $exception) {
            $geoguesser->save();

            Log::error('GeoGuessr cookie test failed', [
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('profile.edit')
                ->withInput()
                ->withErrors(['ncfa' => 'Could not verify the cookie. Check the log and try Test again.']);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'GeoGuessr is active. Profile data was saved.');
    }
}
