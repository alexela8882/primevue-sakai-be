<?php

namespace App\Services;

use App\Builders\EntityField;
use App\Formula\ErrorTrait;
use App\Formula\Operation;
use App\Formula\TokenIterator;
use App\Models\Core\FieldType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Nette\Utils\Tokenizer;

class FormulaParser
{
    use ErrorTrait;

    const T_FUNCTION = 1;

    const T_PARENTHESIS = 2;

    const T_ARITHMETIC_OP = 3;

    const T_CONCAT_OP = 4;

    const T_COMPARISON_OP = 5;

    const T_LOGICAL_OP = 6;

    const T_LITERAL_BOOLEAN = 7;

    const T_LITERAL_NUMBER = 8;

    const T_LITERAL_STRING = 9;

    const T_LITERAL_DATE = 10;

    const T_VARIABLE = 11;

    const T_ARG_SEPARATOR = 12;

    const T_NULL = 13;

    const LIMIT_START = 0;

    const LIMIT_END = 1;

    const FUNC_NAME = 0;

    const FUNC_ARGUMENT = 1;

    const RETURN_TYPE = 2;

    const NO_ITERATION = 0;

    private static $operands = [self::T_LITERAL_BOOLEAN, self::T_LITERAL_NUMBER, self::T_LITERAL_STRING, self::T_VARIABLE, self::T_LITERAL_DATE, self::T_FUNCTION, self::T_NULL];

    /**
     * @var \Nette\Utils\Tokenizer
     */
    private $tokenizer;

    private $tokens;

    private $firstTokens;

    private $entity;

    private $entityModel;

    private $entityInstance;

    private $relatedObjects = [];

    private $returnType = null;

    private $formulaFieldType;

    private $field;

    private $oldInstance;

    public function __construct()
    {

        $this->formulaFieldType = FieldType::where(['name' => 'formula'])->first();
        $this->tokenizer = new Tokenizer([
            T_WHITESPACE => '\s+',
            self::T_FUNCTION => 'IF|CASE|AND|OR|NOT|ISBLANK|BLANKVALUE|CONTAINS|LEFT|RIGHT|ROUND|MOD|BEGINS|ABS|LEN|LOG|LOWER|ISPICKVAL|NOW',
            self::T_ARG_SEPARATOR => ',',
            self::T_PARENTHESIS => '\(|\)',
            self::T_LOGICAL_OP => '&&|\|\|',
            self::T_CONCAT_OP => '&',
            self::T_ARITHMETIC_OP => '\^|\*|/|\+|\-',
            self::T_COMPARISON_OP => '<=|>=|<>|<|>|!=|==|=',
            self::T_NULL => 'null',
            self::T_LITERAL_BOOLEAN => 'true|false',
            self::T_LITERAL_NUMBER => '[0-9]+\.[0-9]+|[0-9]+',
            self::T_VARIABLE => '\$?[a-zA-Z_\x7f-\xff\.][a-zA-Z0-9_\x7f-\xff\.]*',
            self::T_LITERAL_STRING => '"[^\"]*"',
        ]);
    }

    protected function getLiteralName($type)
    {
        switch ($type) {
            case self::T_LITERAL_BOOLEAN:
                return 'boolean';
            case self::T_LITERAL_STRING:
                return 'string';
            case self::T_LITERAL_NUMBER:
                return 'number';
            case self::T_NULL:
                return 'null';
            default:
                return 'unknown';
        }
    }

    public function trimToken()
    {
        $newTokens = [];
        $iterator = new TokenIterator($this->tokens);
        while ($iterator->nextToken()) {
            $iterator->skipWhile(T_WHITESPACE);
            $newTokens[] = $iterator->currentToken();
        }
        $this->tokens = $newTokens;
    }

    public function resolveReturnType($return)
    {

        $type = $this->formulaFieldType->formulaReturnTypes()->where('name', $return)->first();

        if (! $type) {
            return $this->throwParsingError('RETURN_TYPE_UNRECOGNIZED');
        } else {
            return $type;
        }

    }

    public function parse($input, $return = null)
    {

        if (! $this->entity) {
            $this->throwParsingError('ENTITY_UNSPECIFIED');
        }

        if ($return) {
            $this->returnType = $this->resolveReturnType($return);
        }

        if (! $this->returnType) {
            $this->throwParsingError('RETURN_TYPE_UNSET');
        }

        // tokenize the input
        if ($this->tokens) {
            $this->firstTokens = $this->tokens;
        }

        $this->tokens = $this->tokenizer->tokenize($input);
        // dump($input);
        // dump($this->tokens);
        $this->trimToken();

        $resultToken = $this->parseExpression();

        $result = $resultToken[Tokenizer::VALUE];

        if (is_numeric($result)) {
            $result = $result + 0;
        }
        $this->tokens = $this->firstTokens;

        return $result;
    }

