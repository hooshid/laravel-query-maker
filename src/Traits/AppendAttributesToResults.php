<?php

namespace Hooshid\QueryMaker\Traits;

trait AppendAttributesToResults
{
    /**
     * Get append from builder
     *
     * @param $appends
     * @return AppendAttributesToResults
     */
    public function setAppends($appends)
    {
        $this->appends = explode(',', $appends);

        return $this;
    }

    /**
     * Add appends to model
     *
     * @param $result
     * @return mixed
     */
    private function addAppendsToModel($result)
    {
        $result->map(function ($item) {
            $item->append($this->appends);
            return $item;
        });

        return $result;
    }

    /**
     * Check Has appends
     *
     * @return boolean
     */
    private function hasAppends()
    {
        return (count($this->appends) > 0);
    }
}
