<?php

namespace BikeShare\Domain\Core;

use Dingo\Api\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class Request extends FormRequest
{

    public function prepareForValidation()
    {
        //
    }


    /**
     * Replace the input with sanitized values before calling rules()
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        return parent::getValidatorInstance()->after(function ($validator) {
            $this->after($validator);
        });
    }


    public function after(Validator $validator)
    {
        //
    }
}