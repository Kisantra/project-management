<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Last sequence handed out per prefix and period. Rows are created lazily
 * and can be edited from the numbering settings page.
 */
class LetterNumberCounter extends Model
{
    protected $fillable = ['prefix', 'period_key', 'last_sequence'];

    protected $casts = ['last_sequence' => 'integer'];
}
