<?php

namespace Cocorico\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * DirectoryRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DirectoryRepository extends EntityRepository
{

    /**
     * @param $userId
     */
    public function findByUser($userId)
    {
        // FIXME: Is this even used ?
        $resp = $this->getFindByC4Id($userId)->getQuery()->getResult();
        return count($resp) > 0 ? $resp[0] : false;
    }

    public function getFindQueryBuilder()
    {
        $qB = $this->createQueryBuilder('d');
        return $qB;
    }

    public function getSome($limit= 10, $offset=0)
    {
        $qB = $this->getFindQueryBuilder();
        $qB->setMaxResults($limit)
           ->setFirstResult($offset)
           ->orderBy('d.name', 'asc')
           ->where('d.nature != \'n/a\'')
           ->where('d.isDelisted = false');

        return $qB;
    }

    public function getAll()
    {
        $qB = $this->getFindQueryBuilder();
        $qB->orderBy('d.name', 'asc')
           ->where('d.nature != \'n/a\'')
           ->where('d.isDelisted = false');

        return $qB;
    }

    public function getFindByC4Id($C4Id)
    {
        $qB = $this->getFindQueryBuilder();
        $qB->where('d.c4Id = :c4Id')
           ->setParameter('c4Id', $C4Id);

        return $qB;
    }


    public function getFindByUserId($UserId)
    {
        $qB = $this->getFindQueryBuilder();
        // FIXME: Do some real filtering here, please !
        $qB->setMaxResults(10);

        return $qB;
    }

    public function getFull()
    {
        $qB = $this->getFindQueryBuilder();
        $qB->orderBy('d.name', 'asc');

        return $qB;
    }
}
