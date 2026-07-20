<?php

namespace App\Domain\Documents\Services;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders a Blade template to PDF (DOMPDF), stamps it with a unique document
 * number + QR code, stores it on the private disk and records an immutable
 * generated_documents row.
 */
class DocumentGenerator
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function generate(
        string $templateKey,
        string $view,
        array $data,
        Model $subject,
        User $generatedBy,
        string $referencePrefix = 'DOC',
    ): GeneratedDocument {
        $documentNumber = strtoupper($referencePrefix).'-'.now()->format('Y').'-'.Str::upper(Str::random(8));

        $qrPayload = url("/documents/verify/{$documentNumber}");
        $qrDataUri = $this->qrDataUri($qrPayload);

        $pdf = Pdf::loadView($view, [
            ...$data,
            'document_number' => $documentNumber,
            'generated_at' => now(),
            'generated_by' => $generatedBy,
            'qr_data_uri' => $qrDataUri,
        ])->setPaper('a4');

        $path = "documents/{$templateKey}/".now()->format('Y/m')."/{$documentNumber}.pdf";
        Storage::disk('private')->put($path, $pdf->output());

        return GeneratedDocument::query()->create([
            'document_number' => $documentNumber,
            'template_key' => $templateKey,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'file_path' => $path,
            'qr_payload' => $qrPayload,
            'generated_by' => $generatedBy->id,
            'meta' => ['template_key' => $templateKey],
        ]);
    }

    private function qrDataUri(string $payload): string
    {
        try {
            $result = (new Builder(
                writer: new PngWriter(),
                data: $payload,
                size: 160,
                margin: 4,
            ))->build();

            return $result->getDataUri();
        } catch (\Throwable) {
            // QR is decorative; never let it break document generation.
            return '';
        }
    }
}
