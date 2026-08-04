<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'verifier_user_id',
    'result',
    'notes',
    'verification_latitude',
    'verification_longitude',
    'visit_date',
    'checklist_json',
    'justifications_json',
    'front_photo',
    'id_with_person_photo',
    'proof_of_address_photo',
    'additional_evidence_json',
    'distance_meters',
])]
final class ApplicationVerification extends Model
{
    protected $casts = [
        'result' => VerificationResult::class,
        'verification_latitude' => 'decimal:7',
        'verification_longitude' => 'decimal:8',
        'visit_date' => 'datetime',
        'checklist_json' => 'array',
        'justifications_json' => 'array',
        'additional_evidence_json' => 'array',
        'distance_meters' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }
}
