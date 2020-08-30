<?php

namespace Hooshid\QueryMaker\Traits;

trait AddIncludesToQuery
{
    /**
     * Set the relationships that should be eager loaded.
     *
     * @param $includes
     * @return $this
     */
    public function setIncludes($includes)
    {
        $this->includes = array_filter(explode(',', $includes));

        return $this;
    }

    /**
     * Check Has includes
     *
     * @return boolean
     */
    private function hasIncludes()
    {
        return (count($this->includes) > 0);
    }
}
