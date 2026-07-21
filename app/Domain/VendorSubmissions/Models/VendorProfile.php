<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'contact_person', 'phone', 'city', 'gst_number',
        'status', 'kyc_status', 'activated_by', 'activated_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorProfileStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    /**
     * The partner-KYC document catalog. `sides` = 2 splits into `<key>_front` /
     * `<key>_back` media types. Required docs must all be verified before the
     * partner can be activated.
     *
     * @return array<string, array{label: string, group: string, sides: int}>
     */
    public static function documentCatalog(): array
    {
        return [
            'pan' => ['label' => 'PAN card', 'group' => 'required', 'sides' => 1],
            'aadhaar' => ['label' => 'Aadhaar', 'group' => 'required', 'sides' => 2],
            'cancelled_cheque' => ['label' => 'Cancelled cheque / bank proof', 'group' => 'required', 'sides' => 1],
            'photo' => ['label' => 'Partner photo', 'group' => 'required', 'sides' => 1],
            'gst_certificate' => ['label' => 'GST certificate', 'group' => 'optional', 'sides' => 1],
            'address_proof' => ['label' => 'Address proof', 'group' => 'optional', 'sides' => 1],
            'agreement' => ['label' => 'Signed partner agreement', 'group' => 'optional', 'sides' => 1],
        ];
    }

    /**
     * Media types for a catalog key: a 2-sided doc yields `<key>_front`/`<key>_back`.
     *
     * @return array<int, string>
     */
    public static function docMediaTypes(string $key, int $sides): array
    {
        return $sides === 2 ? ["{$key}_front", "{$key}_back"] : [$key];
    }

    /**
     * Flat list of every media type in the catalog.
     *
     * @return array<int, string>
     */
    public static function allMediaTypes(): array
    {
        $types = [];
        foreach (self::documentCatalog() as $key => $def) {
            $types = array_merge($types, self::docMediaTypes($key, $def['sides']));
        }

        return $types;
    }

    /**
     * Flat list of the media types that must be verified to complete KYC.
     *
     * @return array<int, string>
     */
    public static function requiredMediaTypes(): array
    {
        $types = [];
        foreach (self::documentCatalog() as $key => $def) {
            if ($def['group'] === 'required') {
                $types = array_merge($types, self::docMediaTypes($key, $def['sides']));
            }
        }

        return $types;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorPartnerDocument::class);
    }

    public function isActive(): bool
    {
        return $this->status === VendorProfileStatus::Active;
    }

    /** Every required document has been verified. */
    public function kycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    /**
     * One row per catalog media slot (front/back expanded) with its uploaded
     * document, for the admin verification table and the vendor upload page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function kycRows(): array
    {
        $docsByType = ($this->relationLoaded('documents') ? $this->documents : $this->documents()->with('verifier:id,name')->get())->keyBy('type');
        $rows = [];

        foreach (self::documentCatalog() as $key => $def) {
            foreach (self::docMediaTypes($key, $def['sides']) as $type) {
                $side = $def['sides'] === 2 ? (str_ends_with($type, '_front') ? 'Front' : 'Back') : null;
                /** @var VendorPartnerDocument|null $doc */
                $doc = $docsByType->get($type);

                $rows[] = [
                    'type' => $type,
                    'label' => $def['label'].($side ? " ({$side})" : ''),
                    'group' => $def['group'],
                    'document' => $doc ? [
                        'id' => $doc->id,
                        'file_path' => $doc->file_path,
                        'status' => $doc->status,
                        'number' => $doc->number,
                        'remarks' => $doc->remarks,
                        'original_name' => $doc->original_name,
                        'verified_by_name' => $doc->verifier?->name,
                        'uploaded_at' => $doc->created_at?->toDateString(),
                    ] : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * Recompute the denormalised KYC summary from the uploaded documents:
     * verified (all required verified) | submitted (all required present) | pending.
     */
    public function kycStatusFromDocuments(): string
    {
        $required = self::requiredMediaTypes();
        $docs = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        $present = $docs->whereIn('type', $required);
        if ($present->pluck('type')->unique()->count() < count($required)) {
            return 'pending';
        }

        return $present->every(fn (VendorPartnerDocument $d) => $d->status === 'verified') ? 'verified' : 'submitted';
    }
}
