<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Pivot;

class AemsReportWorkingPaperLink extends Pivot
{
    public $incrementing = false;
    protected $table = 'aems_report_working_paper_links';
    protected $guarded = [];
    public function reportVersion(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function workingPaperVersion(): BelongsTo { return $this->belongsTo(WorkingPaperVersion::class, 'working_paper_version_id'); }
}
