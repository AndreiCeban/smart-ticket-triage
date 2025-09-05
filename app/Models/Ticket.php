<?php

/**
 * TODO: Ticket Model
 * 
 * Requirements from specification:
 * - id (ULID) - primary key
 * - subject string - ticket subject
 * - body text - ticket description
 * - status enum - open, in_progress, resolved, closed
 * - Additional fields: category, confidence, explanation, note, manually_categorized
 * - Support for AI classification with manual override capability
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'subject',
        'body',
        'status',
        'category',
        'confidence',
        'explanation',
        'note',
        'manually_categorized',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'manually_categorized' => 'boolean',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const CATEGORIES = [
        'technical' => 'Technical Support',
        'billing' => 'Billing & Payment',
        'account' => 'Account Management',
        'feature_request' => 'Feature Request',
        'bug_report' => 'Bug Report',
        'general' => 'General Inquiry',
    ];
}
