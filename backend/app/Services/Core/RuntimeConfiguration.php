<?php

namespace App\Services;

use App\Models\SystemConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Resolves typed runtime settings, safe public values, and configured defaults.
 */
class RuntimeConfiguration
{
    public const CACHE_KEY = 'agis.runtime_configuration.v1';

    /** ARMIS is the only active resource provider. */
    public const ARMIS_PROVIDER_MODES = [
        'ARMIS_AUTHORITATIVE',
    ];

    /**
     * Safe fallbacks keep authentication and public branding usable before the
     * configuration table is migrated, during tests, or while the DB recovers.
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'system_name' => 'Audit Governance Information System',
        'system_short_name' => 'AGIS',
        'organization_name' => 'City Government of Cagayan de Oro',
        'system_version' => '1.0.0',
        'pagination_size' => 25,
        'date_format' => 'MMMM d, yyyy',
        'timezone' => 'Asia/Manila',
        'session_timeout_minutes' => 30,
        'password_min_length' => 8,
        'failed_login_limit' => 5,
        'account_lock_minutes' => 15,
        'fiscal_year_start_month' => 1,
        'document_upload_max_mb' => 25,
        'notification_refresh_seconds' => 60,
        'aems_reminders_enabled' => true,
        'aems_reminder_due_hours' => 48,
        'aems_response_reminder_days' => 3,
        'aems_conference_reminder_days' => 7,
        'iap_default_annual_person_days' => 180,
        'document_number_format' => 'DOC-{YEAR}-{SEQ:5}',
        'aems_engagement_number_format' => 'AEMS-{YEAR}-{SEQ:3}',
        'iap_plan_number_format' => 'IAP-{YEAR}',
        'siap_plan_number_format' => 'SIAP-{START_YEAR}-{END_YEAR}-R00',
        'risk_period_number_format' => 'RISK-{YEAR}-{SEQ:3}',
        'baics_number_format' => 'BAICS-{YEAR}-{SEQ:3}',
        'baics_report_number_format' => 'BAR-{YEAR}-{SEQ:3}',
        'baics_integration_required' => false,
        'prioritization_number_format' => 'PRIO-{YEAR}-{SEQ:3}',
        'default_risk_level_code' => 'MEDIUM',
        'default_workflow_sla_hours' => 72,
        'workflow_mapping_core' => 'CORE_DOCUMENT_REVIEW',
        'workflow_mapping_iap' => 'IAP_ANNUAL_PLAN_APPROVAL',
        'armis_provider_mode' => 'ARMIS_AUTHORITATIVE',
        'logo_url' => '/logo.png',
        'mail_enabled' => false,
        'mail_mailer' => 'smtp',
        'mail_host' => '127.0.0.1',
        'mail_port' => 2525,
        'mail_encryption' => '',
        'mail_username' => '',
        'mail_password' => '',
        'mail_from_address' => 'agis@example.gov.ph',
        'mail_from_name' => 'AGIS',
    ];

    /** @return array<string, mixed> */
    public function all(): array
    {
        // Runtime reads are frequent, so configuration is cached until an
        // administrator changes a value and explicitly calls forget().
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            try {
                if (! Schema::hasTable('system_configurations')) {
                    return self::DEFAULTS;
                }

                $values = [
                    ...self::DEFAULTS,
                    ...SystemConfiguration::query()
                        ->pluck('value', 'key')
                        ->all(),
                ];

                // Never expose or return a historical fallback/shadow value
                // as an active runtime setting after the ARMIS cutover.
                $values['armis_provider_mode'] = 'ARMIS_AUTHORITATIVE';

                return $values;
            } catch (Throwable) {
                return self::DEFAULTS;
            }
        });
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function string(string $key): string
    {
        return (string) ($this->all()[$key] ?? self::DEFAULTS[$key] ?? '');
    }

    public function integer(string $key): int
    {
        return (int) ($this->all()[$key] ?? self::DEFAULTS[$key] ?? 0);
    }

    public function boolean(string $key): bool
    {
        return filter_var(
            $this->all()[$key] ?? self::DEFAULTS[$key] ?? false,
            FILTER_VALIDATE_BOOL,
        );
    }

    public function secret(string $key): string
    {
        $value = $this->string($key);
        if ($value === '') {
            return '';
        }

        try {
            // Secrets are encrypted at rest by the configuration endpoint.
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }

    /** @param array<string, string|int> $tokens */
    public function formatNumber(
        string $configurationKey,
        int $sequence = 1,
        array $tokens = [],
    ): string {
        // Supported examples: DOC-{YEAR}-{SEQ:5} and
        // SIAP-{START_YEAR}-{END_YEAR}-R00.
        $year = (int) ($tokens['YEAR'] ?? $this->currentFiscalYear());
        $format = $this->string($configurationKey);
        $format = str_replace(
            ['{YEAR}', '{START_YEAR}', '{END_YEAR}'],
            [
                (string) $year,
                (string) ($tokens['START_YEAR'] ?? $year),
                (string) ($tokens['END_YEAR'] ?? $year),
            ],
            $format,
        );
        $format = preg_replace_callback(
            '/\{SEQ(?::(\d+))?\}/',
            fn (array $match): string => str_pad(
                (string) $sequence,
                max(1, min(12, (int) ($match[1] ?? 1))),
                '0',
                STR_PAD_LEFT,
            ),
            $format,
        ) ?? $format;

        foreach ($tokens as $key => $value) {
            $format = str_replace('{'.strtoupper($key).'}', (string) $value, $format);
        }

        return $format;
    }

    public function paginationSize(): int
    {
        return min(100, max(5, $this->integer('pagination_size')));
    }

    public function passwordMinLength(): int
    {
        return min(128, max(4, $this->integer('password_min_length')));
    }

    public function failedLoginLimit(): int
    {
        return min(20, max(1, $this->integer('failed_login_limit')));
    }

    public function accountLockMinutes(): int
    {
        return min(1440, max(1, $this->integer('account_lock_minutes')));
    }

    public function documentUploadMaxKilobytes(): int
    {
        return min(100, max(1, $this->integer('document_upload_max_mb'))) * 1024;
    }

    public function timezone(): string
    {
        $timezone = $this->string('timezone');

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) self::DEFAULTS['timezone'];
    }

    public function currentFiscalYear(): int
    {
        $now = CarbonImmutable::now($this->timezone());
        $startMonth = min(12, max(1, $this->integer('fiscal_year_start_month')));

        return $now->month >= $startMonth ? $now->year : $now->year - 1;
    }

    public function armisProviderMode(): string
    {
        // Historical configuration values are normalized at read time. IAP
        // remains available only as source lineage/reconciliation history;
        // it is never selected as an operational resource provider.
        return 'ARMIS_AUTHORITATIVE';
    }

    public function apply(): void
    {
        // Laravel services read these config keys at runtime. Reapplying them
        // makes an administrator's saved settings effective without a restart.
        config([
            'app.name' => $this->string('system_name'),
            'session.lifetime' => min(1440, max(5, $this->integer('session_timeout_minutes'))),
            'mail.default' => $this->string('mail_mailer'),
            'mail.mailers.smtp.host' => $this->string('mail_host'),
            'mail.mailers.smtp.port' => $this->integer('mail_port'),
            'mail.mailers.smtp.username' => $this->string('mail_username') ?: null,
            'mail.mailers.smtp.password' => $this->secret('mail_password') ?: null,
            'mail.mailers.smtp.scheme' => $this->string('mail_encryption') ?: null,
            'mail.from.address' => $this->string('mail_from_address'),
            'mail.from.name' => $this->string('mail_from_name'),
        ]);
    }

    /** @return array<string, mixed> */
    public function publicValues(): array
    {
        // Never expose SMTP credentials, lockout internals, or other secrets to
        // the unauthenticated runtime-configuration endpoint.
        return [
            'systemName' => $this->string('system_name'),
            'systemShortName' => $this->string('system_short_name'),
            'organizationName' => $this->string('organization_name'),
            'systemVersion' => $this->string('system_version'),
            'paginationSize' => $this->paginationSize(),
            'dateFormat' => $this->string('date_format'),
            'timezone' => $this->timezone(),
            'sessionTimeoutMinutes' => min(1440, max(5, $this->integer('session_timeout_minutes'))),
            'passwordMinLength' => $this->passwordMinLength(),
            'fiscalYearStartMonth' => min(12, max(1, $this->integer('fiscal_year_start_month'))),
            'currentFiscalYear' => $this->currentFiscalYear(),
            'documentUploadMaxMb' => min(100, max(1, $this->integer('document_upload_max_mb'))),
            'notificationRefreshSeconds' => min(3600, max(15, $this->integer('notification_refresh_seconds'))),
            'aemsReminderRules' => [
                'enabled' => $this->boolean('aems_reminders_enabled'),
                'dueHours' => min(720, max(1, $this->integer('aems_reminder_due_hours'))),
                'responseDueDays' => min(90, max(1, $this->integer('aems_response_reminder_days'))),
                'conferenceDueDays' => min(90, max(1, $this->integer('aems_conference_reminder_days'))),
            ],
            'iapDefaultAnnualPersonDays' => max(1, $this->integer('iap_default_annual_person_days')),
            'logoUrl' => $this->string('logo_url') ?: '/logo.png',
            'defaultRiskLevelCode' => $this->string('default_risk_level_code'),
            'defaultWorkflowSlaHours' => max(1, $this->integer('default_workflow_sla_hours')),
            'armisProviderMode' => $this->armisProviderMode(),
            'mailEnabled' => $this->boolean('mail_enabled'),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            'system_name' => ['rules' => ['required', 'string', 'max:150'], 'runtimeEffect' => 'Application title and browser-facing branding'],
            'system_short_name' => ['rules' => ['required', 'string', 'max:30'], 'runtimeEffect' => 'Sidebar, login, and footer branding'],
            'organization_name' => ['rules' => ['required', 'string', 'max:150'], 'runtimeEffect' => 'Login and application copyright'],
            'system_version' => ['rules' => ['required', 'string', 'max:30'], 'runtimeEffect' => 'Login and application version label'],
            'pagination_size' => ['rules' => ['required', 'integer', 'min:5', 'max:100'], 'min' => 5, 'max' => 100, 'runtimeEffect' => 'Default rows per registry and API page'],
            'date_format' => [
                'rules' => ['required', 'string', 'in:MMMM d, yyyy,MMM d, yyyy,MM/dd/yyyy,dd/MM/yyyy,yyyy-MM-dd'],
                'options' => ['MMMM d, yyyy', 'MMM d, yyyy', 'MM/dd/yyyy', 'dd/MM/yyyy', 'yyyy-MM-dd'],
                'runtimeEffect' => 'Dates displayed in the application shell',
            ],
            'timezone' => ['rules' => ['required', 'timezone'], 'runtimeEffect' => 'Displayed dates and fiscal-year calculations; stored timestamps remain UTC'],
            'session_timeout_minutes' => ['rules' => ['required', 'integer', 'min:5', 'max:1440'], 'min' => 5, 'max' => 1440, 'runtimeEffect' => 'Authenticated session lifetime'],
            'password_min_length' => ['rules' => ['required', 'integer', 'min:4', 'max:128'], 'min' => 4, 'max' => 128, 'runtimeEffect' => 'New and changed password validation'],
            'failed_login_limit' => ['rules' => ['required', 'integer', 'min:1', 'max:20'], 'min' => 1, 'max' => 20, 'runtimeEffect' => 'Automatic account lock threshold'],
            'account_lock_minutes' => ['rules' => ['required', 'integer', 'min:1', 'max:1440'], 'min' => 1, 'max' => 1440, 'runtimeEffect' => 'Automatic account lock duration'],
            'fiscal_year_start_month' => ['rules' => ['required', 'integer', 'min:1', 'max:12'], 'min' => 1, 'max' => 12, 'runtimeEffect' => 'Default current fiscal year for planning'],
            'document_upload_max_mb' => ['rules' => ['required', 'integer', 'min:1', 'max:100'], 'min' => 1, 'max' => 100, 'runtimeEffect' => 'Document, version, and IAP evidence uploads'],
            'notification_refresh_seconds' => ['rules' => ['required', 'integer', 'min:15', 'max:3600'], 'min' => 15, 'max' => 3600, 'runtimeEffect' => 'Notification badge refresh interval'],
            'aems_reminders_enabled' => ['rules' => ['required', 'boolean'], 'options' => [true, false], 'runtimeEffect' => 'Enable or pause AEMS due-date and overdue reminder dispatch without changing workflow state'],
            'aems_reminder_due_hours' => ['rules' => ['required', 'integer', 'min:1', 'max:720'], 'min' => 1, 'max' => 720, 'runtimeEffect' => 'Hours before an AEMS task becomes due-soon for reminder delivery'],
            'aems_response_reminder_days' => ['rules' => ['required', 'integer', 'min:1', 'max:90'], 'min' => 1, 'max' => 90, 'runtimeEffect' => 'Days ahead for management-response reminders'],
            'aems_conference_reminder_days' => ['rules' => ['required', 'integer', 'min:1', 'max:90'], 'min' => 1, 'max' => 90, 'runtimeEffect' => 'Days ahead for entry and exit conference reminders'],
            'iap_default_annual_person_days' => ['rules' => ['required', 'integer', 'min:1', 'max:365'], 'min' => 1, 'max' => 365, 'runtimeEffect' => 'Legacy IAP planning baseline retained for historical lineage; ARMIS supplies operational capacity'],
            'document_number_format' => ['rules' => ['required', 'string', 'max:80', 'regex:/\\{YEAR\\}/'], 'runtimeEffect' => 'Codes assigned to new repository documents; supports {YEAR} and {SEQ:n}'],
            'aems_engagement_number_format' => ['rules' => ['required', 'string', 'max:80', 'regex:/\\{YEAR\\}/'], 'runtimeEffect' => 'Codes assigned to new AEMS engagements; supports {YEAR} and {SEQ:n}'],
            'iap_plan_number_format' => ['rules' => ['required', 'string', 'max:80', 'regex:/\\{YEAR\\}/'], 'runtimeEffect' => 'Codes assigned to new annual internal audit plans'],
            'siap_plan_number_format' => ['rules' => ['required', 'string', 'max:80'], 'runtimeEffect' => 'Codes assigned to strategic plans; supports {START_YEAR}, {END_YEAR}, and {SEQ:n}'],
            'risk_period_number_format' => ['rules' => ['required', 'string', 'max:80'], 'runtimeEffect' => 'Codes assigned to risk-assessment periods'],
            'baics_number_format' => ['rules' => ['required', 'string', 'max:80', 'regex:/\{YEAR\}/'], 'runtimeEffect' => 'Codes assigned to BAICS assessment cycles'],
            'baics_report_number_format' => ['rules' => ['required', 'string', 'max:80'], 'runtimeEffect' => 'Codes assigned to Baseline Assessment Reports'],
            'baics_integration_required' => ['rules' => ['required', 'boolean'], 'runtimeEffect' => 'When enabled, IAP risk and plan approvals require an approved BAICS baseline or an approved legacy exception'],
            'prioritization_number_format' => ['rules' => ['required', 'string', 'max:80'], 'runtimeEffect' => 'Codes assigned to prioritization runs'],
            'default_risk_level_code' => ['rules' => ['required', 'string', 'max:60'], 'runtimeEffect' => 'Default risk level for records that do not explicitly specify one'],
            'default_workflow_sla_hours' => ['rules' => ['required', 'integer', 'min:1', 'max:8760'], 'min' => 1, 'max' => 8760, 'runtimeEffect' => 'Default deadline for workflow steps without a specific SLA'],
            'workflow_mapping_core' => ['rules' => ['nullable', 'string', 'max:80'], 'runtimeEffect' => 'Default published workflow for Core records'],
            'workflow_mapping_iap' => ['rules' => ['nullable', 'string', 'max:80'], 'runtimeEffect' => 'Default published workflow for IAP records'],
            'armis_provider_mode' => [
                'rules' => ['required', 'string', 'in:ARMIS_AUTHORITATIVE'],
                'options' => ['ARMIS_AUTHORITATIVE'],
                'runtimeEffect' => 'ARMIS is the sole operational resource provider; historical IAP comparisons do not switch runtime ownership.',
            ],
            'logo_url' => ['rules' => ['required', 'string', 'max:500'], 'runtimeEffect' => 'Application, login, and sidebar logo'],
            'mail_enabled' => ['rules' => ['required', 'boolean'], 'options' => [true, false], 'runtimeEffect' => 'Delivery of configured outbound email'],
            'mail_mailer' => ['rules' => ['required', 'string', 'in:smtp,log'], 'options' => ['smtp', 'log'], 'runtimeEffect' => 'Outbound mail transport'],
            'mail_host' => ['rules' => ['required', 'string', 'max:255'], 'runtimeEffect' => 'SMTP server hostname'],
            'mail_port' => ['rules' => ['required', 'integer', 'min:1', 'max:65535'], 'min' => 1, 'max' => 65535, 'runtimeEffect' => 'SMTP server port'],
            'mail_encryption' => ['rules' => ['nullable', 'string', 'in:,tls,ssl'], 'options' => ['', 'tls', 'ssl'], 'runtimeEffect' => 'SMTP transport security'],
            'mail_username' => ['rules' => ['nullable', 'string', 'max:255'], 'runtimeEffect' => 'SMTP authentication username'],
            'mail_password' => ['rules' => ['nullable', 'string', 'max:1000'], 'secret' => true, 'runtimeEffect' => 'Encrypted SMTP authentication password'],
            'mail_from_address' => ['rules' => ['required', 'email', 'max:255'], 'runtimeEffect' => 'Sender address for AGIS email'],
            'mail_from_name' => ['rules' => ['required', 'string', 'max:255'], 'runtimeEffect' => 'Sender name for AGIS email'],
        ];
    }
}
