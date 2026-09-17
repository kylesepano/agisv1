<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AemsRiskWorkingPaperLink extends Model { protected $fillable = ['risk_matrix_item_id','working_paper_id','working_paper_reference','relationship_basis']; public function riskItem(): BelongsTo { return $this->belongsTo(AemsRiskMatrixItem::class,'risk_matrix_item_id'); } public function workingPaper(): BelongsTo { return $this->belongsTo(WorkingPaper::class,'working_paper_id')->withTrashed(); } }
