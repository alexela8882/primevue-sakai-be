<?php

namespace App\Formula;

trait ErrorTrait
{
    public function throwParsingError($errorCode, $msg = null)
    {
        switch ($errorCode) {
            case 'FUNC_PAR_MISMATCH':
                throw new \Exception($msg ?: $errorCode.' Function definition error. Parenthesis mismatch');
            case 'PAR_MISMATCH':
                throw new \Exception($msg ?: $errorCode.' Expression error. Parenthesis mismatch');
            case 'MISSING_OPERATOR':
                throw new \Exception($msg ?: $errorCode.' Syntax error. Missing operator in between.');
            case 'INVALID_OPERAND':
                throw new \Exception($msg ?: $errorCode.' Syntax error. The operands are not valid for the specific operator');
            case 'FUNC_ARG_MISSING':
                throw new \Exception($msg ?: $errorCode.' Syntax error. Function argument missing');
            case 'RETURN_TYPE_UNSET':
                throw new \Exception($msg ?: $errorCode.' Error. Return Type is unset.');
            case 'RETURN_TYPE_UNRECOGNIZED':
                throw new \Exception($msg ?: $errorCode.' Error. Return Type is unrecognized.');
            case 'ENTITY_UNSPECIFIED':
                throw new \Exception($msg ?: $errorCode.' Error. Entity not specified.');
            case 'ENTITY_INSTANCE_ID_UNRECOGNIZED':
                throw new \Exception($msg ?: $errorCode.' Error. Entity instance Id unrecognized.');
            case 'UNEXPECTED_VALUE':
                throw new \Exception($msg ?: $errorCode.' Error. Unexpected return value');
            default:
                throw new \Exception($msg ?: $errorCode.' Error has occured');
        }
    }
}
