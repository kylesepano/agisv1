<?php

namespace Database\Seeders;

use App\Models\SystemConfiguration;
use App\Services\RuntimeConfiguration;
use Illuminate\Database\Seeder;

/**
 * Seeds typed runtime configuration defaults used by the application.
 */
class SystemConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configurations = [
            ['system_name', 'System Name', 'Audit Governance Information System', 'string', 'general', 'Full application name displayed to users.'],
            ['system_short_name', 'System Short Name', 'AGIS', 'string', 'general', 'Short application name used in navigation and reports.'],
            ['organization_name', 'Organization Name', 'City Government of Cagayan de Oro', 'string', 'general', 'Organization responsible for the platform.'],
            ['system_version', 'System Version', '1.0.0', 'string', 'general', 'Version label displayed in the login page, application footer, and reports.'],
            ['pagination_size', 'Default Pagination Size', 25, 'integer', 'display', 'Default number of records shown on each registry page.'],
            ['date_format', 'Date Format', 'MMMM d, yyyy', 'string', 'display', 'Preferred human-readable date format.'],
            ['timezone', 'Timezone', 'Asia/Manila', 'string', 'regional', 'Timezone used for timestamps and scheduled activities.'],
            ['session_timeout_minutes', 'Session Timeout', 30, 'integer', 'security', 'Minutes of inactivity before a session expires.'],
            ['password_min_length', 'Minimum Password Length', 8, 'integer', 'security', 'Minimum length for non-demo account passwords.'],
            ['failed_login_limit', 'Failed Login Limit', 5, 'integer', 'security', 'Failed attempts allowed before temporary account lockout.'],
            ['account_lock_minutes', 'Account Lock Duration', 15, 'integer', 'security', 'Minutes an account remains locked after repeated failed logins.'],
            ['fiscal_year_start_month', 'Fiscal Year Start Month', 1, 'integer', 'planning', 'Month number (1 to 12) used when AGIS determines the current fiscal year.'],
            ['document_upload_max_mb', 'Maximum Document Upload', 25, 'integer', 'documents', 'Maximum file size in megabytes for documents, versions, and IAP evidence.'],
            ['notification_refresh_seconds', 'Notification Refresh Interval', 60, 'integer', 'notifications', 'Seconds between automatic notification badge refreshes.'],
            ['aems_reminders_enabled', 'Enable AEMS Reminders', true, 'boolean', 'notifications', 'Allow scheduled AEMS due-date, overdue, response, and conference reminders to be delivered.'],
            ['aems_reminder_due_hours', 'AEMS Task Due Window', 48, 'integer', 'notifications', 'Hours before an open AEMS task is due when a due-soon reminder is generated.'],
            ['aems_response_reminder_days', 'AEMS Response Reminder Window', 3, 'integer', 'notifications', 'Days ahead of a management-response due date for an AEMS reminder.'],
            ['aems_conference_reminder_days', 'AEMS Conference Reminder Window', 7, 'integer', 'notifications', 'Days ahead of a scheduled AEMS conference for a reminder.'],
            ['iap_default_annual_person_days', 'Legacy IAP Annual Person-days', 180, 'integer', 'planning', 'Historical compatibility default only; ARMIS is authoritative for current resource capacity.'],
            ['document_number_format', 'Document Number Format', 'DOC-{YEAR}-{SEQ:5}', 'string', 'numbering', 'Code template for repository documents. Tokens: {YEAR} and {SEQ:n}.'],
            ['aems_engagement_number_format', 'AEMS Engagement Number Format', 'AEMS-{YEAR}-{SEQ:3}', 'string', 'numbering', 'Code template for AEMS engagements. Tokens: {YEAR} and {SEQ:n}.'],
            ['iap_plan_number_format', 'Annual Plan Number Format', 'IAP-{YEAR}', 'string', 'numbering', 'Code template for annual internal audit plans.'],
            ['siap_plan_number_format', 'Strategic Plan Number Format', 'SIAP-{START_YEAR}-{END_YEAR}-R00', 'string', 'numbering', 'Code template for strategic plans.'],
            ['risk_period_number_format', 'Risk Period Number Format', 'RISK-{YEAR}-{SEQ:3}', 'string', 'numbering', 'Code template for risk-assessment periods.'],
            ['baics_number_format', 'BAICS Assessment Number Format', 'BAICS-{YEAR}-{SEQ:3}', 'string', 'numbering', 'Code template for BAICS assessment cycles.'],
            ['baics_report_number_format', 'BAICS Report Number Format', 'BAR-{YEAR}-{SEQ:3}', 'string', 'numbering', 'Code template for Baseline Assessment Reports.'],
            ['baics_integration_required', 'Require BAICS for IAP Decisions', false, 'boolean', 'integrations', 'When enabled, risk, prioritization, strategic-plan, and annual-plan approval gates require an approved BAICS baseline or an approved legacy exception.'],
            ['prioritization_number_format', 'Prioritization Number Format', 'PRIO-{YEAR}-{SEQ:3}', 'string', 'numbering', 'Code template for prioritization runs.'],
            ['default_risk_level_code', 'Default Risk Level', 'MEDIUM', 'string', 'planning', 'Risk level selected when a new record does not explicitly specify one.'],
            ['default_workflow_sla_hours', 'Default Workflow Deadline', 72, 'integer', 'workflow', 'Default number of hours allowed for workflow steps without an explicit SLA.'],
            ['workflow_mapping_core', 'Core Default Workflow', 'CORE_DOCUMENT_REVIEW', 'string', 'workflow', 'Published workflow used by default for Core records.'],
            ['workflow_mapping_iap', 'IAP Default Workflow', 'IAP_ANNUAL_PLAN_APPROVAL', 'string', 'workflow', 'Published workflow used by default for IAP records.'],
            ['armis_provider_mode', 'ARMIS Provider Mode', 'ARMIS_AUTHORITATIVE', 'string', 'integrations', 'ARMIS is the sole operational resource provider. IAP values are retained only as historical planning lineage and reconciliation evidence.'],
            ['logo_url', 'Runtime Logo', '/logo.png', 'string', 'branding', 'Current AGIS logo URL. Use the upload control to replace it safely.'],
            ['mail_enabled', 'Enable Outbound Email', false, 'boolean', 'email', 'Allow AGIS to send notification and test email through the selected mail transport.'],
            ['mail_mailer', 'Mail Transport', 'smtp', 'string', 'email', 'Use SMTP for production delivery or Log for safe local verification.'],
            ['mail_host', 'SMTP Host', '127.0.0.1', 'string', 'email', 'SMTP server hostname or IP address.'],
            ['mail_port', 'SMTP Port', 2525, 'integer', 'email', 'SMTP server port.'],
            ['mail_encryption', 'SMTP Encryption', '', 'string', 'email', 'SMTP encryption mode: none, TLS, or SSL.'],
            ['mail_username', 'SMTP Username', '', 'string', 'email', 'Optional SMTP authentication username.'],
            ['mail_password', 'SMTP Password', '', 'secret', 'email', 'Encrypted SMTP authentication password.'],
            ['mail_from_address', 'Mail From Address', 'agis@example.gov.ph', 'string', 'email', 'Sender email address used by AGIS.'],
            ['mail_from_name', 'Mail From Name', 'AGIS', 'string', 'email', 'Sender display name used by AGIS.'],
        ];

        foreach ($configurations as [$key, $name, $value, $type, $group, $description]) {
            $configuration = SystemConfiguration::query()->firstOrNew(['key' => $key]);
            $configuration->fill(compact('name', 'type', 'group', 'description'));
            if (! $configuration->exists || $type !== 'secret' || blank($configuration->value)) {
                $configuration->value = $value;
            }
            $configuration->save();
        }

        app(RuntimeConfiguration::class)->forget();
    }
}
