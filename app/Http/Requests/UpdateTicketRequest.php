<?php


namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(array_keys(Ticket::STATUSES))],
            'category' => ['sometimes', 'nullable', Rule::in(array_keys(Ticket::CATEGORIES))],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
