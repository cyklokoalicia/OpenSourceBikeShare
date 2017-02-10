<?php
namespace BikeShare\Domain\Rent\Requests;

use BikeShare\Domain\Core\Request;

class CreateRentRequest extends Request
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
            'bike' => 'required',
            'test' => 'string',
        ];
    }

    public function prepareForValidation()
    {

    }
}
