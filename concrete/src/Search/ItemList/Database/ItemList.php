<?php
namespace Concrete\Core\Search\ItemList\Database;

use Concrete\Core\Search\Column\Set;
use Concrete\Core\Search\ItemList\Column;
use Concrete\Core\Search\StickyRequest;
use Database;
use Concrete\Core\Search\ItemList\ItemList as AbstractItemList;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\DBAL\Query\QueryBuilder;

abstract class ItemList extends AbstractItemList
{
    /** @var \Doctrine\DBAL\Query\QueryBuilder */
    protected $query;

    /** @var  \Concrete\Core\Search\StickyRequest | null */
    protected $searchRequest;

    abstract public function createQuery();

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder $query
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function finalizeQuery(\Doctrine\DBAL\Query\QueryBuilder $query)
    {
        return $query;
    }

    public function __construct(StickyRequest $req = null)
    {
        $this->query = Database::get()->createQueryBuilder();
        $this->searchRequest = $req;
        $this->createQuery();
    }

    /**
     * @return StickyRequest|null
     */
    public function getSearchRequest()
    {
        return $this->searchRequest;
    }

    public function getQueryObject()
    {
        return $this->query;
    }

    public function deliverQueryObject()
    {
        // setup the default sorting based on the request.
        $this->setupAutomaticSorting($this->searchRequest);
        $query = clone $this->query;
        $query = $this->finalizeQuery($query);
        return $query;
    }

    public function getOrderByColumns(Set $set)
    {
        $query = $this->deliverQueryObject();
        $orderBy = $query->getQueryPart('orderBy');
        $return = array();
        foreach ($orderBy as $data) {
            $data  = explode(" ", $data);
            $column = $set->getColumnByKey($data[0]);
            if ($column) {
                $column->setColumnSortDirection($data[1]);
                $return[] = $column;
            }
        }
        return $return;
    }

    public function executeGetResults()
    {
        return $this->deliverQueryObject()->execute()->fetchAll();
    }

    public function debugStart()
    {
        if ($this->isDebugged()) {
            Database::get()->getConfiguration()->setSQLLogger(new EchoSQLLogger());
        }
    }

    public function debugStop()
    {
        if ($this->isDebugged()) {
            Database::get()->getConfiguration()->setSQLLogger(null);
        }
    }

    protected function executeSortBy($column, $direction = 'asc')
    {
        if (in_array(strtolower($direction), array('asc', 'desc'))) {
            $this->query->orderBy($column, $direction);
        } else {
            throw new \Exception(t('Invalid SQL in order by'));
        }
    }

    protected function executeSanitizedSortBy($column, $direction = 'asc')
    {
        if (preg_match('/[^0-9a-zA-Z\$\.\_\x{0080}-\x{ffff}]+/u', $column) === 0) {
            $this->executeSortBy($column, $direction);
        } else {
            throw new \Exception(t('Invalid SQL in order by'));
        }
    }

    /**
     * @deprecated
     */
    public function filter($field, $value, $comparison = '=')
    {
        if ($field == false) {
            $this->query->andWhere($value); // ugh
        } else {
            $this->query->andWhere(implode(' ', array(
               $field, $comparison, $this->query->createNamedParameter($value),
            )));
        }
    }

    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
