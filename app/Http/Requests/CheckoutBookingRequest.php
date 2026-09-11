<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckoutBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', 'exists:parents,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'trial_class_id' => ['required', 'integer', 'exists:trial_classes,id'],
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'simulate_failure' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.required' => 'Parent is required.',
            'parent_id.exists' => 'Selected parent does not exist.',
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'Selected student does not exist.',
            'trial_class_id.required' => 'Trial class is required.',
            'trial_class_id.exists' => 'Selected trial class does not exist.',
            'payment_method.required' => 'Payment method is required.',
        ];
    }
}
