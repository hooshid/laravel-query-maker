<?php

namespace Hooshid\QueryMaker;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Hooshid\QueryMaker\Traits\FiltersQuery;
use Hooshid\QueryMaker\Traits\OrderQuery;
use Hooshid\QueryMaker\Traits\LimitQuery;
use Hooshid\QueryMaker\Traits\AppendAttributesToResults;
use Hooshid\QueryMaker\Traits\AddIncludesToQuery;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Hooshid\QueryMaker\Exceptions\InvalidRelation;

class QueryMaker extends Builder
{
    use FiltersQuery,
        OrderQuery,
        LimitQuery,
        AppendAttributesToResults,
        AddIncludesToQuery;

    /**
     * The model being queried.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    protected $table;

    /**
     * The base query builder instance.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * The orderings for the query.
     *
     * @var array
     */
    protected $orders = [];

    /**
     * The maximum number of records to return.
     *
     * @var int
     */
    protected $limit;

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $includes = [];

    /**
     * The appends that should be added to results.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The base request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * use table alias for join (real table name or sha1)
     *
     * @var boolean
     */
    protected $useTableAlias = false;

    /**
     * appendRelationsCount
     *
     * @var boolean
     */
    protected $appendRelationsCount = false;

    /**
     * store joined tables, we want join table only once
     *
     * @var array
     */
    protected $joinedTables = [];

    /**
     * QueryMaker constructor.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $model
     * @param null|\Illuminate\Http\Request $request
     */
    public function __construct($model, ?Request $request = null)
    {
        parent::__construct(clone $model->getQuery());

        $this->model = $model;

        $this->table = $this->model->getModel()->getTable();

        $this->query = $this->model->newQuery();

        $this->request = $request;
    }

    /**
     * Create a new QueryMaker for a request and model.
     *
     * @param string|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $baseQuery Model class or base query builder
     * @param \Illuminate\Http\Request $request
     *
     * @return \Hooshid\QueryMaker\QueryMaker
     */
    public static function for($baseQuery, ?Request $request = null): self
    {
        if (is_string($baseQuery)) {
            /** @var Builder $baseQuery */
            $baseQuery = $baseQuery::query();
        }

        return new static($baseQuery, $request ?? app(Request::class));
    }

    /**
     * Build query
     *
     * @return QueryMaker
     */
    public function build()
    {
        // set filters to query
        $this->resolveFilters();

        // set orders to query
        $this->resolveOrders();

        // set limit to query
        $this->resolveLimit();

        // add selected columns to query
        if (empty($this->columns)) {
            $this->columns = ['*'];
        }
        $this->query->select($this->columns);

        // set includes
        if ($this->hasIncludes()) {
            $this->query->with($this->includes);
        }

        return $this;
    }

    /**
     * fetch data and return in default format
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|string $result
     * @throws Exception
     */
    public function getData()
    {
        if (!$this->hasLimit()) {
            throw new Exception("You can't use unlimited option for pagination", 1);
        }

        try {
            $result = $this->query->paginate($this->limit);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

        if ($this->hasAppends()) {
            $result = $this->addAppendsToModel($result);
        }

        return $result;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|string $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $result = $this->query->get();

        if ($this->hasAppends()) {
            $result = $this->addAppendsToModel($result);
        }

        return $result;
    }

    /**
     * Set the columns to be selected.
     *
     * @param array|mixed $columns
     * @param null $prefixTable
     * @param null $prefixColumns
     * @return $this
     */
    public function selectColumns($columns = ['*'], $prefixTable = null, $prefixColumns = null)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if ($prefixTable) {
            $prefixTable = $prefixTable . '.';
        } else {
            $prefixTable = $this->table . '.';
        }

        foreach ($columns as $column) {
            if ($prefixColumns) {
                $this->columns[] = $prefixTable . $column . ' as ' . $prefixColumns . '_' . $column;
            } else {
                $this->columns[] = $prefixTable . $column;
            }
        }

        return $this;
    }

