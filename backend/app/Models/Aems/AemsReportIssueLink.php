<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Pivot;

class AemsReportIssueLink extends Pivot
{
    public $incrementing = false;
    protected $table = 'aems_report_issue_links';
    protected $guarded = [];
    public function reportVersion(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function issue(): BelongsTo { return $this->belongsTo(AuditIssue::class, 'audit_issue_id'); }
}
