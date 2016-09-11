<?php
namespace BikeShare\Domain\Auth\Requests;

use Dingo\Api\Http\FormRequest;

class RegisterRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'         => 'required',
            'email'        => 'required|unique:users,email',
            'phone_number' => 'required|unique:users,phone_number',
            'password'     => 'required|confirmed',
        ];
    }
}