    /**
     * Join related tables.
     *
     * @param $relation
     * @param string $type
     * @param string[] $columns
     * @param string $prefixColumns
     * @return $this
     * @throws InvalidRelation
     */
    public function joinRelation($relation, $type = 'inner', $columns = ['*'], $prefixColumns = null)
    {
        $this->performJoin($relation, $type, $columns, $prefixColumns);

        return $this;
    }

    /**
     * Detect relation method
     *
     * @param string $type
     * @return string
     */
    protected function detectRelationMethod($type)
    {
        if ($type == 'left') {
            return 'leftJoin';
        } elseif ($type == 'right') {
            return 'rightJoin';
        } elseif ($type == 'cross') {
            return 'crossJoin';
        }
        return 'join';
    }

    /**
     * Create join
     *
     * @param $relation
     * @param string $type
     * @param $columns
     * @param $prefixColumns
     * @return string
     * @throws InvalidRelation
     */
    protected function performJoin($relation, $type, $columns, $prefixColumns)
    {
        // detect join method
        $joinMethod = $this->detectRelationMethod($type);

        // detect current model data
        $baseModel = $this->getModel();
		$baseTable = $baseModel->model->getTable();
        //$baseTable = $baseModel->getTable();
        //$basePrimaryKey = $baseModel->getKeyName();

        $currentModel = $baseModel;
        $currentTableAlias = $baseTable;


        /** @var Relation $relatedRelation */
		$relatedRelation = $baseModel->getModel()->$relation();
        //$relatedRelation = $currentModel->$relation();
        $relatedModel = $relatedRelation->getRelated();
        $relatedPrimaryKey = $relatedModel->getKeyName();
        $relatedTable = $relatedModel->getTable();
        //$relatedTableAlias = $this->useTableAlias ? sha1($relatedTable) : $relatedTable;
		$relatedTableAlias = $relatedTable .'_'. rand(1,999999999);

        $relationsAccumulated[] = $relatedTableAlias;
        $relationAccumulatedString = implode('_', $relationsAccumulated);

        // relations count
        if ($this->appendRelationsCount) {
            $this->selectRaw('COUNT(' . $relatedTableAlias . '.' . $relatedPrimaryKey . ') as ' . $relationAccumulatedString . '_count');
        }

        if (!in_array($relationAccumulatedString, $this->joinedTables)) {
            //$joinQuery = $relatedTable . ($this->useTableAlias ? ' as ' . $relatedTableAlias : '');
			$joinQuery = $relatedTable . ' as ' . $relatedTableAlias;

            if ($relatedRelation instanceof BelongsTo) {
                $relatedKey = $relatedRelation->getQualifiedForeignKeyName();
                $relatedKey = last(explode('.', $relatedKey));
                $ownerKey = $relatedRelation->getOwnerKeyName();

                $this->$joinMethod($joinQuery, function ($join) use ($relatedRelation, $relatedTableAlias, $relatedKey, $currentTableAlias, $ownerKey) {
                    $join->on($relatedTableAlias . '.' . $ownerKey, '=', $currentTableAlias . '.' . $relatedKey);
                });
            } elseif ($relatedRelation instanceof HasOne || $relatedRelation instanceof HasMany) {
                $relatedKey = $relatedRelation->getQualifiedForeignKeyName();
                $relatedKey = last(explode('.', $relatedKey));
                $localKey = $relatedRelation->getQualifiedParentKeyName();
                $localKey = last(explode('.', $localKey));

                $this->$joinMethod($joinQuery, function ($join) use ($relatedRelation, $relatedTableAlias, $relatedKey, $currentTableAlias, $localKey) {
                    $join->on($relatedTableAlias . '.' . $relatedKey, '=', $currentTableAlias . '.' . $localKey);
                });
            } else {
                throw new InvalidRelation();
            }
        }

        $this->joinedTables[] = implode('_', $relationsAccumulated);

        if ($columns) {
            if ($prefixColumns === 'null' or $prefixColumns === null) {
                $prefixColumns = Str::snake($relation);
            }

            $this->selectColumns($columns, $$relatedTableAlias, $prefixColumns);
        }

        return true;
    }
}
