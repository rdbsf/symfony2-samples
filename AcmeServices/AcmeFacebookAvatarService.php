<?php

namespace Acme\ApiBundle\Services;


use Acme\ApiBundle\Entity\User;
use Facebook\Facebook;
use Facebook\GraphNodes\GraphPicture;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Constraints\Null;

class AcmeFacebookAvatarService
{
    protected $neo4jService;
    protected $avatarService;
    protected $fb;


    public function __construct($neo4jService, $avatarService, $fbAppId, $fbAppSecret, $fbVersion)
    {
        $this->neo4jService = $neo4jService;
        $this->avatarService = $avatarService;

        $this->fb = new Facebook([
            'app_id' => $fbAppId,
            'app_secret' =>  $fbAppSecret,
            'default_graph_version' => $fbVersion
        ]);
    }

    /**
     * Save a FB avatar
     * @param \Acme\UserBundle\Entity\User $user the FOS User no the the Neo4J user
     * @return array|bool
     */
    public function saveUserAvatar(\Acme\UserBundle\Entity\User $user)
    {
        $facebookUserAccessToken = $user->getFacebookAccessToken();

        // Find user, check if avatar already exists
        $userNode = $this->neo4jService->getRepository('Acme\\ApiBundle\\Entity\\User')->findOneBy(array('fosUserId' => $user->getId()));

        // skip if custom avatar exits (non-tribe avatar) or no fb token
        if (!$userNode instanceof User || $userNode->hasCustomAvatar())
        {
            return false;
        }

        try {

            $response = $this->fb->get('/me?fields=picture.width(250).height(250)', $facebookUserAccessToken); //

        } catch(\Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
           // exit;
        } catch(\Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            //exit;
        }

        $responseArray = array();
        $fbPictureUrl = '';
        $forceEmptyAvatar = false;

        if (isset($response)) {

            $graphUser = $response->getGraphUser();

            $responseArray['graphUserPicture'] = $graphUser['picture'];

            if ($responseArray['graphUserPicture'] instanceof GraphPicture)
            {
                $fbPictureUrl = $responseArray['graphUserPicture']->getUrl();

                // put fb avatar on aws
                $fbPictureUrl = $this->avatarService->saveFbAvatar($fbPictureUrl, $user->getId());

                // if fb error we want to save empty avatar
                $forceEmptyAvatar = $fbPictureUrl == '' ? true : false;

            }

        }

        if ($userNode instanceof User && ($fbPictureUrl != '' || $forceEmptyAvatar))
        {
            // Save user
            $userNode->setAvatar($fbPictureUrl);
            $this->neo4jService->persist($userNode);
            $this->neo4jService->flush();

        }

        return $responseArray;

    }


}