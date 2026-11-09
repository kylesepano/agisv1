<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsPreliminarySurveyRequest;
use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\User;
use App\Services\AemsAccessService;
use App\Services\AemsPlanningPackageService;
use App\Services\AemsSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AemsPreliminarySurveyController extends Controller
{
    public function __construct(private readonly AemsPlanningPackageService $packages, private readonly AemsAccessService $access, private readonly AemsSupport $support) {}

    public function show(Request $request, AuditEngagement $engagement)
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.view');

        return response()->json(['success' => true, 'data' => $this->workspace($request, $engagement)]);
    }

    public function save(AemsPreliminarySurveyRequest $request, AuditEngagement $engagement)
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, $engagement->planningPackage()->exists() ? 'aems.planning-package.update' : 'aems.planning-package.create');
        $input = $request->validated();
        if ($input['section'] === 'meeting' && array_key_exists('ciasParticipantIds', $input['data'])) {
            $people = User::query()->whereIn('id', $input['data']['ciasParticipantIds'] ?? [])->pluck('name', 'id');
            $input['data']['ciasParticipants'] = collect($input['data']['ciasParticipantIds'] ?? [])->map(fn ($id) => $people->get($id))->filter()->implode(', ');
        }
        foreach (['noticeDocumentVersionId', 'budgetDocumentVersionId', 'flowchartDocumentVersionId'] as $key) {
            if (! empty($input['data'][$key])) {
                $this->document($engagement, (int) $input['data'][$key]);
            }
        }
        foreach ($input['data']['risks'] ?? [] as $index => $risk) {
            $input['data']['risks'][$index]['riskRating'] = isset($risk['likelihood'], $risk['impact']) ? (int) $risk['likelihood'] * (int) $risk['impact'] : null;
        }
        $this->packages->saveSurveySection($request, $engagement, $input);

        return response()->json(['success' => true, 'data' => $this->workspace($request, $engagement->fresh())]);
    }

    public function upload(Request $request, AuditEngagement $engagement)
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, $engagement->planningPackage()->exists() ? 'aems.planning-package.update' : 'aems.planning-package.create');
        $this->assertEditable($engagement);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240']]);
        $file = $request->file('file');
        $path = Storage::disk('local')->putFile('aems/engagements/'.$engagement->id.'/preliminary-survey', $file);
        if (! $path) {
            throw ValidationException::withMessages(['file' => ['The file could not be stored.']]);
        }
        try {
            $version = DB::transaction(function () use ($file, $path, $request, $engagement) {
                $stored = ['original_file_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'file_extension' => $file->getClientOriginalExtension(), 'file_size' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath())];
                $document = Document::query()->create([
                    ...$stored, 'document_code' => 'AEMS-SURVEY-'.Str::uuid(),
                    'title' => $engagement->engagement_code.' - Preliminary Survey',
                    'document_type_id' => MasterList::where('code', 'DOCUMENT_TYPE')->firstOrFail()->items()->where('code', 'OTHER')->value('id'),
                    'confidentiality_level_id' => MasterList::where('code', 'DOCUMENT_CONFIDENTIALITY')->firstOrFail()->items()->where('code', 'INTERNAL')->value('id'),
                    'owner_module' => 'AEMS', 'library_visible' => false, 'is_active' => true,
                    'uploaded_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                ]);
                $version = $document->versions()->create([...$stored, 'version_number' => 1, 'version_label' => 'Survey attachment', 'change_summary' => 'Initial immutable preliminary survey attachment.', 'uploaded_by' => $request->user()->id]);
                $document->update(['current_version_id' => $version->id]);
                $document->links()->create(['module_code' => 'AEMS', 'record_type' => 'AEMS_PRELIMINARY_SURVEY', 'record_id' => $engagement->id, 'record_code' => $engagement->engagement_code, 'record_label' => $engagement->title, 'linked_by' => $request->user()->id]);
                $this->support->audit($request, 'aems.preliminary-survey.attachment_uploaded', $engagement, null, ['documentVersionId' => $version->id]);

                return $version;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return response()->json(['success' => true, 'data' => ['id' => $version->id, 'fileName' => $version->original_file_name]], 201);
    }

    public function download(Request $request, AuditEngagement $engagement, int $version)
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.view');
        $document = $this->document($engagement, $version);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_file_name);
    }

    public function template(Request $request, AuditEngagement $engagement)
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.view');
        $parts = [
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        ];
        $lines = ['CITY INTERNAL AUDIT SERVICE', 'NOTICE OF PRELIMINARY MEETING', $engagement->engagement_code.' - '.$engagement->title, 'To: [Auditee / Focal Person]', 'Date and Time: [Proposed date and time]', 'Venue / Online Link: [Venue or link]', 'Please attend the preliminary meeting for the above audit engagement.', 'Key Matters to Discuss: [Agenda]', 'Initial Information / Documents Needed: [Requirements]', 'CIAS Participants: [Names]', 'Auditee Participants: [Names]', 'Prepared by: [Name and position]'];
        $body = implode('', array_map(fn ($line) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1, 'UTF-8').'</w:t></w:r></w:p>', $lines));
        $parts['word/document.xml'] = '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body></w:document>';
        // Three small uncompressed ZIP entries keep this Word template portable
        // on hosts without ext-zip. ZIP CRCs and the directory are deterministic.
        $archive = '';
        $directory = '';
        foreach ($parts as $name => $content) {
            $size = strlen($content);
            $length = strlen($name);
            $crc = crc32($content);
            $offset = strlen($archive);
            $archive .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 33, $crc, $size, $size, $length, 0).$name.$content;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, 0, 33, $crc, $size, $size, $length, 0, 0, 0, 0, 0, $offset).$name;
        }
        $archive .= $directory.pack('VvvvvVVv', 0x06054B50, 0, 0, count($parts), count($parts), strlen($directory), strlen($archive), 0);

        return response()->streamDownload(fn () => print ($archive), 'Notice_of_Preliminary_Meeting_Template.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    private function document(AuditEngagement $engagement, int $id): DocumentVersion
    {
        return DocumentVersion::query()->whereKey($id)->whereHas('document.links', fn ($q) => $q->where('module_code', 'AEMS')->where('record_type', 'AEMS_PRELIMINARY_SURVEY')->where('record_id', $engagement->id))->firstOrFail();
    }

    private function assertEditable(AuditEngagement $engagement): void
    {
        $package = $engagement->planningPackage()->first();
        if ($engagement->status !== 'ENGAGEMENT_PLANNING' || ($package && ! in_array($package->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true))) {
            throw ValidationException::withMessages(['status' => ['The planning package must be in Planning and editable before changes can be saved.']]);
        }
    }

    private function workspace(Request $request, AuditEngagement $engagement): array
    {
        $package = $engagement->planningPackage()->with('latestVersion')->first();
        $survey = data_get($package?->latestVersion?->preliminary_survey, 'workspace', []);
        $ids = collect([data_get($survey, 'meeting.noticeDocumentVersionId'), data_get($survey, 'auditee.budgetDocumentVersionId'), data_get($survey, 'ppsp.flowchartDocumentVersionId')])->filter();
        $canEdit = $engagement->status === 'ENGAGEMENT_PLANNING'
            && (! $package || in_array($package->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true));
        if ($canEdit) {
            try {
                $this->access->authorizeEngagementAction($request->user(), $engagement, $package ? 'aems.planning-package.update' : 'aems.planning-package.create');
            } catch (HttpException) {
                $canEdit = false;
            }
        }

        return [
            'survey' => $survey,
            'lockVersion' => $package?->lock_version,
            'packageStatus' => $package?->status ?? 'DRAFT',
            'canEdit' => $canEdit,
            'attachments' => DocumentVersion::whereIn('id', $ids)->get()->map(fn ($v) => ['id' => $v->id, 'fileName' => $v->original_file_name])->values(),
            'participantOptions' => User::query()->where('is_active', true)->whereIn('id', ArmisResourceProfile::query()->where('status', 'ACTIVE')->select('user_id'))->orderBy('name')->get(['id', 'name', 'position', 'employee_id'])->map(fn ($person) => ['id' => $person->id, 'name' => $person->name, 'position' => $person->position, 'employeeId' => $person->employee_id])->values(),
        ];
    }
}
