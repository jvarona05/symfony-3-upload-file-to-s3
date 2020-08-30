<?php

namespace AppBundle\Security\Voter;

use AppBundle\Entity\UserFile;
use FOS\UserBundle\Model\User as BaseUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class DocumentVoter extends Voter
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, ['MANAGE']) && $subject instanceof UserFile;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        /** @var UserFile $subject */
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof BaseUser) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case 'MANAGE':
                // this is the author!
                if ($subject->getUser() == $user) {
                    return true;
                }

                if ($this->security->isGranted('ROLE_ADMIN_ARTICLE')) {
                    return true;
                }

                return false;
        }

        return false;
    }
}
