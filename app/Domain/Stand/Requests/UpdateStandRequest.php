<?php
namespace BikeShare\Domain\Stand\Requests;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use Dingo\Api\Http\FormRequest;

class UpdateStandRequest extends FormRequest
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
        $stand = app(StandsRepository::class)->findByUuid($this->route('uuid'));

        return [
            'name' => 'required|unique:stands,name,' . $stand->id,
            'latitude' => 'nullable',
            'longitude' => 'nullable',
        ];
    }
}