    /**
     * @param  int  $startPosition
     * @param  null  $endPosition
     * @param  int  $iterationCnt
     * @param  array|null  $return
     * @return mixed
     *
     * @throws \Exception
     */
    public function parseExpression($startPosition = -1, $endPosition = null, $iterationCnt = 0, $return = null)
    {
        if ($startPosition == $endPosition) {
            $resultToken = $this->tokens[$startPosition];
            $this->validateReturnType($resultToken, $return);

            return $resultToken;
        }
        if ($iterationCnt > 0) {
            $startPosition--;
        }
        $iterator = new TokenIterator($this->tokens);
        $iterator->setPositions($startPosition, $endPosition);

        $operatorStack = collect([]);
        $postFix = collect([]);
        $parenCtr = 0;
        while ($iterator->nextToken() && $iterator->isPositionUnrestricted()) {
            // if the current token is an operand
            if ($iterator->isCurrentIn(self::$operands)) {

                if ($iterator->isCurrent(self::T_FUNCTION)) {
                    // get the function name
                    $functionName = $iterator->currentValue();
                    $startPosition = $iterator->currentPosition();
                    $operandToken = $this->parseFunction($functionName, $iterator, $startPosition);

                    $iterator->shiftPosition(1);
                } else {
                    $operandToken = $iterator->currentToken();
                }

                $postFix->push($operandToken);
            } else { // else, if it an operator

                if ($iterator->isCurrent(self::T_PARENTHESIS)) {
                    if ($iterator->isCurrent('(')) {
                        $parenCtr++;
                    } else {
                        $parenCtr--;
                    }

                    if ($parenCtr < 0) {
                        $this->throwParsingError('PAR_MISMATCH');
                    }
                }

                $operatorToken = $iterator->currentToken();
                $topOperatorToken = null;

                // while top operator is higher than incoming operator, pop operator and push it on postFix stack
                while ($operatorStack->isNotEmpty() && $operatorStack->last()[Tokenizer::VALUE] != '('
                    && $this->operatorPrecedes($operatorStack->last()[Tokenizer::VALUE], $operatorToken[Tokenizer::VALUE])
                        || ($topOperatorToken && $topOperatorToken[Tokenizer::VALUE] != '(' && $operatorToken[Tokenizer::VALUE] == ')')) {

                    $topOperatorToken = $operatorStack->pop();

                    if (! in_array($topOperatorToken[Tokenizer::VALUE], ['(', ')'])) {
                        $postFix->push($topOperatorToken);
                    }
                }

                if (! in_array($operatorToken[Tokenizer::VALUE], ['(', ')'])) {
                    $operatorStack->push($operatorToken);
                }

            }

        }

        // If operator stack is not yet empty, pop them and push them all to postFix stack

        while ($operatorStack->isNotEmpty()) {
            $postFix->push($operatorStack->pop());
        }

        return $this->parsePostFixExpression($postFix, $return);
    }

    /**
     * @param  null|array  $return
     * @return array|mixed
     *
     * @throws \Exception
     */
    protected function parsePostFixExpression(Collection $postFix, $return = null)
    {
        $iterator = new TokenIterator($postFix->toArray());
        $resultStack = collect([]);

        while ($iterator->nextToken()) {

            $currentToken = $iterator->currentToken();

            // if it is an operand, push it on result stack
            if ($iterator->isCurrentIn(self::$operands)) {
                $resultStack->push($currentToken);
            } else {

                $operandToken2 = $this->popOperand($resultStack, $currentToken);
                $operandToken1 = $this->popOperand($resultStack, $currentToken);

                // parse term
                $resultToken = $this->parseTerm($operandToken1, $currentToken, $operandToken2);
                $resultStack->push($resultToken);
            }
        }

        $resultToken = $resultStack->pop();
        // dump('result',$resultToken);
        $this->validateReturnType($resultToken, $return);

        return $resultToken;
    }

    protected function validateReturnType($resultToken, $return)
    {
        if ($return && $resultToken[Tokenizer::TYPE] != $return[static::RETURN_TYPE]) {
            return $this->throwParsingError('UNEXPECTED_VALUE', 'Error. Expected '.$this->getLiteralName($return[static::RETURN_TYPE]).' for argument '.$return[static::FUNC_ARGUMENT].' of function '.$return[static::FUNC_NAME].', '.$this->getLiteralName($resultToken[Tokenizer::TYPE]).' returned');
        }
    }

