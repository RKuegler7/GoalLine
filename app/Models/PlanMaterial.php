<?php

namespace App\Models;

use App\RoadmapStatus;
use Database\Factories\PlanMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Fillable(['original_name', 'path', 'mime_type', 'size', 'note'])]
class PlanMaterial extends Model
{
    /** @use HasFactory<PlanMaterialFactory> */
    use HasFactory;

    /**
     * File types people can upload, as extensions for validation.
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];

    /**
     * Largest upload, in kilobytes.
     */
    public const MAX_KILOBYTES = 10240;

    /**
     * Most materials a single goal can hold.
     */
    public const MAX_PER_GOAL = 5;

    /**
     * Uploads are kept on the private disk, never under public/.
     */
    public const DISK = 'uploads';

    /**
     * Default values for new materials.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RoadmapStatus::class,
            'digest' => 'array',
        ];
    }

    /**
     * Validation rules for an upload.
     *
     * @return array<string, list<string>>
     */
    public static function rules(bool $required = true): array
    {
        return [
            'material' => [$required ? 'required' : 'nullable', 'file', 'mimes:'.implode(',', self::ALLOWED_EXTENSIONS), 'max:'.self::MAX_KILOBYTES],
            'material_note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Save an uploaded file privately and record it against the goal.
     */
    public static function storeUpload(Goal $goal, UploadedFile $file, ?string $note): self
    {
        return $goal->materials()->create([
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'path' => $file->store("plan-materials/{$goal->id}", self::DISK),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'note' => $note,
        ]);
    }

    /**
     * Remove the stored file along with the record.
     */
    protected static function booted(): void
    {
        static::deleted(fn (PlanMaterial $material) => Storage::disk(self::DISK)->delete($material->path));
    }

    /**
     * The goal this material shapes.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Whether the file is a PDF rather than an image.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * The file's contents, base64-encoded for Claude.
     */
    public function base64Contents(): string
    {
        return base64_encode(Storage::disk(self::DISK)->get($this->path));
    }
}
