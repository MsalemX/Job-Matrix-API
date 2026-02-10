<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
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
            'section_id' => 'required|exists:project_sections,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'assigned_to' => 'nullable|exists:users,id',
            'deadline' => 'nullable|date',
            'points' => 'nullable|integer|min:0',
            'depends_on' => 'nullable|array',
            'depends_on.*' => 'exists:tasks,id',
        ];
    }
}
