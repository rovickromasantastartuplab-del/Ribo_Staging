<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\EmailSettingController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\SystemSettingsController;
use App\Http\Controllers\Settings\CompanySystemSettingsController;
use App\Http\Controllers\Settings\CurrencySettingController;
use App\Http\Controllers\PlanOrderController;
use App\Http\Controllers\Settings\PaymentSettingController;
use App\Http\Controllers\Settings\CompanyPaymentSettingController;
use App\Http\Controllers\Settings\WebhookController;
use App\Http\Controllers\Settings\IntegrationsSettingsController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\InvoiceStripePaymentController;
use App\Http\Controllers\PayPalPaymentController;
use App\Http\Controllers\BankPaymentController;
use Inertia\Inertia;

/* |-------------------------------------------------------------------------- | Settings Routes |-------------------------------------------------------------------------- | | Here are the routes for settings management | */

// Payment routes accessible without plan check
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payment-methods', [PaymentSettingController::class , 'getPaymentMethods'])->name('payment.methods');
    Route::get('/enabled-payment-methods', [PaymentSettingController::class , 'getEnabledMethods'])->name('payment.enabled-methods');
    Route::post('/plan-orders', [PlanOrderController::class , 'create'])->name('plan-orders.create');
    Route::post('/stripe-payment', [StripePaymentController::class , 'processPayment'])->name('settings.stripe.payment');
});

