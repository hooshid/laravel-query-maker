<?php

namespace Hooshid\QueryMaker\Traits;

use Hooshid\QueryMaker\Exceptions\UnknownColumnException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ConditionsQuery
{
    /**
     * Store all filters before added to query.
     *
     * @var string
     */
    protected $queryParameters = [];

    /**
     * The excluded parameters for filters.
     *
     * @var string
     */
    protected $excludedParameters = [];

    /**
     * Resolve filters
     *
     * @return FiltersQuery
     */
    private function resolveConditions()
    {
        if ($this->request->get(config('query-maker.parameters.conditions'))) {
            $this->getConditions();

            $this->setWheres($this->queryParameters);
        }

        if ($this->hasWheres()) {
            $this->excludedParameters = array_merge($this->excludedParameters, config('query-maker.excluded_parameters'));

            $this->addWheresToQuery();
        }

        return $this;
    }

    /**
     * Get conditions from request
     */
    private function getConditions()
    {
        $conditions = $this->request->query(config('query-maker.parameters.conditions'));
        $data = json_decode($conditions);

        foreach ($data as $item) {
            // find real name if column set with alias name in query
            $key = $this->findRealNameOfAliasColumn($item->key);

            if ($key) {
                // prepare value
                $value = strip_tags($item->value);
                $value = str_replace('*', '%', $value);
                $value = trim($value);

                $comparison = $this->setOperator($item->comparison);

                $this->queryParameters[] = [
                    'key' => $key,
                    'operator' => $comparison,
                    'value' => $value
                ];
            }
        }
    }

    /**
     * Detect filter operator
     *
     * @param $name
     * @return string
     */
    private function setOperator($name)
    {
        if ($name === "eq") {
            return '=';
        } elseif ($name === "uneq") {
            return '!=';
        } elseif ($name === "gt") {
            return '>';
        } elseif ($name === "gte") {
            return '>=';
        } elseif ($name === "lt") {
            return '<';
        } elseif ($name === "lte") {
            return '<=';
        } elseif ($name === "like") {
            return "like";
        } elseif ($name === "unlike") {
            return "not like";
        } elseif ($name === "between") {
            return 'between';
        } elseif ($name === "not_between") {
            return 'not_between';
        } elseif ($name === "in") {
            return 'in';
        } elseif ($name === "not_in") {
            return 'not_in';
        } elseif ($name === "is_null") {
            return 'is_null';
        } elseif ($name === "is_not_null") {
            return 'is_not_null';
        } else {
            return '=';
        }
    }

    /**
     * Set filters
     *
     * @param $parameters
     */
    private function setWheres($parameters)
    {
        $this->wheres = $parameters;
    }

    /**
     * Check Has wheres
     *
     * @return boolean
     */
    private function hasWheres()
    {
        return (count($this->wheres) > 0);
    }

    /**
     * add all filters to query
     */
    private function addWheresToQuery()
    {
        foreach ($this->wheres as $where) {
            $this->addWhereToQuery($where);
        }
    }

    /**
     * add each filter to query
     *
     * @param $where
     * @return boolean
     */
    private function addWhereToQuery($where)
    {
        /** @var string $key */
        /** @var string $operator */
        /** @var string $value */
        extract($where);

        if ($this->isExcludedParameter($key)) {
            return false;
        }

        /*
        if ($this->hasCustomFilter($key)) {
            return $this->applyCustomFilter($key, $operator, $value, $type);
        }
        */

        /*
        if (!$this->hasTableColumn($key)) {
            throw new UnknownColumnException("Unknown column '{$key}'");
        }
        */

        if ($operator === 'between') {
            if ($this->countComma($value) < 2) {
                return false;
            }
            $this->query->whereBetween($key, explode(',', $value));
        } else if ($operator === 'not_between') {
            if ($this->countComma($value) < 2) {
                return false;
            }
            $this->query->whereNotBetween($key, explode(',', $value));
        } else if ($operator === 'in') {
            $this->query->whereIn($key, explode(',', $value));
        } else if ($operator === 'not_in') {
            $this->query->whereNotIn($key, explode(',', $value));
        } else if ($operator === 'is_null') {
            $this->query->whereNull($key);
        } else if ($operator === 'is_not_null') {
            $this->query->whereNotNull($key);
        } else {
            $this->query->where($key, $operator, $value);
        }

        return true;
    }

    /**
     * count number of items with comma separator
     *
     * @param $value
     * @return boolean
     */
    private function countComma($value)
    {
        return count(explode(',', $value));
    }

    /**
     * find real name of alias columns
     *
     * @param $key
     * @return boolean
     */
    private function findRealNameOfAliasColumn($key)
    {
        foreach ($this->columns as $column) {
            $match = Str::endsWith($column, " as " . $key);
            if ($match) {
                $tableAndColumn = Str::of($column)->explode(' as ');
                return $tableAndColumn[0];
            }
        }
        return $this->table . "." . $key;
    }

    /**
     * Check table has given column
     *
     * @param $column
     * @return boolean
     */
    private function hasTableColumn($column)
    {
        return (Schema::hasColumn($this->model->getTable(), $column));
    }

    /**
     * Check given key excluded to filter
     *
     * @param $key
     * @return boolean
     */
    private function isExcludedParameter($key)
    {
        return in_array($key, $this->excludedParameters);
    }
}
