<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsEscalationResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $v = $r->currentVersion;

        return ['id' => $r->id, 'escalationId' => $r->cms_escalation_id, 'issuedNoticeVersionId' => $r->issued_notice_version_id, 'currentVersionId' => $r->current_version_id, 'acceptedVersionId' => $r->accepted_version_id, 'lockVersion' => $r->lock_version, 'currentVersion' => $v ? new CmsEscalationResponseVersionResource($v) : null, 'versions' => CmsEscalationResponseVersionResource::collection($r->relationLoaded('versions') ? $r->versions : collect())];
    }
}
