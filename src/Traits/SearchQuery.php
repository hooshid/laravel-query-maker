<?php

namespace Hooshid\QueryMaker\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SearchQuery
{
    protected $search = null;

    /**
     * Resolve search
     *
     * @return $this
     */
    private function resolveSearch()
    {
        if ($this->request->get(config('query-maker.parameters.search'))) {
            $this->search = strip_tags($this->request->get(config('query-maker.parameters.search')));

            foreach ($this->columns as $column) {
                $match = Str::of($column)->explode(' as ');
                $col = $match[0];
                if ($col) {
                    if (Str::of($col)->contains('*')) {
                        $explode = Str::of($col)->explode('.');
                        $tableColumns = DB::select('SHOW FULL COLUMNS FROM ' . $explode[0]);
                        foreach ($tableColumns as $tableColumn) {
                            $this->addSearchWhereToQuery("{$explode[0]}.{$tableColumn->Field}");
                        }
                    } else {
                        $this->addSearchWhereToQuery("{$col}");
                    }
                }
            }
        }

        return $this;
    }

    /**
     * add search where to query
     *
     * @param $field
     * @return bool
     */
    private function addSearchWhereToQuery($field)
    {
        if (Str::endsWith($field, ['_id', '_at', '_url', '_date'])) {
            return false;
        }

        if ($this->isExcludedParameter(explode('.', $field)[0])) {
            return false;
        }

        $this->query->orWhere($field, 'LIKE', "%{$this->search}%");
    }
}
