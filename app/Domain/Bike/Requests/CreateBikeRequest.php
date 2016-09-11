<?php
namespace BikeShare\Domain\Bike\Requests;

use Dingo\Api\Http\FormRequest;

class CreateBikeRequest extends FormRequest
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
            'bike_num' => 'required|unique:bikes,bike_num'
        ];
    }
}
