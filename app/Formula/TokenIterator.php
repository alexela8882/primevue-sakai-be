<?php

namespace App\Formula;

use Nette\Utils\TokenIterator as Iterator;

class TokenIterator extends Iterator
{
    protected $endPosition = null;

    /**
     * @param  int  $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    public function setPositions($position, $endPosition)
    {
        $this->position = $position;
        $this->endPosition = $endPosition;
    }

    public function currentPosition()
    {
        return $this->position;
    }

    public function shiftPosition($step = 1)
    {
        $this->position -= $step;
    }

    public function previousPosition($step = 1)
    {
        return ($this->position != -1) ? $this->position - $step : $this->position;
    }

    public function nextPosition()
    {
        return ($this->position != $this->lastPosition()) ? $this->position + 1 : null;
    }

    public function checkNextToken()
    {
        if (! $this->lastPosition()) {
            return $this->tokens[$this->position + 1];
        }
    }

    public function isPositionUnrestricted()
    {
        if (! $this->endPosition) {
            return true;
        } else {
            return $this->position <= $this->endPosition;
        }
    }

    public function lastPosition()
    {
        return count($this->tokens) - 1;
    }

    public function skipWhile($type)
    {
        while ($this->isCurrent($type)) {
            $this->nextToken();
        }
    }

    /**
     * @param  array  $tokenTypes
     * @return mixed
     */
    public function isCurrentIn($tokenTypes)
    {
        return call_user_func_array([$this, 'isCurrent'], $tokenTypes);
    }
}