    protected function parseTerm($operandToken1, $operatorToken, $operandToken2)
    {

        switch ($operatorToken[Tokenizer::TYPE]) {
            case static::T_ARITHMETIC_OP:
                $resultTokenType = static::T_LITERAL_NUMBER;
                $result = Operation::parseArithmetic($operandToken1, $operatorToken, $operandToken2);
                break;

            case static::T_COMPARISON_OP:
            case static::T_LOGICAL_OP:
                $resultTokenType = static::T_LITERAL_BOOLEAN;
                $result = Operation::parseLogComp($operandToken1, $operatorToken, $operandToken2);
                break;

            case static::T_CONCAT_OP:
                $resultTokenType = static::T_LITERAL_STRING;
                $result = Operation::parseTextual($operandToken1, $operatorToken, $operandToken2);
                break;
            default:
                $this->throwParsingError('INVALID_OPERAND', 'Unidentified operator '.$operatorToken[Tokenizer::VALUE]);
        }

        $token = [$result, $operatorToken[Tokenizer::OFFSET], $resultTokenType];

        return $token;
    }

    protected function popOperand(&$resultStack, $operatorToken)
    {
        if ($resultStack->isEmpty()) {
            $this->throwParsingError('MISSING_OPERATOR', 'Missing operand/s for operator '.$operatorToken[Tokenizer::VALUE]);
        }
        $operand = $resultStack->pop();

        return $this->parseOperand($operand);

    }

    protected function parseOperand($operandToken)
    {
        // Add evaluation of field value here...

        $value = $operandToken[Tokenizer::VALUE];
        if ($value === true) {
            $operandToken[Tokenizer::VALUE] = 'true';
        } elseif ($value === false) {
            $operandToken[Tokenizer::VALUE] = 'false';
        } elseif ($operandToken[Tokenizer::TYPE] == self::T_VARIABLE) {
            // if it is related object
            if (starts_with($value, '$')) {

                // TODO: resolve object and value
            } else {

                $operand = (new EntityField)->checkInstanceFieldValue($this->entity, $this->entityInstance, $value);

                $tval = static::T_NULL;

                if (is_string($operand)) {
                    $operand = str_replace('"', '', $operand);
                }

                if (is_numeric($operand)) {
                    $tval = self::T_LITERAL_NUMBER;
                } elseif ($operand === null) {
                    $tval = self::T_NULL;
                } elseif (is_valid_date($operand) || is_valid_date($operand, 'Y-m-d H:i:s')) {
                    $tval = self::T_LITERAL_DATE;
                } elseif (is_string($operand)) {
                    $tval = self::T_LITERAL_STRING;
                } elseif (is_bool($operand)) {
                    $tval = self::T_LITERAL_BOOLEAN;
                } elseif ($operand === null) {
                    $tval = self::T_NULL;
                }

                $operandToken = $this->createToken($operand, 0, $tval);
                // }
            }
        } elseif ($operandToken[Tokenizer::TYPE] == self::T_LITERAL_STRING) {

            $str = str_replace('"', '', $operandToken[Tokenizer::VALUE]);
            if (is_valid_date($str, 'Y-m-d') || is_valid_date($str, 'Y-m-d H:i:s')) {
                $operandToken = $this->createToken($str, 0, self::T_LITERAL_DATE);

            }
        } elseif ($operandToken[Tokenizer::TYPE] == self::T_LITERAL_DATE) {

        } elseif ($operandToken[Tokenizer::TYPE] == self::T_LITERAL_NUMBER) {
            $str = floatval($operandToken[Tokenizer::VALUE]);
            $operandToken = $this->createToken($str, 0, self::T_LITERAL_NUMBER);
        } else {

        }

        return $operandToken;
    }

    public function getEntity()
    {
        return $this->entity->name;
    }

    protected function operatorPrecedes($operator1, $operator2)
    {
        return $this->checkOperatorLevel($operator1) < $this->checkOperatorLevel($operator2);
    }

    public function checkOperatorLevel($tokenType)
    {
        switch ($tokenType) {
            case '(':
            case ')':
                return 0;
            case '^':
                return 1;
            case '*':
            case '/':
            case '%':
                return 2;
            case '&':
                return 3;
            case '+':
            case '-':
                return 4;
            case '<=':
            case '>=':
            case '<':
            case '>':
            case '==':
            case '!=':
                return 5;
            case '&&':
                return 6;
            case '||':
                return 7;
        }
    }

    /**
     * @return $this
     */
    public function setEntity($entity, $field = null)
    {
        $this->entity = (new EntityField)->resolveEntity($entity);
        $this->entityModel = $this->entity->getModel();
        if ($field) {
            $this->field = $this->entity->fields()->where('name', $field)->first();
        }
        // TODO: Add related objects to the entity (e.g., $user as the current user)

        return $this;
    }

    public function setInstance($modelId)
    {
        if (! $this->entity) {
            $this->throwParsingError('ENTITY_UNSPECIFIED');
        }

        if (is_string($modelId)) {
            $this->entityInstance = $this->entityModel->find($modelId);
            if (! $this->entityInstance) {
                if ($this->oldInstance) {
                    $this->entityInstance = $this->entityModel->find($this->oldInstance->_id);
                }

                if (! $this->entityInstance) {
                    $this->throwParsingError('ENTITY_INSTANCE_ID_UNRECOGNIZED');
                }
            }
        } else {

            $this->entityInstance = $modelId;
        }

        return $this;
    }

