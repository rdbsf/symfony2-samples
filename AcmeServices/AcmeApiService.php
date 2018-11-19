<?php

namespace Acme\ApiBundle\Services;

use Acme\ApiBundle\Entity\House;
use Acme\ApiBundle\Entity\User;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AcmeApiService {

    protected $neo4jService;
    private $authorizationChecker;
    private $AcmeSlugifyService;
    private $AcmeUserNameService;

    const SEARCH_TYPE_BOOKS = 'books';
    const SEARCH_TYPE_AUTHORS = 'authors';
    const SEARCH_TYPE_GENRES = 'genres';
    const SEARCH_TYPE_LISTS = 'lists';
    const SEARCH_TYPE_USERS = 'users';


    public function __construct($neo4jService, AuthorizationCheckerInterface $authorizationChecker, $AcmeSlugifyService)
    {
        $this->neo4jService = $neo4jService;
        $this->authorizationChecker = $authorizationChecker;
        $this->AcmeSlugifyService = $AcmeSlugifyService;
    }

    public function createUser($userId, $firstName, $lastName, $zipCode, $userType, $proFields = array())
    {
        $em = $this->neo4jService;

        $user = new User();
        $user->setFullName($firstName . ' ' . $lastName);
        $user->setFosUserId($userId);
        $user->setUserType($userType);

        $slug = $this->AcmeSlugifyService->getUserSlug($user);
        $user->setSlug($slug);

        if ($userType === User::USER_TYPE_PRO)
        {
            $user->setBusinessName($proFields['business_name']);
            $user->setPhoneNumber($proFields['phone_number']);
            //...
        }

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Update the user details
     */
    public function updateUser($userId, $firstName, $lastName, $zipCode, $avatar)
    {
        $em = $this->neo4jService;

        /** @var \Acme\ApiBundle\Repositories\UserRepository $userRepo */
        $userRepo = $em->getRepository(User::class);

        $AcmeUser = $userRepo->findOneBy(['fosUserId' => $userId]);

        if ($AcmeUser instanceof User)
        {
            if (!$this->authorizationChecker->isGranted('ROLE_ADMIN'))
            {
                $AcmeUser->setDisplayName($firstName, substr($lastName,0,1));
                $AcmeUser->setFullName($firstName . ' ' . $lastName);
            }

            $AcmeUser->setZipCode($zipCode);

            if ($avatar !== '')
            {
                $AcmeUser->setAvatar($avatar);
            }

            $em->persist($AcmeUser);
            $em->flush();

        }

        return $AcmeUser;
    }

    /**
     * Get a users slug by id
     * @param $userId
     * @return null
     */
    public function getUserSlug($userId)
    {
        $em = $this->neo4jService;

        /** @var \Acme\ApiBundle\Repositories\UserRepository $userRepo */
        $userRepo = $em->getRepository(User::class);

        $AcmeUser = $userRepo->findOneBy(['fosUserId' => $userId]);

        if ($AcmeUser instanceof User)
        {
            return $AcmeUser->getSlug();
        }

        return null;
    }

    /**
     * Check that email address for user is confirmed
     * @param $userId
     * @return bool
     */
    public function isEmailConfirmed($userId)
    {
        $em = $this->neo4jService;

        /** @var \Acme\ApiBundle\Repositories\UserRepository $userRepo */
        $userRepo = $em->getRepository(User::class);

        $AcmeUser = $userRepo->findOneBy(['fosUserId' => $userId]);

        if ($AcmeUser instanceof User)
        {
            return $AcmeUser->isEmailConfirmed();
        }

        return false;
    }
}