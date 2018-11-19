<?php

namespace Acme\ApiBundle\Services;

use Acme\ApiBundle\Entity\MessageQueue;
use Acme\ApiBundle\Event\AcmeUserEvent;
use Acme\ApiBundle\Services\AcmeQueueService;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Security\LoginManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;


class AcmeRegistrationEventListener implements EventSubscriberInterface
{

    private $queueService;
    private $neo4jService;

    public function __construct(AcmeQueueService $queueService, $neo4jService)
    {

        $this->queueService = $queueService;
        $this->neo4jService = $neo4jService;
    }

    public static function getSubscribedEvents()
    {
        return array(
            FOSUserEvents::REGISTRATION_COMPLETED => 'onAcmeRegistrationCompleted',
            FOSUserEvents::REGISTRATION_CONFIRMED => 'onAcmeRegistrationConfirmed'
        );
    }

    public function onAcmeRegistrationCompleted(FilterUserResponseEvent $event, $eventName = null, EventDispatcherInterface $eventDispatcher = null)
    {
        /** @var $fosUser \FOS\UserBundle\Model\UserInterface */
        $fosUser = $event->getUser();

        /** @var \Acme\ApiBundle\Entity\User $user */
        $user = $this->neo4jService->getRepository('Acme\\ApiBundle\\Entity\\User')->findOneByFosUserId($fosUser->getId());

        // Users that did not register with facebook should receive the email confirmation first
        if ($fosUser->getFacebookId() === '' || $fosUser->getFacebookId() == null)
        {
            $arr = array(
                'cmd' => MessageQueue::MESSAGE_TYPE_EMAIL_CONFIRMATION,
                'user_id' => $user->getId()
            );
        }
        else {
            $arr = array(
                'cmd' => MessageQueue::MESSAGE_TYPE_WELCOME,
                'user_id' => $user->getId()
            );
        }

        $this->queueService->send(json_encode($arr));

    }

    public function onAcmeRegistrationConfirmed(FilterUserResponseEvent $event, $eventName = null, EventDispatcherInterface $eventDispatcher = null)
    {
        /** @var $fosUser \FOS\UserBundle\Model\UserInterface */
        $fosUser = $event->getUser();

        // Users that did not register with facebook should receive the welcome email
        if ($fosUser->getFacebookId() === '' || $fosUser->getFacebookId() == null) {

            $arr = array(
                'cmd' => MessageQueue::MESSAGE_TYPE_WELCOME,
                'user_id' => $fosUser->getId()
            );
            $this->queueService->send(json_encode($arr));
        }


    }
}
