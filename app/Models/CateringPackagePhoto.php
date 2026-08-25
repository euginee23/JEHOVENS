<?php

namespace App\Models;

use Database\Factories\CateringPackagePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $catering_package_id
 * @property-read CateringPackage $cateringPackage
 */
#[Fillable(['catering_package_id', 'path', 'alt', 'sort_order'])]
class CateringPackagePhoto extends Photo
{
    /** @use HasFactory<CateringPackagePhotoFactory> */
    use HasFactory;

    /**
     * The record this photo belongs to.
     *
     * @return BelongsTo<CateringPackage, $this>
     */
    public function cateringPackage(): BelongsTo
    {
        return $this->belongsTo(CateringPackage::class);
    }
}
