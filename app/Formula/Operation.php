<?php

namespace App\Formula;

use Carbon\Carbon;
use Nette\Utils\Tokenizer;

class Operation
{
    const T_ARITHMETIC = 1;

    const T_LOG_COMP = 2;

    const T_TEXTUAL = 4;

    const T_DATE = 10;

    const T_LITERAL_NUMBER = 8;

    const T_LITERAL_STRING = 9;

    /**
     * Default decimal places for arithmetic value
     *
     * @var int
     */
    private static $decimalPlaces = null;

    public static function setDecimalPlaces($decimalPlaces)
    {
        static::$decimalPlaces = $decimalPlaces;
    }

    public static function getDecimalPlaces()
    {
        return static::$decimalPlaces;
    }

    /**
     * @param  array  $operandToken1
     * @param  array  $operatorToken
     * @param  array  $operandToken2
     *
     * @returns float
     *
     * @throws \Exception
     */
    public static function parseArithmetic($operandToken1, $operatorToken, $operandToken2)
    {

        [$operand1, $operand2, $operator] = static::parseTermTokens($operandToken1, $operandToken2, $operatorToken, static::T_ARITHMETIC);

        $type = null;
        switch ($operator) {
            case '+':
                [$result, $type] = static::add($operand1, $operand2, $operandToken1, $operandToken2);
                break;
            case '-':
                [$result, $type] = static::subtract($operand1, $operand2, $operandToken1, $operandToken2);
                break;
            case '*':
                $result = $operand1 * $operand2;
                break;
            case '/':
                if (! $operand1 || ! $operand2) {
                    $result = 0;
                } else {
                    $result = $operand1 / $operand2;
                }

                break;
            case '^':
                $result = pow($operand1, $operand2);
                break;
            default:
                throw new \Exception('Syntax error. Unidentified operator '.$operator);
        }
        if ($type === static::T_DATE) {
            return $result;
        } elseif (static::$decimalPlaces === null) {
            return $result;
        } else {
            return number_format((float) $result, 0, '.', '');
        }
    }

    protected static function add($operand1, $operand2, $token1, $token2, $numberDateSuffix = 'days')
    {
        if ($token1[Tokenizer::TYPE] == static::T_LITERAL_NUMBER && $token1[Tokenizer::TYPE] == static::T_LITERAL_NUMBER) {
            return [
                $operand1 + $operand2,
                static::T_LITERAL_NUMBER,
            ];
        } elseif ($token1[Tokenizer::TYPE] == static::T_LITERAL_NUMBER && $token2[Tokenizer::TYPE] == static::T_DATE) {
            return [
                (new Carbon($operand2.' + ' + $operand1.' '.$numberDateSuffix))->toDateString(),
                static::T_DATE,
            ];
        } elseif ($token1[Tokenizer::TYPE] == static::T_DATE && $token2[Tokenizer::TYPE] == static::T_LITERAL_NUMBER) {
            return [
                (new Carbon($operand1.' + '.$operand2.' ' + $numberDateSuffix))->toDateString(),
                static::T_DATE,
            ];
        } else {
            throw new \Exception('Error. Invalid arithmetic or date operation');
        }
    }

    protected static function subtract($operand1, $operand2, $token1, $token2, $numberDateSuffix = 'days')
    {

        if ($token1[Tokenizer::TYPE] == static::T_LITERAL_NUMBER && $token1[Tokenizer::TYPE] == static::T_LITERAL_NUMBER) {

            return [
                (float) $operand1 - (float) $operand2,
                static::T_LITERAL_NUMBER,
            ];
        } elseif ($token1[Tokenizer::TYPE] == static::T_DATE && $token2[Tokenizer::TYPE] == static::T_DATE) {
            return [
                (new Carbon($operand1))->diffInDays((new Carbon($operand2))),
                static::T_LITERAL_NUMBER,
            ];
        } elseif ($token1[Tokenizer::TYPE] == static::T_LITERAL_NUMBER && $token2[Tokenizer::TYPE] == static::T_DATE) {
            return [
                (new Carbon($operand2.' - ' + $operand1.' '.$numberDateSuffix))->toDateString(),
                static::T_DATE,
            ];
        } elseif ($token1[Tokenizer::TYPE] == static::T_DATE && $token2[Tokenizer::TYPE] == static::T_LITERAL_NUMBER) {
            return [
                (new Carbon($operand1.' - '.$operand2.' '.$numberDateSuffix))->toDateString(),
                static::T_DATE,
            ];
        } else {

            throw new \Exception('Error. Invalid arithmetic or date operation');
        }
    }

