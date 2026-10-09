<?php

namespace App\Models;

use Database\Factories\ResortSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * The resort's own details, as staff set them from Settings → Mail.
 *
 * A single row. Guest emails read the contact from here — as the Reply-To address and
 * in the line telling the guest how to reach the resort — so a guest replying to their
 * receipt reaches the inbox staff actually watch, not the no-reply sending address.
 *
 * @property int $id
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['contact_email', 'contact_phone'])]
class ResortSetting extends Model
{
    /** @use HasFactory<ResortSettingFactory> */
    use HasFactory;

    /**
     * The resort's settings, or a blank unsaved row before staff have filled any in.
     *
     * The oldest row, so there is always exactly one answer even if a second row ever
     * appears. A failed read — the table not yet migrated on a fresh deploy — is reported
     * and treated as blank: every guest email reads this, and a receipt must still go out
     * without its contact line rather than not at all.
     */
    public static function current(): self
    {
        try {
            return static::query()->oldest('id')->firstOrNew();
        } catch (QueryException $exception) {
            report($exception);

            return new self;
        }
    }

    /**
     * Whether staff have given guests any way to get in touch.
     */
    public function hasContact(): bool
    {
        return filled($this->contact_email) || filled($this->contact_phone);
    }
}
