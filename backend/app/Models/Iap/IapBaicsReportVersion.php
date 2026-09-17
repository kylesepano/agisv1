<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsReportVersion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $table = 'iap_baics_report_versions';
    protected $fillable = ['report_id', 'version_number', 'status', 'snapshot', 'source_manifest', 'source_manifest_sha256', 'content_sha256', 'pdf_checksum_sha256', 'csv_checksum_sha256', 'file_version', 'reason', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array', 'source_manifest' => 'array']; }
    public function report(): BelongsTo { return $this->belongsTo(IapBaicsReport::class, 'report_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
