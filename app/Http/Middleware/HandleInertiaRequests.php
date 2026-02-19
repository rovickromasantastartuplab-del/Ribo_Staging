<?php
namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\Currency;
use App\Models\User;
use App\Models\Setting;
use App\Services\StorageConfigService;
use App\Models\Plan;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Skip database queries during installation
        if ($request->is('install/*') || $request->is('update/*') || !file_exists(storage_path('installed'))) {
            // Get available languages even during installation
            $languagesFile = resource_path('lang/language.json');
            $availableLanguages = [];
            if (file_exists($languagesFile)) {
                $availableLanguages = json_decode(file_get_contents($languagesFile), true) ?? [];
            }

            $globalSettings = [
                'currencySymbol' => '$',
                'currencyNname' => 'US Dollar',
                'base_url' => config('app.url'),
                'image_url' => config('app.url'),
                'is_demo' => config('app.is_demo', false),
                'availableLanguages' => $availableLanguages,
            ];
            $storageSettings = [
                'allowed_file_types' => 'jpg,png,webp,gif',
                'max_file_size_mb' => 2
            ];
        } else {
            // Get system settings
            $settings = settings();
            // Get currency symbol
            $currencyCode = $settings['defaultCurrency'] ?? 'USD';
            $currency = Currency::where('code', $currencyCode)->first();
            $currencySettings = [];
            if ($currency) {
                $currencySettings = [
                    'currencySymbol' => $currency->symbol,
                    'currencyNname' => $currency->name
                ];
            } else {
                $currencySettings = [
                    'currencySymbol' => '$',
                    'currencyNname' => 'US Dollar'
                ];
            }

            // Get storage settings from superadmin
            $storageSettings = [];
            try {
                $superAdmin = User::where('type', 'superadmin')->first();
                if ($superAdmin) {
                    $storageSettingsData = Setting::where('user_id', $superAdmin->id)
                        ->whereIn('key', ['storage_file_types', 'storage_max_upload_size'])
                        ->pluck('value', 'key')
                        ->toArray();

                    $maxSizeKB = (int) ($storageSettingsData['storage_max_upload_size'] ?? 2048);
                    $storageSettings = [
                        'allowed_file_types' => $storageSettingsData['storage_file_types'] ?? 'jpg,png,webp,gif',
                        'max_file_size_mb' => round($maxSizeKB / 1024, 2)
                    ];
                } else {
                    $storageSettings = [
                        'allowed_file_types' => 'jpg,png,webp,gif',
                        'max_file_size_mb' => 2
                    ];
                }
            } catch (\Exception $e) {
                // Fallback to default settings if service fails
                $storageSettings = [
                    'allowed_file_types' => 'jpg,png,webp,gif',
                    'max_file_size_mb' => 2
                ];
            }

            // Get super admin currency settings for plans and referrals
            $superAdminCurrencySettings = [];
            try {
                $superAdmin = User::where('type', 'superadmin')->first();
                if ($superAdmin) {
                    $superAdminSettings = Setting::where('user_id', $superAdmin->id)
                        ->whereIn('key', ['decimalFormat', 'defaultCurrency', 'thousandsSeparator', 'currencySymbolSpace', 'currencySymbolPosition'])
                        ->pluck('value', 'key')
                        ->toArray();

                    $superAdminCurrencyCode = $superAdminSettings['defaultCurrency'] ?? 'USD';
                    $superAdminCurrency = Currency::where('code', $superAdminCurrencyCode)->first();

                    $superAdminCurrencySettings = [
                        'superAdminCurrencySymbol' => $superAdminCurrency ? $superAdminCurrency->symbol : '$',
                        'superAdminDecimalFormat' => $superAdminSettings['decimalFormat'] ?? '2',
                        'superAdminThousandsSeparator' => $superAdminSettings['thousandsSeparator'] ?? ',',
                        'superAdminCurrencySymbolSpace' => ($superAdminSettings['currencySymbolSpace'] ?? false) === '1',
                        'superAdminCurrencySymbolPosition' => $superAdminSettings['currencySymbolPosition'] ?? 'before',
                    ];
                }
            } catch (\Exception $e) {
                // Fallback to default super admin currency settings
                $superAdminCurrencySettings = [
                    'superAdminCurrencySymbol' => '$',
                    'superAdminDecimalFormat' => '2',
                    'superAdminThousandsSeparator' => ',',
                    'superAdminCurrencySymbolSpace' => false,
                    'superAdminCurrencySymbolPosition' => 'before',
                ];
            }

            // Get available languages
            $languagesFile = resource_path('lang/language.json');
            $availableLanguages = [];
            if (file_exists($languagesFile)) {
                $availableLanguages = json_decode(file_get_contents($languagesFile), true) ?? [];
            }

            // Get superadmin enableLogging setting for cookie consent
            $superAdminEnableLogging = false;
            try {
                $superAdmin = User::where('type', 'superadmin')->first();
                if ($superAdmin) {
                    $enableLoggingSetting = Setting::where('user_id', $superAdmin->id)
                        ->where('key', 'enableLogging')
                        ->first();
                    $superAdminEnableLogging = $enableLoggingSetting ? $enableLoggingSetting->value : false;
                }
            } catch (\Exception $e) {
                $superAdminEnableLogging = false;
            }

            // Merge currency settings with other settings
            $globalSettings = array_merge($settings, $currencySettings, $superAdminCurrencySettings);
            $globalSettings['base_url'] = config('app.url');
            $globalSettings['image_url'] = config('app.url');
            $globalSettings['is_demo'] = config('app.is_demo', false);
            $globalSettings['availableLanguages'] = $availableLanguages;
            $globalSettings['enableLogging'] = $superAdminEnableLogging;

            //     // Add cookie consent setting
            //     $cookieSetting = Setting::where('key', 'strictlyNecessaryCookies')->first();
            //     $globalSettings['strictlyNecessaryCookies'] = $cookieSetting ? (int)$cookieSetting->value : 0;
            //
            // Get layout direction from Super Admin settings for public pages
            if (config('app.is_demo')) {
                $globalSettings['layoutDirection'] = $request->cookie('layoutDirection', 'left');
            } else {
                $globalSettings['layoutDirection'] = $globalSettings['layoutDirection'] ?? 'left';
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'base_url' => config('app.url'),
            'image_url' => config('app.url'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $request->user(),
                'roles' => fn() => $request->user()?->roles->pluck('name'),
                'permissions' => fn() => $request->user()?->getAllPermissions()->pluck('name'),
                'plan_features' => function () use ($request) {
                    $user = $request->user();

                    if (!$user) {
                        return null;
                    }

                    if ($user->isSuperAdmin()) {
                        return [
                            'is_superadmin' => true,
                            'plan_id' => null,
                            'features' => ['branding', 'ai_integration', 'trial'],
                            'flags' => [
                                'enable_branding' => true,
                                'enable_chatgpt' => true,
                                'is_trial' => true,
                            ],
                        ];
                    }

                    if ($user->type === 'company') {
                        $user->loadMissing('plan');
                        $plan = $user->plan;
                    } else {
                        $user->loadMissing('creator.plan');
                        $plan = $user->creator?->plan;
                    }

                    $features = [];
                    $flags = [
                        'enable_branding' => false,
                        'enable_chatgpt' => false,
                        'is_trial' => false,
                    ];

                    if ($plan) {
                        $features = is_array($plan->module) ? $plan->module : [];
                        $features = array_values(array_unique(array_filter($features, fn($v) => is_string($v) && $v !== '')));

                        $flags = [
                            'enable_branding' => $plan->enable_branding === 'on',
                            'enable_chatgpt' => $plan->enable_chatgpt === 'on',
                            'is_trial' => $plan->is_trial === 'on',
                        ];

                        if ($flags['enable_branding'] && !in_array('branding', $features, true)) {
                            $features[] = 'branding';
                        }
                        if ($flags['enable_chatgpt'] && !in_array('ai_integration', $features, true)) {
                            $features[] = 'ai_integration';
                        }
                        if ($flags['is_trial'] && !in_array('trial', $features, true)) {
                            $features[] = 'trial';
                        }
                    }

                    return [
                        'is_superadmin' => false,
                        'plan_id' => $plan?->id,
                        'features' => $features,
                        'flags' => $flags,
                    ];
                },
                'company_features' => function () use ($request) {
                    $user = $request->user();
                    if (!$user)
                        return [];

                    if ($user->type === 'company') {
                        return $user->getEnabledFeatures();
                    }

                    // For company staff, check their company's flags and plan
                    if ($user->creator && $user->creator->type === 'company') {
                        return $user->creator->getEnabledFeatures();
                    }

                    return [];
                },
                'debug_plan' => function () use ($request) {
                    $user = $request->user();
                    if (!$user || $user->type !== 'company')
                        return null;

                    $plan = $user->plan;
                    return [
                        'user_id' => $user->id,
                        'plan_id_fk' => $user->plan_id,
                        'plan_loaded' => $plan ? 'yes' : 'no',
                        'plan_data' => $plan ? $plan->toArray() : null,
                        'module_type' => $plan ? gettype($plan->module) : 'N/A',
                        'module_raw' => $plan ? $plan->module : 'null'
                    ];
                },
            ],
            'userLanguage' => config('app.is_demo')
                ? $request->cookie('app_language', $request->user()?->lang ?? $globalSettings['defaultLanguage'] ?? 'en')
                : ($request->user()?->lang ?? $globalSettings['defaultLanguage'] ?? 'en'),
            'isImpersonating' => session('impersonated_by') ? true : false,
            'ziggy' => fn(): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'globalSettings' => $globalSettings,
            'storageSettings' => $storageSettings,
            'is_demo' => config('app.is_demo', false)
        ];
    }
}
