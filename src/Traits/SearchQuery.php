<?php

namespace Hooshid\QueryMaker\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SearchQuery
{
    /**
     * Resolve search
     *
     * @return $this
     */
    private function resolveSearch()
    {
        if ($this->request->get(config('query-maker.parameters.search'))) {
            $search = trim(strip_tags($this->request->get(config('query-maker.parameters.search'))));
            $columns = [];

            foreach ($this->columns as $column) {
                $match = Str::of($column)->explode(' as ');
                $col = $match[0];
                if ($col) {
                    if (Str::of($col)->contains('*')) {
                        $explode = Str::of($col)->explode('.');
                        $tableColumns = DB::select('SHOW FULL COLUMNS FROM ' . $explode[0]);
                        foreach ($tableColumns as $tableColumn) {
                            $columns[] = "{$explode[0]}.{$tableColumn->Field}";
                        }
                    } else {
                        $columns[] = $col;
                    }
                }
            }

            if (isset($columns)) {
                $this->addSearchWhereToQuery($columns, $search);
            }
        }

        return $this;
    }

    /**
     * add search where to query
     *
     * @param $fields
     * @param $search
     * @return void
     */
    private function addSearchWhereToQuery($fields, $search)
    {
        $this->query->where(function ($query) use ($fields, $search) {
            foreach ($fields as $field) {
                if ($this->checkFieldCanBeQueried($field)) {
                    $query->orWhere($field, 'LIKE', "%{$search}%");
                }
            }
        });
    }

    /**
     * check field condition fo query
     *
     * @param $field
     * @return bool
     */
    private function checkFieldCanBeQueried($field)
    {
        if (Str::endsWith($field, ['_id', '_at', '_date'])) {
            return false;
        }

        if ($this->isExcludedParameter(explode('.', $field)[0])) {
            return false;
        }

        return true;
    }
}
