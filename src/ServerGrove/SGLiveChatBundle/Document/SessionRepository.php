<?php

namespace ServerGrove\SGLiveChatBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository;
use MongoDate;

/**
 * Description of SessionRepository
 *
 * @author Ismael Ambrosi<ismael@servergrove.com>
 */
class SessionRepository extends DocumentRepository
{

    public function getRequestedChats()
    {
        return $this->createQueryBuilder()->field('updatedAt')->range(new MongoDate(time() - 300), new MongoDate(time()))->getQuery()->execute();
    }

    public function getRequestedChatsArray()
    {
        return array_map(
        function (Session $chat)
        {
            $operator = array();
            if ($chat->getOperator()) {
                $operator['id'] = $chat->getOperator()->getId();
                $operator['name'] = $chat->getOperator()->getName();
            }
            return array(
                'id' => $chat->getId(),
                'visitor' => array(
                    'id' => $chat->getVisitor()->getId(),
                    'name' => $chat->getVisitor()->getName(),
                    'email' => $chat->getVisitor()->getEmail()),
                'question' => $chat->getQuestion(),
                'time' => $chat->getCreatedAt()->format('Y-m-d H:i:s'),
                'duration' => $chat->getUpdatedAt()->format('U') - $chat->getCreatedAt()->format('U'),
                'operator' => $operator,
                'status' => array(
                    'id' => $chat->getStatusId(),
                    'name' => $chat->getStatus()));
        }, $this->getRequestedChats()->toArray());
    }

    public function closeSessions()
    {
        $this->createQueryBuilder()->field('statusId')->set(Session::STATUS_CANCELED)->field('statusId')->notIn(array(
            Session::STATUS_CANCELED,
            Session::STATUS_WAITING,
            Session::STATUS_CLOSED))->field('updatedAt')->lt(new MongoDate(time() - 300))->update()->getQuery()->execute();
    }

    public function getSessionIfNotFinished($id)
    {
        return $this->createQueryBuilder()->field('id')->equals($id)->field('statusId')->notIn(array(
            Session::STATUS_CANCELED,
            Session::STATUS_CLOSED))->getQuery()->getSingleResult();
    }

    public function getOpenInvites()
    {
        return $this->find(array(
            'statusId' => Session::STATUS_INVITE));
    }

}