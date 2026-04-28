<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const DEFINITIONS = [
        'site_name' => [
            'label' => 'Nama aplikasi',
            'description' => 'Nama yang tampil pada aplikasi.',
            'type' => 'text',
            'rules' => ['required', 'string', 'max:100'],
        ],
        'site_tagline' => [
            'label' => 'Tagline',
            'description' => 'Tagline singkat aplikasi.',
            'type' => 'text',
            'rules' => ['nullable', 'string', 'max:150'],
        ],
        'public_gallery_per_page' => [
            'label' => 'Foto galeri publik per halaman',
            'description' => 'Jumlah foto pada galeri publik.',
            'type' => 'number',
            'rules' => ['required', 'integer', 'min:6', 'max:60'],
        ],
        'dashboard_table_per_page' => [
            'label' => 'Data tabel dashboard per halaman',
            'description' => 'Jumlah baris default untuk tabel dashboard.',
            'type' => 'number',
            'rules' => ['required', 'integer', 'min:10', 'max:100'],
        ],
        'upload_max_file_size_mb' => [
            'label' => 'Batas upload per file (MB)',
            'description' => 'Ukuran maksimal satu file foto.',
            'type' => 'number',
            'rules' => ['required', 'integer', 'min:1', 'max:15'],
        ],
        'upload_max_files_per_batch' => [
            'label' => 'Batas file per batch upload',
            'description' => 'Jumlah maksimal file dalam satu proses upload.',
            'type' => 'number',
            'rules' => ['required', 'integer', 'min:1', 'max:50'],
        ],
        'watermark_text' => [
            'label' => 'Teks watermark',
            'description' => 'Teks watermark default untuk preview foto.',
            'type' => 'text',
            'rules' => ['required', 'string', 'max:80'],
        ],
        'watermark_opacity' => [
            'label' => 'Opacity watermark (%)',
            'description' => 'Tingkat transparansi watermark.',
            'type' => 'number',
            'rules' => ['required', 'integer', 'min:5', 'max:80'],
        ],
        'payment_pending_hours' => [
            'label' => 'Masa berlaku order pending (jam)',
            'description' => 'Durasi sebelum order pending dianggap expired.',
            'type' => 'number',
            'rules' => ['required', 'integer', 'min:1', 'max:168'],
        ],
    ];

    public function edit(): Response
    {
        $settings = Setting::query()
            ->whereIn('key', array_keys(self::DEFINITIONS))
            ->pluck('value', 'key');

        return Inertia::render('SuperAdmin/Settings/Edit', [
            'settings' => collect(self::DEFINITIONS)
                ->map(fn (array $definition, string $key) => [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'type' => $definition['type'],
                    'value' => (string) ($settings[$key] ?? ''),
                ])
                ->values(),
            'sensitiveKeywords' => $this->sensitiveKeywords(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $allowedKeys = implode(',', array_keys(self::DEFINITIONS));

        $rules = [
            'settings' => ['required', "array:{$allowedKeys}"],
        ];

        foreach (self::DEFINITIONS as $key => $definition) {
            $rules["settings.{$key}"] = $definition['rules'];
        }

        $validated = $request->validate($rules, [
            'settings.array' => 'Settings hanya boleh berisi konfigurasi non-sensitif yang didukung.',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'description' => self::DEFINITIONS[$key]['description'],
                ],
            );
        }

        Log::info('Settings updated', [
            'super_admin_id' => $request->user()->id,
            'keys' => array_keys($validated['settings']),
        ]);

        return back()->with('success', 'Settings berhasil diperbarui.');
    }

    private function sensitiveKeywords(): array
    {
        return [
            'password',
            'secret',
            'token',
            'server key',
            'client key',
            'midtrans credential',
        ];
    }
}
