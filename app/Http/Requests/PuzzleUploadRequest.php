<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class PuzzleUploadRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'puzzle_file' => ['nullable', 'file', 'max:100'],
            'puzzle_json' => ['nullable', 'string', 'max:100000'],
            'difficulty' => ['required', Rule::in(['gentle', 'tricky', 'expert'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
    public function documentText(): string
    {
        if ($this->hasFile('puzzle_file') && filled($this->validated('puzzle_json'))) {
            throw ValidationException::withMessages(['puzzle_json' => 'Choose a file or paste JSON, rather than supplying both.']);
        }
        return $this->hasFile('puzzle_file') ? $this->file('puzzle_file')->get() : ($this->validated('puzzle_json') ?? '');
    }
}