    /**
     * @returns $this
     */
    public function setReturnType($return)
    {
        $this->returnType = $this->resolveReturnType($return);

        return $this;
    }

    /**
     * @param  int  $value
     *
     * @returns $this
     */
    public function setDecimalPlaces($value)
    {

        Operation::setDecimalPlaces($value);

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function parseFunction($functionName, TokenIterator &$iterator, $startPosition)
    {

        // find the end of the function definition
        $endPosition = $this->scanParenthesisEnd($iterator);
        $startPosition++;

        $parserMethodName = 'parseFn'.title_case(strtolower($functionName));

        if (method_exists($this, $parserMethodName)) {
            return $this->$parserMethodName($startPosition, $endPosition);
        } else {
            $this->throwParsingError('Unknown function named '.$functionName);
        }
    }

    public function parseField($field, $model, $first = false, $exp = null)
    {
        $this->field = $field;

        if ($first) {
            $this->oldInstance = $model;
        }

        $this->setEntity($field->entity)->setReturnType($field->formulaType)
            ->setInstance($model->_id);

        (new RollUpSummaryResolver)->setEntity($field->entity);

        if ($field->decimalPlace) {
            $this->setDecimalPlaces($field->decimalPlace);
        }

        return $this->parse($exp ? $exp : $field->formulaExpression);
    }

    /*************************************** Functions ******************************************************************/

    /**
     * @param  bool  $isFunction
     * @return mixed
     *
     * @throws \Exception
     */
    protected function scanParenthesisEnd(&$iterator, $isFunction = true)
    {
        // If the next token after a function name is not opening parenthesis, throw error
        if ($isFunction) {
            $iterator->nextToken();
        }

        if (! $iterator->isCurrent('(')) {
            $this->throwParsingError(($isFunction) ? 'FUNC_PAR_MISMATCH' : 'PAR_MISMATCH');
        }

        // find the pair of the parenthesis
        $parenCtr = 1;
        while ($iterator->nextToken() && $parenCtr && $iterator->isPositionUnrestricted()) {

            if ($iterator->isCurrent(')')) {
                $parenCtr--;
            } elseif ($iterator->isCurrent('(')) {
                $parenCtr++;
            }
        }
        if ($parenCtr) {
            $this->throwParsingError(($isFunction) ? 'FUNC_PAR_MISMATCH' : 'PAR_MISMATCH');
        }

        return $iterator->previousPosition(2);
    }

    /**
     * @param  string  $functionName
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @param  int|string  $argCnt
     * @param  int|null  $argsOptionalMin
     * @return Collection|void
     *
     * @throws \Exception
     */
    protected function getArgumentLimits($functionName, $startPosition, $endPosition, $argCnt = 'AUTO', $argsOptionalMin = null)
    {

        $iterator = new TokenIterator($this->tokens);
        $iterator->setPositions($startPosition, $endPosition);
        $argLimits = collect([]);
        $parenCtr = 0;

        $startLimit = $iterator->currentPosition();

        while ($iterator->nextToken() && $iterator->isPositionUnrestricted()) {

            if ($iterator->isCurrent(T_WHITESPACE)) {
                continue;
            }
            if ($iterator->isCurrent('(')) {
                $parenCtr++;
            } elseif ($iterator->isCurrent(')')) {
                $parenCtr--;
            }
            // If it is a comma and argument token should is on the same level of the function being parsed
            elseif ($iterator->isCurrent(self::T_ARG_SEPARATOR) && $parenCtr == 0) {
                $endLimit = $iterator->previousPosition();

                $limits = [];
                $limits[static::LIMIT_START] = $startLimit;
                $limits[static::LIMIT_END] = $endLimit;

                $argLimits->push($limits);

                $startLimit = $iterator->nextPosition();
            }
            if ($iterator->currentPosition() == $endPosition) {
                $endLimit = $iterator->currentPosition();
                $limits = [];
                $limits[static::LIMIT_START] = $startLimit;
                $limits[static::LIMIT_END] = $endLimit;
                $argLimits->push($limits);
            }
        }

        if ($argsOptionalMin && $argsOptionalMin != $argLimits->count() || ! $argsOptionalMin && is_numeric($argCnt) && $argLimits->count() != $argCnt) {
            return $this->throwParsingError('FUNC_ARG_MISSING', 'Missing argument for function '.$functionName);
        }

        return $argLimits;

    }

    /**
     * @param  string  $functionName
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @param  int|string  $argCnt
     * @param  int|null  $argsOptionalMin
     * @return array
     */
    public function getArgumentLimitsArray($functionName, $startPosition, $endPosition, $argCnt = 'AUTO', $argsOptionalMin = null)
    {
        $argLimits = $this->getArgumentLimits($functionName, $startPosition, $endPosition, $argCnt);

        return $argLimits->toArray();
    }

    /**
     * @param  array  $limit
     * @param  int  $iterationCnt
     * @param  array|null  $return
     * @return mixed
     */
    protected function parseExpressionThruLimits($limit, $iterationCnt = 0, $return = null)
    {
        return $this->parseExpression($limit[static::LIMIT_START], $limit[static::LIMIT_END], $iterationCnt, $return);
    }

    /***************** List of functions starts here... ******************************/

    /**
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @return mixed
     */
    public function parseFnIf($startPosition, $endPosition)
    {

        [$conditionLimits, $expression1Limits, $expression2Limits] = $this->getArgumentLimitsArray('IF', $startPosition, $endPosition, 3);

        if ($this->parseExpressionThruLimits($conditionLimits)[Tokenizer::VALUE]) {
            return $this->parseExpressionThruLimits($expression1Limits, 1);
        } else {
            return $this->parseExpressionThruLimits($expression2Limits, 1);
        }

    }

    /**
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @return mixed
     */
    public function parseFnCase($startPosition, $endPosition)
    {

        $argLimits = $this->getArgumentLimits('CASE', $startPosition, $endPosition);
        $argLimitsCnt = $argLimits->count();
        if ($argLimitsCnt % 2 || $argLimitsCnt < 4) {
            $this->throwParsingError('FUNC_ARG_MISSING', 'Missing argument for method CASE');
        }

        $expressionLimits = $argLimits->shift();
        $expressionToken = $this->parseExpressionThruLimits($expressionLimits);
        $caseElseLimits = $argLimits->pop();

        $iteration = 1;
        do {
            $caseLimits = $argLimits->shift();
            $valueLimits = $argLimits->shift();
            if ($expressionToken[Tokenizer::VALUE] == $this->parseExpressionThruLimits($caseLimits, 1, ['CASE', ++$iteration, $expressionToken[Tokenizer::TYPE]])[Tokenizer::VALUE]) {
                return $this->parseExpressionThruLimits($valueLimits, 1);
            }
        } while ($argLimits->count());

        // if no matched found on cases
        return $this->parseExpressionThruLimits($caseElseLimits);
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    public function parseFnAnd($startPosition, $endPosition)
    {

        $argLimits = $this->getArgumentLimits('AND', $startPosition, $endPosition);

        if ($argLimits->count() < 2) {
            return $this->throwParsingError('FUNC_ARG_MISSING', 'Missing argument for method AND. Must be at least 2');
        }
        $iteration = 0;
        do {
            $expressionLimit = $argLimits->shift();
            $resultToken = $this->parseExpressionThruLimits($expressionLimit, $iteration, ['AND', $iteration + 1, static::T_LITERAL_BOOLEAN]);
            //            if($resultToken[Tokenizer::TYPE] != self::T_LITERAL_BOOLEAN)
            //                dd($resultToken);
            if ($resultToken[Tokenizer::VALUE] == false) {
                return $resultToken;
            }
        } while ($argLimits->isNotEmpty() && ++$iteration);

        return $resultToken;
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    public function parseFnOr($startPosition, $endPosition)
    {
        $argLimits = $this->getArgumentLimits('OR', $startPosition, $endPosition);

        if ($argLimits->count() < 2) {
            return $this->throwParsingError('FUNC_ARG_MISSING', 'Missing argument for method OR. Must be at least 2');
        }

        $iteration = 0;
        do {
            $expressionLimit = $argLimits->shift();
            $resultToken = $this->parseExpressionThruLimits($expressionLimit, $iteration, ['OR', $iteration + 1, static::T_LITERAL_BOOLEAN]);
            if ($resultToken[Tokenizer::VALUE] == true) {
                return $resultToken;
            }
        } while ($argLimits->isNotEmpty() && ++$iteration);

        return $resultToken;
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public function parseFnNot($startPosition, $endPosition)
    {

        $resultToken = $this->parseExpression($startPosition, $endPosition, 0, ['NOT', 1, static::T_LITERAL_BOOLEAN]);
        $notValue = ! ($resultToken[Tokenizer::VALUE]);

        return $this->createToken($notValue, 0, static::T_LITERAL_BOOLEAN);
    }

    /**
     * @return array
     */
    public function parseFnIsblank($startPosition, $endPosition)
    {
        $resultToken = $this->parseExpression($startPosition, $endPosition);

        return $this->createToken(($resultToken[Tokenizer::TYPE] == self::T_NULL), 0, self::T_LITERAL_BOOLEAN);
    }

    /**
     * @return mixed
     */
    public function parseFnBlankvalue($startPosition, $endPosition)
    {
        [$expressionLimits, $subExpressionLimits] = $this->getArgumentLimitsArray('BLANKVALUE', $startPosition, $endPosition, 2);
        $resultToken = $this->parseFnIsblank($expressionLimits[self::LIMIT_START], $expressionLimits[self::LIMIT_END]);

        if ($resultToken[Tokenizer::VALUE]) {
            return $this->parseExpressionThruLimits($subExpressionLimits);
        }

        return $this->parseExpression($expressionLimits[self::LIMIT_START], $expressionLimits[self::LIMIT_END]);
    }

    protected function parseLeftRight($startPosition, $endPosition, $funcName = 'LEFT')
    {

        [$textLimits, $numCharsLimits] = $this->getArgumentLimitsArray($funcName, $startPosition, $endPosition, 2);
        $strToken = $this->parseExpressionThruLimits($textLimits, static::NO_ITERATION, [$funcName, 1, static::T_LITERAL_STRING]);
        $numCharsToken = $this->parseExpressionThruLimits($numCharsLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_NUMBER]);

        if ($numCharsToken[Tokenizer::VALUE] < 0) {
            $numCharsToken[Tokenizer::VALUE] = 0;
        }

        return [$strToken, $numCharsToken];
    }

    /**
     * @return array
     */
    public function parseFnLeft($startPosition, $endPosition)
    {

        [$leftToken, $numCharsToken] = $this->parseLeftRight($startPosition, $endPosition);

        return $this->createToken(left($leftToken[Tokenizer::VALUE].'"', $numCharsToken[Tokenizer::VALUE] + 1), 0, self::T_LITERAL_STRING);
    }

    /**
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @return array
     */
    public function parseFnRight($startPosition, $endPosition)
    {

        [$rightToken, $numCharsToken] = $this->parseLeftRight($startPosition, $endPosition, 'RIGHT');

        return $this->createToken('"'.right($rightToken[Tokenizer::VALUE], $numCharsToken[Tokenizer::VALUE] + 1), 0, self::T_LITERAL_STRING);
    }

    public function parseFnMid($startPosition, $endPosition)
    {

        [$textLimits, $startNumLimits, $numCharsLimits] = $this->getArgumentLimitsArray('MID', $startPosition, $endPosition, 3);
        $textToken = $this->parseExpressionThruLimits($textLimits);
        $startNumToken = $this->parseExpressionThruLimits($startNumLimits);
        $numCharsToken = $this->parseExpressionThruLimits($numCharsLimits);

        return $this->createToken(substr($textToken[Tokenizer::VALUE], $startNumToken[Tokenizer::VALUE], $numCharsToken[Tokenizer::VALUE]), 0, self::T_LITERAL_STRING);
    }

    /**
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @return array
     *
     * @throws \Exception
     */
    public function parseFnRound($startPosition, $endPosition)
    {
        $funcName = 'ROUND';

        [$numberLimits, $digitLimits] = $this->getArgumentLimitsArray($funcName, $startPosition, $endPosition, 2);

        $numberToken = $this->parseExpressionThruLimits($numberLimits, static::NO_ITERATION, [$funcName, 1, static::T_LITERAL_NUMBER]);
        $digitToken = $this->parseExpressionThruLimits($digitLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_NUMBER]);

        if ($digitToken[Tokenizer::VALUE] < 0) {
            $digitToken[Tokenizer::VALUE] = 0;
        }

        return $this->createToken(round($numberToken[Tokenizer::VALUE], $digitToken[Tokenizer::VALUE], PHP_ROUND_HALF_UP), 0, self::T_LITERAL_NUMBER);
    }

    /**
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @return array
     *
     * @throws \Exception
     */
    public function parseFnMod($startPosition, $endPosition)
    {
        $funcName = 'MOD';
        [$numberLimits, $divisorLimits] = $this->getArgumentLimitsArray('ROUND', $startPosition, $endPosition, 2);
        $numberToken = $this->parseExpressionThruLimits($numberLimits, static::NO_ITERATION, [$funcName, 1, static::T_LITERAL_NUMBER]);
        $divisorToken = $this->parseExpressionThruLimits($divisorLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_NUMBER]);

        $result = $numberToken[Tokenizer::VALUE] % $divisorToken[Tokenizer::VALUE];

        return $this->createToken($result, 0, self::T_LITERAL_NUMBER);
    }

    /**
     * @param  int  $startPosition
     * @param  int  $endPosition
     * @return array
     */
    public function parseFnAbs($startPosition, $endPosition)
    {
        $numberToken = $this->parseExpression($startPosition, $endPosition, static::NO_ITERATION, ['ABS', 1, static::T_LITERAL_NUMBER]);

        return $this->createToken(abs($numberToken[Tokenizer::VALUE]), 0, self::T_LITERAL_NUMBER);
    }

    /**
     * @return array
     */
    public function parseFnBegins($startPosition, $endPosition)
    {
        $funcName = 'BEGINS';
        [$textLimits, $compareTextLimits] = $this->getArgumentLimitsArray($funcName, $startPosition, $endPosition, 2);
        $textToken = $this->parseExpressionThruLimits($textLimits, static::NO_ITERATION, [$funcName, 1, static::T_LITERAL_STRING]);
        $compareTextToken = $this->parseExpressionThruLimits($compareTextLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_STRING]);

        return $this->createToken(starts_with($textToken[Tokenizer::VALUE], $compareTextToken[Tokenizer::VALUE]), 0, self::T_LITERAL_STRING);
    }