Route::middleware(['auth', 'verified', 'plan.access'])->group(function () {
    // Payment Settings (admin only)
    Route::post('/payment-settings', [PaymentSettingController::class , 'store'])->middleware('App\\Http\\Middleware\\SuperAdminMiddleware')->name('payment.settings');

    // Company Payment Settings (uses same as admin)
    Route::post('/company-payment-settings', [CompanyPaymentSettingController::class , 'store'])->name('company.payment.settings');
    Route::get('/company-payment-methods', [CompanyPaymentSettingController::class , 'getCompanyPaymentMethods'])->name('company.payment.methods');

    // Invoice Stripe Payment
    Route::post('/invoice-stripe-payment', [InvoiceStripePaymentController::class , 'processPayment'])->name('settings.invoice.stripe.payment');
    Route::post('/invoice-stripe-confirm', [InvoiceStripePaymentController::class , 'confirmPayment'])->name('settings.invoice.stripe.confirm');

    // Profile settings page with profile and password sections
    Route::get('profile', function () {
            return Inertia::render('settings/profile-settings');
        }
        )->name('profile');

        // Routes for form submissions
        Route::patch('profile', [ProfileController::class , 'update'])->name('profile.update');
        Route::post('profile', [ProfileController::class , 'update']); // For file uploads with method spoofing
        Route::delete('profile', [ProfileController::class , 'destroy'])->name('profile.destroy');
        Route::put('profile/password', [PasswordController::class , 'update'])->name('password.update');

        // Email settings page
        Route::get('settings/email', function () {
            return Inertia::render('settings/components/email-settings');
        }
        )->name('settings.email');

        // Email settings routes
        Route::get('settings/email/get', [EmailSettingController::class , 'getEmailSettings'])->name('settings.email.get');
        Route::post('settings/email/update', [EmailSettingController::class , 'updateEmailSettings'])->name('settings.email.update');
        Route::post('settings/email/test', [EmailSettingController::class , 'sendTestEmail'])->name('settings.email.test');

        // General settings page with system and company settings
        Route::get('settings', [SettingsController::class , 'index'])->name('settings');

        // System Settings routes (Super Admin)
        Route::post('settings/system', [SystemSettingsController::class , 'update'])->name('settings.system.update');
        Route::post('settings/brand', [SystemSettingsController::class , 'updateBrand'])->middleware('plan.feature:branding')->name('settings.brand.update');

        // Module Visibility (Superadmin only)
        Route::get('settings/module-visibility', [\App\Http\Controllers\ModuleVisibilityController::class , 'index'])
            ->middleware('App\Http\Middleware\SuperAdminMiddleware')
            ->name('settings.module-visibility.index');
        Route::post('settings/module-visibility/toggle', [\App\Http\Controllers\ModuleVisibilityController::class , 'toggle'])
            ->middleware('App\Http\Middleware\SuperAdminMiddleware')
            ->name('settings.module-visibility.toggle');
        Route::post('settings/module-visibility/update-all', [\App\Http\Controllers\ModuleVisibilityController::class , 'updateAll'])
            ->middleware('App\Http\Middleware\SuperAdminMiddleware')
            ->name('settings.module-visibility.update-all');

        // Company System Settings routes
        Route::post('settings/company/system', [CompanySystemSettingsController::class , 'update'])->name('settings.company.system.update');
        Route::post('settings/company/integrations', [IntegrationsSettingsController::class , 'update'])->name('settings.company.integrations.update');
        Route::get('settings/field-mappings/{provider}', [IntegrationsSettingsController::class , 'getFieldMappings'])->name('settings.field-mappings.get');
        Route::post('settings/field-mappings/{provider}', [IntegrationsSettingsController::class , 'saveFieldMappings'])->name('settings.field-mappings.save');

        // Social & Omnichannel OAuth Integration Routes
        Route::get('auth/redirect/{provider}', [\App\Http\Controllers\Settings\SocialAuthController::class , 'redirect'])->name('social.redirect');
        Route::get('auth/callback/{provider}', [\App\Http\Controllers\Settings\SocialAuthController::class , 'callback'])->name('social.callback');

        Route::post('settings/recaptcha', [SystemSettingsController::class , 'updateRecaptcha'])->name('settings.recaptcha.update');
        Route::post('settings/chatgpt', [SystemSettingsController::class , 'updateChatgpt'])->middleware(['plan.feature:ai_integration', 'App\\Http\\Middleware\\SuperAdminMiddleware'])->name('settings.chatgpt.update');
        Route::post('settings/cookie', [SystemSettingsController::class , 'updateCookie'])->name('settings.cookie.update');
        Route::post('settings/seo', [SystemSettingsController::class , 'updateSeo'])->name('settings.seo.update');
        Route::post('settings/storage', [SystemSettingsController::class , 'updateStorage'])->name('settings.storage.update');
        Route::get('settings/email-notifications', [SystemSettingsController::class , 'getEmailNotifications'])->name('settings.email-notifications.get');
        Route::get('settings/email-notifications/available', [SystemSettingsController::class , 'getAvailableEmailNotifications'])->name('settings.email-notifications.available');
        Route::post('settings/email-notifications', [SystemSettingsController::class , 'updateEmailNotifications'])->name('settings.email-notifications.update');
        Route::get('settings/twilio-notifications', [SystemSettingsController::class , 'getTwilioNotifications'])->name('settings.twilio-notifications.get');
        Route::get('settings/twilio-notifications/available', [SystemSettingsController::class , 'getAvailableTwilioNotifications'])->name('settings.twilio-notifications.available');
        Route::get('settings/twilio-config', [SystemSettingsController::class , 'getTwilioConfig'])->name('settings.twilio-config.get');
        Route::post('settings/twilio-notifications', [SystemSettingsController::class , 'updateTwilioNotifications'])->name('settings.twilio-notifications.update');
        Route::post('settings/sms/test', [SystemSettingsController::class , 'sendTestSMS'])->name('settings.sms.test');
        Route::post('settings/cache/clear', [SystemSettingsController::class , 'clearCache'])->name('settings.cache.clear');

        // Currency Settings routes
        Route::post('settings/currency', [CurrencySettingController::class , 'update'])->middleware('App\\Http\\Middleware\\SuperAdminMiddleware')->name('settings.currency.update');

        // Invoice Template Settings routes
        Route::post('settings/invoice-template', [SystemSettingsController::class , 'updateInvoiceTemplate'])->name('settings.invoice-template');

        // Quote Template Settings routes
        Route::post('settings/quote-template', [SystemSettingsController::class , 'updateQuoteTemplate'])->name('settings.quote-template');

        // Sales Order Template Settings routes
        Route::post('settings/sales-order-template', [SystemSettingsController::class , 'updateSalesOrderTemplate'])->name('settings.sales-order-template');

        // Webhook Settings routes
        Route::get('settings/webhooks', [WebhookController::class , 'index'])->name('settings.webhooks.index');
        Route::post('settings/webhooks', [WebhookController::class , 'store'])->name('settings.webhooks.store');
        Route::put('settings/webhooks/{webhook}', [WebhookController::class , 'update'])->name('settings.webhooks.update');
        Route::delete('settings/webhooks/{webhook}', [WebhookController::class , 'destroy'])->name('settings.webhooks.destroy');

        // Google Calendar Settings routes
        Route::post('settings/google-calendar', [SystemSettingsController::class , 'updateGoogleCalendar'])->name('settings.google-calendar.update');
        Route::post('settings/google-calendar/sync', [SystemSettingsController::class , 'syncGoogleCalendar'])->name('settings.google-calendar.sync');

        // Gmail Integration routes
        Route::post('settings/gmail/disconnect', [\App\Http\Controllers\GmailController::class , 'disconnect'])->name('settings.gmail.disconnect');
        Route::post('settings/gmail/sync', [\App\Http\Controllers\GmailController::class , 'syncNow'])->name('settings.gmail.sync');
        Route::post('settings/gmail/categories', [\App\Http\Controllers\GmailController::class , 'updateSyncSettings'])->name('settings.gmail.categories.update');
        Route::get('gmail/threads', [\App\Http\Controllers\GmailController::class , 'threads'])->name('gmail.threads');
        Route::get('gmail/threads/{threadId}', [\App\Http\Controllers\GmailController::class , 'showThread'])->name('gmail.thread.show');

        // Omnichannel Channel Account Management routes
        Route::get('settings/channels', [\App\Http\Controllers\Settings\ChannelAccountController::class, 'index'])->name('settings.channels.index');
        Route::post('settings/channels', [\App\Http\Controllers\Settings\ChannelAccountController::class, 'store'])->name('settings.channels.store');
        Route::post('settings/channels/{account}/sync', [\App\Http\Controllers\Settings\ChannelAccountController::class, 'sync'])->name('settings.channels.sync');
        Route::delete('settings/channels/{account}', [\App\Http\Controllers\Settings\ChannelAccountController::class, 'destroy'])->name('settings.channels.destroy');
    });
