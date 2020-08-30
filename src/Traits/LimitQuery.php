<?php

namespace Hooshid\QueryMaker\Traits;

trait LimitQuery
{
    /**
     * Resolve limit
     *
     * @return LimitQuery
     */
    private function resolveLimit()
    {
        if ($this->request->get(config('query-maker.parameters.limit'))) {
            $this->setLimit($this->request->get(config('query-maker.parameters.limit')));
        } else {
            $this->limit = config('query-maker.limit');
        }

        return $this;
    }

    /**
     * Set limit
     *
     * @param $limit
     */
    private function setLimit($limit)
    {
        $this->limit = ($limit == 'unlimited') ? null : (int)$limit;
    }

    /**
     * Check Has limit
     *
     * @return boolean
     */
    private function hasLimit()
    {
        return ($this->limit);
    }
}
