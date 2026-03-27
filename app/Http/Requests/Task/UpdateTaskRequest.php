<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => 'sometimes|exists:project_sections,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'assigned_to' => 'nullable|exists:users,id',
            'deadline' => 'nullable|date',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'points' => 'sometimes|integer|min:0',
            'is_archived' => 'sometimes|boolean',
        ];
    }
}