    /**
     * @param  array  $operandToken1
     * @param  array  $operatorToken
     * @param  array  $operandToken2
     *
     * @throws \Exception
     */
    public static function parseLogComp($operandToken1, $operatorToken, $operandToken2)
    {

        [$operand1, $operand2, $operator] = static::parseTermTokens($operandToken1, $operandToken2, $operatorToken, static::T_LOG_COMP);

        switch ($operator) {
            case '&&':
                return $operand1 && $operand2;
            case '||':
                return $operand1 || $operand2;
            case '<=':
                return $operand1 <= $operand2;
            case '>=':
                return $operand1 >= $operand2;
            case '<':
                return $operand1 < $operand2;
            case '>':
                return $operand1 > $operand2;
            case '!=':
            case '<>':
                return $operand1 != $operand2;
            case '==':
            case '=':
                return $operand1 == $operand2;
            default:
                throw new \Exception('INVALID_LOGICAL_COMPARISON_OPERATOR. Unidentified operator '.$operator);
        }
    }

    public static function parseTextual($operandToken1, $operatorToken, $operandToken2)
    {
        [$operand1, $operand2, $operator] = static::parseTermTokens($operandToken1, $operandToken2, $operatorToken, static::T_TEXTUAL);

        if ($operator == '&') {
            return stringify($operand1).stringify($operand2);
        } else {
            throw new \Exception('INVALID_TEXT_OPERATOR. Unidentified operator '.$operator);
        }
    }

    public static function parseTermTokens($operandToken1, $operandToken2, $operatorToken, $operatorType)
    {

        $operand1 = static::evaluateOperand($operandToken1, $operatorType);
        $operand2 = static::evaluateOperand($operandToken2, $operatorType);

        $operator = $operatorToken[Tokenizer::VALUE];

        return [$operand1, $operand2, $operator];
    }

    /**
     * @param  array  $operandToken
     * @param  int  $operatorType
     * @return int|string
     *
     * @throws \Exception
     */
    public static function evaluateOperand($operandToken, $operatorType)
    {

        if (is_array($operandToken)) {
            $value = $operandToken[Tokenizer::VALUE];
            if ($operandToken[Tokenizer::TYPE]) {
                $value = str_replace('"', '', $value);
            }
        } else {
            $value = $operandToken;
        }

        if ($operandToken[Tokenizer::VALUE] === 'null') {
            return null;
        } elseif ($operatorType == static::T_ARITHMETIC) {
            //            try {
            //                $test = Carbon::createFromFormat('Y-m-d', $value);
            //            } catch(\Exception $e) {
            //                dd($value);
            //            }
            $value = str_replace('"', '', $value);
            // dd($value, $v);

            if (is_numeric($value)) {
                return $value + 0;
            } elseif ($value && ! (is_valid_date($value) || is_valid_date($value, 'Y-m-d H:i:s'))) {
                throw new \Exception('INVALID_ARITHMETIC_OPERAND. The operand '.$value.' is not a valid arithmetic operand');
            }

            return $value;
        } elseif ($operatorType == static::T_TEXTUAL) {
            return stringify($value);
        } else {
            return $value;
        }

    }

    public static function getOperatorList()
    {
        $list = collect([
            ['symbol' => '+', 'name' => 'Add', 'returnType' => ['number', 'date']],
            ['symbol' => '-', 'name' => 'Subtract', 'returnType' => ['number', 'date']],
            ['symbol' => '*', 'name' => 'Multiply', 'returnType' => ['number']],
            ['symbol' => '/', 'name' => 'Divide', 'returnType' => ['number']],
            ['symbol' => '^', 'name' => 'Exponentiation', 'returnType' => ['number']],
            ['symbol' => '(', 'name' => 'Open Parenthesis', 'returnType' => ['number']],
            ['symbol' => ')', 'name' => 'Close Parenthesis', 'returnType' => ['number']],
            ['symbol' => '&', 'name' => 'Concatenate', 'returnType' => ['text']],
            ['symbol' => '=', 'name' => 'Equal', 'returnType' => ['boolean']],
            ['symbol' => '<>', 'name' => 'Not Equal', 'returnType' => ['boolean']],
            ['symbol' => '<', 'name' => 'Less Than', 'returnType' => ['boolean']],
            ['symbol' => '>', 'name' => 'Greater Than', 'returnType' => ['boolean']],
            ['symbol' => '<=', 'name' => 'Less Than', 'returnType' => ['boolean']],
            ['symbol' => '>=', 'name' => 'Greater Than', 'returnType' => ['boolean']],
            ['symbol' => '&&', 'name' => 'And', 'returnType' => ['boolean']],
            ['symbol' => '||', 'name' => 'Or', 'returnType' => ['boolean']],
        ]);

        return $list;
    }
}