    public function parseFnContains($startPosition, $endPosition)
    {
        $funcName = 'CONTAINS';
        [$textLimits, $compareTextLimits] = $this->getArgumentLimitsArray($funcName, $startPosition, $endPosition, 2);
        $textToken = $this->parseExpressionThruLimits($textLimits, static::NO_ITERATION, [$funcName, 1, static::T_LITERAL_STRING]);
        $compareTextToken = $this->parseExpressionThruLimits($compareTextLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_STRING]);

        return $this->createToken(strpos($textToken[Tokenizer::VALUE], $compareTextToken[Tokenizer::VALUE]), 0, self::T_LITERAL_BOOLEAN);
    }

    public function parseFnLen($startPosition, $endPosition)
    {
        $textToken = $this->parseExpression($startPosition, $endPosition, static::NO_ITERATION, ['LEN', 1, self::T_LITERAL_STRING]);

        return $this->createToken(strlen($textToken), 0, self::T_LITERAL_NUMBER);
    }

    public function parseFnLog($startPosition, $endPosition)
    {
        $numberToken = $this->parseExpression($startPosition, $endPosition, static::NO_ITERATION, ['LOG', 1, self::T_LITERAL_STRING]);

        return $this->createToken(log10($numberToken[Tokenizer::VALUE]), 0, self::T_LITERAL_NUMBER);
    }

    public function parseFnLower($startPosition, $endPosition)
    {

    }

    protected function parseLeftRightPad($funcName, $startPosition, $endPosition)
    {
        $argLimits = $this->getArgumentLimits($funcName, $startPosition, $endPosition, 3, 2);
        $textLimits = $argLimits->shift();
        $paddedLengthLimits = $argLimits->shift();
        $text = $this->parseExpressionThruLimits($textLimits, static::NO_ITERATION, [$funcName, 1, static::T_LITERAL_STRING])[Tokenizer::VALUE];
        $paddedLength = $this->parseExpressionThruLimits($paddedLengthLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_NUMBER])[Tokenizer::VALUE];

        if ($argLimits->isNotEmpty()) {      // optional pad_string is defined
            $padStringLimits = $argLimits->shift();
            $padString = $this->parseExpressionThruLimits($padStringLimits, static::NO_ITERATION, [$funcName, 3, static::T_LITERAL_STRING])[Tokenizer::VALUE];
        } else {
            $padString = '';
        }

        return [$text, $paddedLength, $padString];
    }

    public function parseFnLpad($startPosition, $endPosition)
    {

        [$text, $paddedLength, $padString] = $this->parseLeftRightPad('LPAD', $startPosition, $endPosition);
        $text = str_pad($text, $paddedLength, $padString, STR_PAD_LEFT);

        return $this->createToken($text, 0, static::T_LITERAL_STRING);
    }

    public function parseFnRpad($startPosition, $endPosition)
    {

        [$text, $paddedLength, $padString] = $this->parseLeftRightPad('LPAD', $startPosition, $endPosition);
        $text = str_pad($text, $paddedLength, $padString, STR_PAD_RIGHT);

        return $this->createToken($text, 0, static::T_LITERAL_STRING);
    }

    public function parseFnIspickval($startPosition, $endPosition)
    {

        $funcName = 'ISPICKVAL';
        [$pickListFieldLimits, $textLiteralLimits] = $this->getArgumentLimitsArray($funcName, $startPosition, $endPosition, 2);

        $pickListFieldToken = $this->parseExpressionThruLimits($pickListFieldLimits, static::NO_ITERATION, [$funcName, 1, static::T_VARIABLE]);
        $pickListTokenName = $pickListFieldToken[Tokenizer::VALUE];
        //dump('ispickval',$textLiteralLimits);
        // check if field is a picklist and get current value
        $picklistValue = (new EntityField)->checkInstanceFieldValue($this->entity, $this->entityInstance, $pickListTokenName, 'picklist');
        $textLiteralToken = $this->parseExpressionThruLimits($textLiteralLimits, static::NO_ITERATION, [$funcName, 2, static::T_LITERAL_STRING]);

        return $this->createToken('"'.$picklistValue.'"' == $textLiteralToken[Tokenizer::VALUE], 0, static::T_LITERAL_BOOLEAN);
    }

    public function parseFnSubstitute($startPosition, $endPosition)
    {

    }

    public function parseFnTrim($startPosition, $endPosition)
    {

    }

    // Date and time functions
    public function parseFnNow($startPosition, $endPosition)
    {
        return $this->createToken(Carbon::now()->toDateString(), 0, static::T_LITERAL_DATE);
    }

    protected function numberFormat($number)
    {
        return number_format($number, Operation::getDecimalPlaces(), '.', '');
    }

    /**
     * @param  int  $value
     * @param  int  $offset
     * @param  int  $type
     * @return array
     */
    protected function createToken($value, $offset, $type)
    {
        if ($type == self::T_LITERAL_NUMBER) {
            // $value = $this->numberFormat($value);
        }
        $token = [
            Tokenizer::VALUE => $value,
            Tokenizer::OFFSET => $offset,
            Tokenizer::TYPE => $type,
        ];

        return $token;
    }

    public static function getFunctionsInfo($keysOnly = false)
    {
        $modules = [
            'IF' => ['category' => 'Logical',
                'syntax' => 'IF(logical_test, value_if_true, value_if_false)',
                'description' => 'Checks whether a condition is true, and returns one value if TRUE and another value if FALSE.',
            ],
            'CASE' => ['category' => 'Logical',
                'syntax' => 'CASE(expression, value1, result1, value2, result2,...,else_result)',
                'description' => 'Checks an expression against a series of values.  If the expression compares equal to any value, the corresponding result is returned. If it is not equal to any of the values, the else-result is returned.',
            ],
            'AND' => ['category' => 'Logical',
                'syntax' => 'AND(logical1,logical2,...)',
                'description' => 'Checks whether all arguments are true and returns TRUE if all arguments are true.',
            ],
            'OR' => ['category' => 'Logical',
                'syntax' => 'OR(logical1,logical2,...)',
                'description' => 'Checks whether any of the arguments are true and returns TRUE or FALSE. Returns FALSE only if all arguments are false.',
            ],
            'NOT' => ['category' => 'Logical',
                'syntax' => 'NOT(logical)',
                'description' => 'Changes FALSE to TRUE or TRUE to FALSE',
            ],
            'ISBLANK' => ['category' => 'Logical',
                'syntax' => 'ISBLANK(expression)',
                'description' => 'Checks whether an expression is blank and returns TRUE or FALSE',
            ],
            'BLANKVALUE' => ['category' => 'Logical',
                'syntax' => 'BLANKVALUE(expression, substitute_expression)',
                'description' => 'Checks whether expression is blank and returns substitute_expression if it is blank. If expression is not blank, returns the original expression value.',
            ],
            'CONTAINS' => ['category' => 'Text',
                'syntax' => 'CONTAINS(text, compare_text)',
                'description' => 'Checks if text contains specified characters, and returns TRUE if it does. Otherwise, returns FALSE.',
            ],
            'LEFT' => ['category' => 'Text',
                'syntax' => 'LEFT(text, num_chars)',
                'description' => 'Returns the specified number of characters from the start of a text string.',
            ],
            'RIGHT' => ['category' => 'Text',
                'syntax' => 'RIGHT(text, num_chars)',
                'description' => 'Returns the specified number of characters from the end of a text string.',
            ],
            'ROUND' => ['category' => 'Math',
                'syntax' => 'ROUND(number,num_digits)',
                'description' => 'Rounds a number to a specified number of digits.',
            ],
            'MOD' => ['category' => 'Math',
                'syntax' => 'MOD(number,divisor)',
                'description' => 'Returns the remainder after a number is divided by a divisor.',
            ],
            'BEGINS' => ['category' => 'Text',
                'syntax' => 'BEGINS(text, compare_text)',
                'description' => 'Checks if text begins with specified characters and returns TRUE if it does. Otherwise returns FALSE.',
            ],
            'ABS' => ['category' => 'Math',
                'syntax' => 'ABS(number)',
                'description' => '',
            ],
            'LEN' => ['category' => 'Text',
                'syntax' => 'LEN(text)',
                'description' => 'Returns the number of characters in a text string.',
            ],
            'LOG' => ['category' => 'Math',
                'syntax' => 'LOG(number)',
                'description' => 'Returns the base 10 logarithm of n',
            ],
            'LOWER' => ['category' => 'Text',
                'syntax' => 'LOWER(text)',
                'description' => 'Converts all letters in the value to lowercase',
            ],
            'ISPICKVAL' => ['category' => 'Text',
                'syntax' => 'ISPICKVAL(picklist_field, text_literal)',
                'description' => 'Checks whether the value of a picklist field is equal to a string literal.',
            ],
        ];

        return $modules;
    }

    /*****************************Debugging functions ***********************************************/

    protected function getTokenValues()
    {
        return collect($this->tokens)->pluck(Tokenizer::VALUE);
    }

    protected function values($arrayValues)
    {
        return collect($arrayValues)->pluck(Tokenizer::VALUE);
    }
}
