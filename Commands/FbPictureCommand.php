<?php

namespace Acme\ApiBundle\Command;

use Acme\ApiBundle\Entity\User;
use Facebook\GraphNodes\GraphPicture;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Facebook\Facebook;

use FOS\UserBundle\Command\CreateUserCommand as BaseCreateUserCommand;

class FbPictureCommand extends ContainerAwareCommand

{

    protected function configure()
    {
        $this
            ->setName('Acme:user:fbpicture')
            ->setDescription('Store a user facebook avatar to s3.')
            ;
    }

    /**
    * @see Command
    */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $awsBucket = $this->getContainer()->getParameter('aws_bucket');
        $awsBucketDir = $this->getContainer()->getParameter('aws_directory_user_profile');

        $em  = $this->getContainer()->get('neo4j.manager');

        /** @var \Acme\ApiBundle\Repositories\UserRepository $userRepo */
        $userRepo = $this->getContainer()->get('neo4j.manager')->getRepository('Acme\\ApiBundle\\Entity\\User');

        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $avatarService = $this->getContainer()->get('Acme_api.Acme_avatar_service');

        // All Users
        $users = $userRepo->getUsersWithFacebookAvatars();

        /** @var $user \Acme\ApiBundle\Entity\User $user */
        foreach($users as $user)
        {
            $fosUserId = $user->getFosUserId();

            $userFos = $userManager->findUserBy(array('id' => (int)$fosUserId));

            if (!$userFos instanceof \Acme\UserBundle\Entity\User)
            {
                continue;
            }

            $userFacebookId = $userFos->getFacebookId();

            if ($userFacebookId == '' && $userFacebookId === null)
            {
                continue;
            }

            $fbUserIdAvatarUrl = 'https://graph.facebook.com/'.$userFacebookId.'/picture';

            if ($userFos instanceof \Acme\UserBundle\Entity\User) //  && !$userNode->hasAvatar()
            {

                $thumbKey = $avatarService->saveFbAvatar($fbUserIdAvatarUrl, $userFos->getId());

                // Save updated key to database
                $user->setAvatar($thumbKey);
                $em->persist($user);
                $em->flush();

            }

        } // end foreach users

     $output->writeln(sprintf('Complete'));

    }
}