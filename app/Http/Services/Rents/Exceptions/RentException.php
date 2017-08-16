<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

class RentException extends \LogicException
{
    /**
     * @var RentExceptionType
     */
    public $type;

    /**
     * @var mixed
     */
    public $param1;

    /**
     * @var mixed
     */
    public $param2;

    /**
     * RentException constructor.
     * @param RentExceptionType $type
     * @param null|mixed $param1
     * @param null|mixed $param2
     */
    public function __construct(RentExceptionType $type, $param1 = null, $param2 = null)
    {
        $this->type = $type;
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}