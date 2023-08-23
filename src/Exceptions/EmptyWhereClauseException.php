<?php

namespace Phyple\Larascaff\Exceptions;

use RuntimeException;

class EmptyWhereClauseException extends RuntimeException
{
    protected $message = "Can't perform action with empty where clause";
}