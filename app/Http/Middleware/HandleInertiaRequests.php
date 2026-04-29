<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'app' => fn () => $this->appSettings(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'upload_result' => fn () => $request->session()->get('upload_result'),
            ],
        ];
    }

    private function appSettings(): array
    {
        $settings = Setting::query()
            ->whereIn('key', ['site_name', 'site_tagline'])
            ->pluck('value', 'key');

        return [
            'name' => (string) ($settings['site_name'] ?? 'Snaporia'),
            'tagline' => (string) ($settings['site_tagline'] ?? 'Find Your Moments.'),
        ];
    }
}
